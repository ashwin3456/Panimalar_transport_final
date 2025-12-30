-- Real-time Bus Tracker Database Schema
CREATE DATABASE IF NOT EXISTS panimalar_bus_tracker;
USE panimalar_bus_tracker;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Driver','Faculty','Student') NOT NULL,
    roll_number VARCHAR(50),
    phone_number VARCHAR(20),
    admin_number VARCHAR(50),
    faculty_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buses table with real-time location support
CREATE TABLE IF NOT EXISTS buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_name VARCHAR(100) NOT NULL,
    route_number VARCHAR(50) NOT NULL,
    driver_id INT,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    current_lat DECIMAL(10, 8) DEFAULT 0,
    current_lon DECIMAL(11, 8) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_bus_route (bus_name, route_number)
);

-- Bus stops table
CREATE TABLE IF NOT EXISTS bus_stops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stop_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stop_location (stop_name, latitude, longitude)
);

-- Routes table (connecting buses with stops)
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    stop_id INT NOT NULL,
    stop_order INT NOT NULL,
    estimated_time TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (stop_id) REFERENCES bus_stops(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bus_stop_order (bus_id, stop_order)
);

-- Bus locations history table (for tracking history)
CREATE TABLE IF NOT EXISTS bus_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    speed DECIMAL(5, 2) DEFAULT 0,
    heading INT DEFAULT 0,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    INDEX idx_bus_timestamp (bus_id, timestamp)
);

-- Student bus assignments
CREATE TABLE IF NOT EXISTS student_bus_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    bus_id INT NOT NULL,
    pickup_stop_id INT NOT NULL,
    drop_stop_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (pickup_stop_id) REFERENCES bus_stops(id) ON DELETE CASCADE,
    FOREIGN KEY (drop_stop_id) REFERENCES bus_stops(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    bus_id INT,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'alert') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- Add columns to existing buses table if they don't exist
ALTER TABLE buses 
ADD COLUMN IF NOT EXISTS current_lat DECIMAL(10, 8) DEFAULT 0,
ADD COLUMN IF NOT EXISTS current_lon DECIMAL(11, 8) DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Insert sample admin user (password: admin123)
INSERT IGNORE INTO users (name, email, password, role) 
VALUES ('Admin User', 'admin@panimalar.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');

-- Sample data for testing
INSERT IGNORE INTO bus_stops (stop_name, latitude, longitude) VALUES
('Panimalar College Gate', 13.1027, 80.2097),
('Anna Nagar', 13.0850, 80.2101),
('Kilpauk', 13.0827, 80.2707),
('Egmore', 13.0732, 80.2609),
('Central Station', 13.0827, 80.2707);

INSERT IGNORE INTO buses (bus_name, route_number, status) VALUES
('College Express 1', 'CE-01', 'active'),
('College Express 2', 'CE-02', 'active'),
('Metro Connect', 'MC-01', 'active');
