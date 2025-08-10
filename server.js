const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const path = require("path");
const fs = require("fs");

const DATA_FILE = path.join(__dirname, "data.json");
const SAVE_DEBOUNCE_MS = 1000;

const app = express();
const server = http.createServer(app);
const io = new Server(server);

app.use(express.static(path.join(__dirname, "public")));
app.use(express.json());

// In-memory state
let buses = [];   // { id, name, driver, lat, lng }
let drivers = []; // [ "Driver Name", ... ]

// Simple in-memory user store (for demo only)
const users = []; // { name, email, password, role }

// Load from disk if exists
function loadData() {
  try {
    if (fs.existsSync(DATA_FILE)) {
      const raw = fs.readFileSync(DATA_FILE, "utf8");
      const parsed = JSON.parse(raw);
      buses = Array.isArray(parsed.buses) ? parsed.buses : [];
      drivers = Array.isArray(parsed.drivers) ? parsed.drivers : [];
      console.log("Loaded data from", DATA_FILE);
    } else {
      console.log("No data file found, starting fresh.");
    }
  } catch (err) {
    console.error("Failed to load data file:", err);
  }
}

// Debounced save
let saveTimeout = null;
function scheduleSave() {
  if (saveTimeout) clearTimeout(saveTimeout);
  saveTimeout = setTimeout(() => {
    const payload = { buses, drivers, savedAt: new Date().toISOString() };
    fs.writeFile(DATA_FILE, JSON.stringify(payload, null, 2), (err) => {
      if (err) return console.error("Failed to save data:", err);
    });
  }, SAVE_DEBOUNCE_MS);
}

// Helper: find bus by id (case-sensitive ID)
function findBus(id) {
  return buses.find(b => b.id === id);
}

loadData();

// API endpoint to get last trip for a given busId
app.get("/trip/:busId", (req, res) => {
  const { busId } = req.params;
  const bus = findBus(busId);
  if (bus && bus.lat != null && bus.lng != null) {
    res.json({ busId: bus.id, lat: bus.lat, lng: bus.lng });
  } else {
    res.json(null);
  }
});

// ======== AUTH ENDPOINTS ========

// POST /signup
app.post('/signup', (req, res) => {
  const { name, email, password, role } = req.body;
  if (!name || !email || !password || !role) {
    return res.status(400).json({ message: 'Missing fields' });
  }
  const exists = users.find(u => u.email === email && u.role === role);
  if (exists) return res.status(409).json({ message: 'User already exists' });

  // Save user (WARNING: no password hashing here - demo only)
  users.push({ name, email, password, role });
  console.log('New user signed up:', email, role);
  return res.status(201).json({ message: 'User created' });
});

// POST /login
app.post('/login', (req, res) => {
  const { email, password, role } = req.body;
  if (!email || !password || !role) {
    return res.status(400).json({ message: 'Missing fields' });
  }
  const user = users.find(u => u.email === email && u.role === role);
  if (!user || user.password !== password) {
    return res.status(401).json({ message: 'Invalid credentials' });
  }

  // In real app, create and return JWT or session token here
  return res.json({ token: "fake-jwt-token" });
});

// ======== SOCKET.IO ========
io.on("connection", (socket) => {
  console.log("Client connected:", socket.id);

  // send initial state
  socket.emit("buses", buses);
  socket.emit("drivers", drivers);

  // ADD DRIVER
  socket.on("addDriver", (name) => {
    if (!name) return;
    if (!drivers.some(d => d.toLowerCase() === name.toLowerCase())) {
      drivers.push(name);
      io.emit("drivers", drivers);
      scheduleSave();
      console.log("Driver added:", name);
    }
  });

  // DELETE DRIVER
  socket.on("deleteDriver", (name) => {
    if (!name) return;
    drivers = drivers.filter(d => d !== name);
    io.emit("drivers", drivers);
    scheduleSave();
    console.log("Driver deleted:", name);
  });

  // ADD BUS
  socket.on("addBus", (bus) => {
    if (!bus || !bus.id) return;
    if (!buses.some(b => b.id.toLowerCase() === bus.id.toLowerCase())) {
      buses.push({ ...bus, lat: null, lng: null });
      io.emit("buses", buses);
      scheduleSave();
      console.log("Bus added:", bus.id);
    }
  });

  // EDIT BUS
  socket.on("editBus", ({ id, name, driver }) => {
    const b = findBus(id);
    if (b) {
      b.name = name;
      b.driver = driver;
      io.emit("buses", buses);
      scheduleSave();
      console.log("Bus edited:", id);
    }
  });

  // DELETE BUS
  socket.on("deleteBus", (id) => {
    if (!id) return;
    buses = buses.filter(b => b.id !== id);
    io.emit("buses", buses);
    scheduleSave();
    console.log("Bus deleted:", id);
  });

  // DRIVER SHARES LOCATION
  socket.on("shareLocation", ({ busId, lat, lng }) => {
    if (!busId) return;
    const b = findBus(busId);
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

  // DRIVER STOPS TRIP
  socket.on("stopTrip", (busId) => {
    const b = findBus(busId);
    if (b) {
      b.lat = null;
      b.lng = null;
    }
    io.emit("buses", buses);
    scheduleSave();
    console.log(`Trip stopped for bus ${busId}`);
  });

  socket.on("disconnect", () => {
    console.log("Client disconnected:", socket.id);
  });
});

const PORT = process.env.PORT || 4000;
server.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
