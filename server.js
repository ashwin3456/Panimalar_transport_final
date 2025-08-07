// server.js
const express = require("express");
const http = require("http");
const path = require("path");
const mysql = require("mysql2");
const { Server } = require("socket.io");

const app = express();
const server = http.createServer(app);
const io = new Server(server);

const db = mysql.createConnection({
  host: "localhost",
  user: "root",
  password: "", // change if needed
  database: "bus_tracker"
});

db.connect(err => {
  if (err) throw err;
  console.log("✅ MySQL Connected.");
});

app.use(express.static(path.join(__dirname, "public")));

// GET all buses with latest location
app.get("/get-buses", (req, res) => {
  db.query(
    `SELECT b.id, b.name, l.lat, l.lng FROM buses b
     LEFT JOIN bus_locations l ON b.id = l.bus_id`,
    (err, result) => {
      if (err) return res.status(500).send("Error fetching buses");
      res.json(result);
    }
  );
});

io.on("connection", socket => {
  console.log("🟢 New client connected");

  socket.on("locationUpdate", data => {
    const { bus_id, lat, lng } = data;

    // Save to DB
    db.query(
      "REPLACE INTO bus_locations (bus_id, lat, lng) VALUES (?, ?, ?)",
      [bus_id, lat, lng],
      err => {
        if (err) console.error("DB Error:", err);
      }
    );

    // Broadcast to all users
    io.emit("busLocationUpdate", { bus_id, lat, lng });
  });

  socket.on("disconnect", () => {
    console.log("🔴 Client disconnected");
  });
});

server.listen(3000, () => {
  console.log("🚀 Server running on http://localhost:3000");
});
