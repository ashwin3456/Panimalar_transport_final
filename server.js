// server.js
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const path = require("path");

const app = express();
const server = http.createServer(app);
const io = new Server(server);

app.use(express.static(path.join(__dirname, "public")));
app.use(express.json());

// Data store
let buses = [];        // Array of buses from server
let selectedBusId = ""; // Set when student searches

let drivers = [];

// SOCKET CONNECTION
io.on("connection", (socket) => {
    console.log("Client connected:", socket.id);

    // Send initial data to the newly connected client
    socket.emit("buses", buses);
    socket.emit("drivers", drivers);

    // ADD DRIVER
    socket.on("addDriver", (name) => {
        if (!drivers.some(d => d.toLowerCase() === name.toLowerCase())) {
            drivers.push(name);
            io.emit("drivers", drivers);
        }
    });

    // DELETE DRIVER
    socket.on("deleteDriver", (name) => {
        drivers = drivers.filter(d => d !== name);
        io.emit("drivers", drivers); // update everyone
    });

    // ADD BUS
    socket.on("addBus", (bus) => {
        if (!buses.some(b => b.id.toLowerCase() === bus.id.toLowerCase())) {
            buses.push({ ...bus, lat: null, lng: null });
            io.emit("buses", buses);
        }
    });

    // EDIT BUS
    socket.on("editBus", ({ id, name, driver }) => {
        const bus = buses.find(b => b.id === id);
        if (bus) {
            bus.name = name;
            bus.driver = driver;
            io.emit("buses", buses);
        }
    });

    // DELETE BUS
    socket.on("deleteBus", (id) => {
        buses = buses.filter(b => b.id !== id);
        io.emit("buses", buses); // send updated list to all
    });

    // DRIVER SHARES LOCATION
    socket.on("shareLocation", ({ busId, lat, lng }) => {
  const bus = buses.find(b => b.id === busId);
  if (bus) {
    bus.lat = lat;
    bus.lng = lng;
    io.emit("locationUpdate", { busId, lat, lng }); // notifies all clients
  }
});


    // DISCONNECT
    socket.on("disconnect", () => {
        console.log("Client disconnected:", socket.id);
    });
});

server.listen(4000, () => {
    console.log("Server running on http://localhost:4000");
});
