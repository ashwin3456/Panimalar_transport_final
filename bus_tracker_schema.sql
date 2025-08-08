
-- Create database (optional if already exists)
CREATE DATABASE IF NOT EXISTS bus_tracker;
USE bus_tracker;

-- Table: buses
CREATE TABLE IF NOT EXISTS buses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) UNIQUE NOT NULL,
  name VARCHAR(255) NOT NULL
);

-- Table: drivers
CREATE TABLE IF NOT EXISTS drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL
);

-- Table: bus_drivers (many-to-many relation)
CREATE TABLE IF NOT EXISTS bus_drivers (
  bus_id INT,
  driver_id INT,
  PRIMARY KEY(bus_id, driver_id),
  FOREIGN KEY(bus_id) REFERENCES buses(id) ON DELETE CASCADE,
  FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

-- Table: stops
CREATE TABLE IF NOT EXISTS stops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_id INT,
  name VARCHAR(255),
  lat DECIMAL(10,8),
  lng DECIMAL(11,8),
  seq INT DEFAULT 0,
  FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- Table: bus_locations
CREATE TABLE IF NOT EXISTS bus_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_id INT NOT NULL,
  lat DECIMAL(10,8),
  lng DECIMAL(11,8),
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(bus_id) REFERENCES buses(id) ON DELETE CASCADE
);
