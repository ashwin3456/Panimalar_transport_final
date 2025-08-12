const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const path = require("path");
const fs = require("fs");
const ngrok = require("ngrok");
const qr = require("qrcode-terminal"); // QR Code package
require("dotenv").config();

// ====== FILE PATHS ======
const DATA_FILE = path.join(__dirname, "data.json");
const USERS_FILE = path.join(__dirname, "users.json");
const SAVE_DEBOUNCE_MS = 1000;

// ====== EXPRESS + SOCKET.IO SETUP ======
const app = express();
const server = http.createServer(app);
const io = new Server(server);

app.use(express.static(path.join(__dirname, "public")));

app.use(express.json());

// ====== STATE ======
let buses = [];
let drivers = [];
let users = [];

// ====== LOAD DATA ======
function loadData() {
  try {
    if (fs.existsSync(DATA_FILE)) {
      const raw = fs.readFileSync(DATA_FILE, "utf8");
      const parsed = JSON.parse(raw);
      buses = Array.isArray(parsed.buses) ? parsed.buses : [];
      drivers = Array.isArray(parsed.drivers) ? parsed.drivers : [];
      console.log("✅ Loaded buses & drivers from", DATA_FILE);
    }

    if (fs.existsSync(USERS_FILE)) {
      const rawUsers = fs.readFileSync(USERS_FILE, "utf8");
      const parsedUsers = JSON.parse(rawUsers);
      users = Array.isArray(parsedUsers) ? parsedUsers : [];
      console.log("✅ Loaded users from", USERS_FILE);
    }
  } catch (err) {
    console.error("❌ Error loading files:", err);
  }
}
loadData();

// ====== SAVE FUNCTIONS ======
let saveTimeout = null;
function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(() => {
    const payload = { buses, drivers, savedAt: new Date().toISOString() };
    fs.writeFile(DATA_FILE, JSON.stringify(payload, null, 2), (err) => {
      if (err) console.error("❌ Failed to save bus/driver data:", err);
    });
  }, SAVE_DEBOUNCE_MS);
}

function saveUsers() {
  fs.writeFile(USERS_FILE, JSON.stringify(users, null, 2), (err) => {
    if (err) console.error("❌ Failed to save users:", err);
  });
}

// ====== API ======
app.get("/trip/:busId", (req, res) => {
  const { busId } = req.params;
  const bus = buses.find(b => b.id === busId);
  if (bus && bus.lat != null && bus.lng != null) {
    res.json({ busId: bus.id, lat: bus.lat, lng: bus.lng });
  } else {
    res.json(null);
  }
});

app.get("/debug-users", (req, res) => {
  res.json(users);
});

// ====== AUTH ======
app.post("/signup", (req, res) => {
  const { name, email, password, role } = req.body;
  if (!name || !email || !password || !role) {
    return res.status(400).json({ message: "Missing fields" });
  }
  const exists = users.find(u => u.email === email && u.role === role);
  if (exists) return res.status(409).json({ message: "User already exists" });

  users.push({ name, email, password, role });
  saveUsers();
  console.log("🆕 New user signed up:", email, role);
  res.status(201).json({ message: "User created" });
});

app.post("/login", (req, res) => {
  const { email, password, role } = req.body;
  if (!email || !password || !role) {
    return res.status(400).json({ message: "Missing fields" });
  }
  const user = users.find(u => u.email === email && u.role === role);
  if (!user || user.password !== password) {
    return res.status(401).json({ message: "Invalid credentials" });
  }
  res.json({ token: "fake-jwt-token" });
});

// ====== SOCKET.IO ======
io.on("connection", (socket) => {
  console.log("📡 Client connected:", socket.id);

  socket.emit("buses", buses);
  socket.emit("drivers", drivers);

  socket.on("addDriver", (name) => {
    if (!name) return;
    if (!drivers.some(d => d.toLowerCase() === name.toLowerCase())) {
      drivers.push(name);
      io.emit("drivers", drivers);
      scheduleSave();
      console.log("➕ Driver added:", name);
    }
  });

  socket.on("deleteDriver", (name) => {
    if (!name) return;
    drivers = drivers.filter(d => d !== name);
    io.emit("drivers", drivers);
    scheduleSave();
    console.log("🗑 Driver deleted:", name);
  });

  socket.on("addBus", (bus) => {
    if (!bus || !bus.id) return;
    if (!buses.some(b => b.id.toLowerCase() === bus.id.toLowerCase())) {
      buses.push({ ...bus, lat: null, lng: null });
      io.emit("buses", buses);
      scheduleSave();
      console.log("🚌 Bus added:", bus.id);
    }
  });

  socket.on("editBus", ({ id, name, driver }) => {
    const b = buses.find(b => b.id === id);
    if (b) {
      b.name = name;
      b.driver = driver;
      io.emit("buses", buses);
      scheduleSave();
      console.log("✏ Bus edited:", id);
    }
  });

  socket.on("deleteBus", (id) => {
    if (!id) return;
    buses = buses.filter(b => b.id !== id);
    io.emit("buses", buses);
    scheduleSave();
    console.log("🗑 Bus deleted:", id);
  });

  socket.on("shareLocation", ({ busId, lat, lng }) => {
    if (!busId) return;
    const b = buses.find(b => b.id === busId);
    if (b) {
      b.lat = lat;
      b.lng = lng;
    } else {
      buses.push({ id: busId, name: busId, driver: "Unknown", lat, lng });
    }
    io.emit("locationUpdate", { busId, lat, lng });
    io.emit("buses", buses);
    scheduleSave();
  });

  socket.on("stopTrip", (busId) => {
    const b = buses.find(b => b.id === busId);
    if (b) {
      b.lat = null;
      b.lng = null;
    }
    io.emit("buses", buses);
    scheduleSave();
    console.log(`🛑 Trip stopped for bus ${busId}`);
  });

  socket.on("disconnect", () => {
    console.log("❌ Client disconnected:", socket.id);
  });
});

// ====== START SERVER ======
const PORT = process.env.PORT || 4000;
server.listen(PORT, "0.0.0.0", async () => {
  console.log(`🚀 Server running on http://0.0.0.0:${PORT}`);

  try {
    const url = await ngrok.connect({
      addr: PORT,
      authtoken: process.env.NGROK_AUTHTOKEN,
      subdomain: process.env.NGROK_SUBDOMAIN
    });
    console.log(`🌍 Public URL: ${url}`);
    qr.generate(url, { small: true }); // Display QR code
  } catch (err) {
    console.error("❌ Ngrok failed to start:", err);
  }
});
