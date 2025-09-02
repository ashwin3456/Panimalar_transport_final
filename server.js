// server.js — Unified backend for Admin / Driver / Faculty / Student dashboards
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const path = require("path");
const fs = require("fs");
const ngrok = require("ngrok");
const qr = require("qrcode-terminal");
const multer = require("multer");
const Database = require("better-sqlite3");

// ---------- Config ----------
const NGROK_AUTHTOKEN = "31EOHnY47ELp1FI1pkxT13H9Y5H_5njZSGX5AK79Ki8LnMnye"; // replace
const PORT = 4000;

// ---------- Directories ----------
const STORAGE_DIR = path.join(__dirname, "storage");
if (!fs.existsSync(STORAGE_DIR)) fs.mkdirSync(STORAGE_DIR, { recursive: true });
const UPLOADS_DIR = path.join(__dirname, "public", "uploads");
if (!fs.existsSync(UPLOADS_DIR)) fs.mkdirSync(UPLOADS_DIR, { recursive: true });

const DATA_FILE = path.join(STORAGE_DIR, "buses.json"); // main storage
const USERS_FILE = path.join(STORAGE_DIR, "users.json");
const DB_FILE = path.join(STORAGE_DIR, "data.db");

// ---------- Multer Upload ----------
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, UPLOADS_DIR),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname) || ".jpg";
    cb(null, `${Date.now()}-${Math.random().toString(36).slice(2, 8)}${ext}`);
  },
});
const upload = multer({ storage });

// ---------- Express + Socket.IO ----------
const app = express();
const server = http.createServer(app);
const io = new Server(server);

// ---------- In-memory state ----------
let buses = [];
let drivers = [];
let stops = [];
let routeOrder = [];
let users = [];

// ---------- SQLite ----------
let db;
try {
  db = new Database(DB_FILE);
  db.prepare(
    "CREATE TABLE IF NOT EXISTS snapshots (id INTEGER PRIMARY KEY AUTOINCREMENT, payload TEXT, savedAt TEXT)"
  ).run();
  console.log("✅ SQLite ready:", DB_FILE);
} catch (e) {
  console.warn("⚠ SQLite init failed:", e.message);
  db = null;
}

// ---------- Load Data ----------
function loadData() {
  try {
    if (fs.existsSync(DATA_FILE)) {
      const parsed = JSON.parse(fs.readFileSync(DATA_FILE, "utf8"));
      buses = parsed.buses || [];
      drivers = parsed.drivers || [];
      stops = parsed.stops || [];
      routeOrder = parsed.routeOrder || [];
    }
    if (fs.existsSync(USERS_FILE)) {
      users = JSON.parse(fs.readFileSync(USERS_FILE, "utf8")) || [];
    }
  } catch (err) {
    console.error("❌ Load error:", err);
  }
}
loadData();

// ---------- Save Functions ----------
const SAVE_DEBOUNCE_MS = 800;
let saveTimeout = null;
function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(() => {
    const payload = { buses, drivers, stops, routeOrder, savedAt: new Date().toISOString() };
    fs.writeFileSync(DATA_FILE, JSON.stringify(payload, null, 2));
    if (db) {
      db.prepare("INSERT INTO snapshots (payload, savedAt) VALUES (?, ?)").run(
        JSON.stringify(payload),
        new Date().toISOString()
      );
    }
    console.log("💾 State saved");
  }, SAVE_DEBOUNCE_MS);
}
function saveUsers() {
  fs.writeFileSync(USERS_FILE, JSON.stringify(users, null, 2));
  console.log("💾 Users saved");
}

// ---------- Helpers ----------
const uid = (p = "id") => p + "_" + Math.random().toString(36).slice(2, 9);
function getDashboardForRole(role) {
  switch ((role || "").toLowerCase()) {
    case "admin": return "/admin_dashboard.html";
    case "driver": return "/driver_dashboard.html";
    case "faculty": return "/faculty_dashboard.html";
    case "student": return "/student_dashboard.html";
    default: return "/";
  }
}

// ---------- Middlewares ----------
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use("/uploads", express.static(UPLOADS_DIR));
app.use(express.static(path.join(__dirname, "public")));

// ---------- API Endpoints ----------
// System data
app.get("/api/data", (req, res) => {
  res.json({ buses, drivers, stops, routeOrder });
});
app.post("/api/data", (req, res) => {
  const { buses: b, drivers: d, stops: s, routeOrder: r } = req.body;
  if (Array.isArray(b)) buses = b;
  if (Array.isArray(d)) drivers = d;
  if (Array.isArray(s)) stops = s;
  if (Array.isArray(r)) routeOrder = r;
  scheduleSave();
  io.emit("buses", buses);
  io.emit("drivers", drivers);
  io.emit("stops", stops);
  res.json({ status: "ok" });
});

// Profiles
app.get("/profile/:id", (req, res) => {
  const u = users.find((x) => String(x.id) === req.params.id);
  if (!u) return res.status(404).json({ error: "Not found" });
  res.json(u);
});
app.post("/profile/:id", upload.single("photo"), (req, res) => {
  const id = req.params.id;
  let u = users.find((x) => String(x.id) === id);
  if (!u) {
    u = { id, role: req.body.role || "student", email: req.body.email || "" };
    users.push(u);
  }
  Object.assign(u, req.body);
  if (req.file) u.profile_photo = "uploads/" + path.basename(req.file.path);
  saveUsers();
  io.emit("profileUpdated", u);
  res.json({ success: true, user: u });
});

