const socket = io();

function sendLocation(bus_id, lat, lng) {
  socket.emit("busLocation", { bus_id, lat, lng });
}

socket.on("locationUpdate", ({ bus_id, lat, lng }) => {
  updateBusOnMap(bus_id, lat, lng); // This function should move the bus icon
});

function sendMessage(msg, sender) {
  socket.emit("chatMessage", { sender, msg });
}

socket.on("chatMessage", ({ sender, msg }) => {
  displayChatMessage(sender, msg); // Add this message to chat UI
});