// Auth
app.post("/signup", (req, res) => {
  const { role, email, password, name } = req.body;
  if (!role || !email || !password)
    return res.status(400).json({ message: "Missing fields" });
  if (users.find((u) => u.email === email && u.role === role))
    return res.status(409).json({ message: "User exists" });

  const newUser = { id: uid("user"), role, email, password, name, createdAt: new Date().toISOString() };
  users.push(newUser);
  saveUsers();
  res.status(201).json({ message: "User created", redirect: getDashboardForRole(role), user: newUser });
});
app.post("/login", (req, res) => {
  const { email, password, role } = req.body;
  const user = users.find((u) => u.email === email && u.role === role);
  if (!user || user.password !== password)
    return res.status(401).json({ message: "Invalid credentials" });
  res.json({ success: true, token: "fake-jwt", redirect: getDashboardForRole(role), user });
});

// ---------- Socket.IO ----------
io.on("connection", (socket) => {
  console.log("📡 Client connected:", socket.id);

  socket.emit("buses", buses);
  socket.emit("drivers", drivers);
  socket.emit("stops", stops);
  socket.emit("users", users);

  // Admin
  socket.on("addDriver", ({ id, name }) => {
    if (name && !drivers.some((d) => d.name.toLowerCase() === name.toLowerCase())) {
      const newDriver = { id: id || uid("drv"), name };
      drivers.push(newDriver);
      io.emit("drivers", drivers);
      scheduleSave();
    }
  });
  socket.on("deleteDriver", (driverId) => {
    drivers = drivers.filter((d) => d.id !== driverId);
    buses.forEach((b) => (b.driverIds = b.driverIds.filter((dr) => dr !== driverId)));
    io.emit("drivers", drivers);
    io.emit("buses", buses);
    scheduleSave();
  });
  socket.on("addBus", (bus) => {
    if (bus && bus.id && !buses.some((b) => b.id === bus.id)) {
      buses.push({ ...bus, driverIds: bus.driverIds || [], stops: bus.stops || [] });
      io.emit("buses", buses);
      scheduleSave();
    }
  });
  socket.on("editBus", (bus) => {
    const i = buses.findIndex((b) => b.id === bus.id);
    if (i !== -1) {
      buses[i] = { ...buses[i], ...bus };
      io.emit("buses", buses);
      scheduleSave();
    }
  });
  socket.on("deleteBus", (id) => {
    buses = buses.filter((b) => b.id !== id);
    io.emit("buses", buses);
    scheduleSave();
  });
  socket.on("assignDriversToBus", ({ busId, driverIds }) => {
    const bus = buses.find((b) => b.id === busId);
    if (bus) {
      bus.driverIds = driverIds || [];
      io.emit("buses", buses);
      scheduleSave();
    }
  });

  // Stops
  socket.on("addStop", (stop) => {
    if (stop && stop.name && !stops.some((s) => s.name.toLowerCase() === stop.name.toLowerCase())) {
      const newStop = { id: stop.id || uid("stp"), ...stop };
      stops.push(newStop);
      io.emit("stops", stops);
      scheduleSave();
    }
  });
  socket.on("deleteStop", (stopId) => {
    stops = stops.filter((s) => s.id !== stopId);
    io.emit("stops", stops);
    scheduleSave();
  });

  // Route order
  socket.on("updateRouteOrder", (order) => {
    if (Array.isArray(order)) {
      routeOrder = order;
      io.emit("routeOrder", routeOrder);
      scheduleSave();
    }
  });

  // Driver
  socket.on("shareLocation", ({ busId, lat, lng }) => {
    let b = buses.find((x) => x.id === busId);
    if (b) {
      b.lat = lat; b.lng = lng;
    } else {
      buses.push({ id: busId, name: busId, lat, lng, driverIds: [], stops: [] });
    }
    io.emit("locationUpdate", { busId, lat, lng, timestamp: Date.now() });
    io.emit("buses", buses);
    scheduleSave();
  });
  socket.on("stopTrip", (busId) => {
    const b = buses.find((x) => x.id === busId);
    if (b) { b.lat = null; b.lng = null; }
    io.emit("buses", buses);
    io.emit("driverStopped", { busId });
    scheduleSave();
  });

  // Profiles
  socket.on("updateProfile", (u) => {
    const idx = users.findIndex((x) => String(x.id) === String(u.id));
    if (idx === -1) users.push(u);
    else users[idx] = { ...users[idx], ...u };
    saveUsers();
    io.emit("profileUpdated", u);
  });

  socket.on("disconnect", () => {
    console.log("❌ Client disconnected:", socket.id);
  });
});

// ---------- Start ----------
server.listen(PORT, "0.0.0.0", async () => {
  console.log(`🚀 Server running on http://localhost:${PORT}`);
  if (NGROK_AUTHTOKEN) {
    try {
      const url = await ngrok.connect({ addr: PORT, authtoken: NGROK_AUTHTOKEN });
      console.log(`🌍 Public URL: ${url}`);
      qr.generate(url, { small: true });
    } catch (err) {
      console.error("❌ Ngrok failed:", err.message);
    }
  }
});
