-- Create the database
CREATE DATABASE IF NOT EXISTS panimalar_bus_tracker1;
USE panimalar_bus_tracker;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Driver', 'Faculty', 'Student') NOT NULL,
    roll_number VARCHAR(50) NULL,
    phone_number VARCHAR(20) NULL,
    admin_number VARCHAR(50) NULL,
    faculty_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Add constraints based on role
    CONSTRAINT chk_role_fields CHECK (
        (role = 'Student' AND roll_number IS NOT NULL) OR
        (role = 'Admin' AND admin_number IS NOT NULL) OR
        (role = 'Faculty' AND faculty_id IS NOT NULL) OR
        (role = 'Driver' AND phone_number IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes for faster lookups
DROP INDEX IF EXISTS idx_email ON users;
DROP INDEX IF EXISTS idx_role ON users;
CREATE INDEX idx_role ON users(role);

-- Note: All admin accounts should be created through the registration process
-- with proper validation and password hashing in the application code.

-- Session management table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log for user actions
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin Dashboard tables

-- Global list of stops
CREATE TABLE IF NOT EXISTS stops (
    id VARCHAR(40) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lon DECIMAL(10,7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_stops_name ON stops(name);
CREATE INDEX idx_stops_lat_lon ON stops(lat, lon);

-- Drivers
CREATE TABLE IF NOT EXISTS drivers (
    id VARCHAR(40) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_drivers_name ON drivers(name);

-- Buses
CREATE TABLE IF NOT EXISTS buses (
    id VARCHAR(40) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    route_no VARCHAR(60) NULL,
    color CHAR(7) NULL,
    boarding_stop_name VARCHAR(120) NULL,
    end_stop_name VARCHAR(120) NULL,
    stops TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_buses_route_no ON buses(route_no);

-- Bus to Drivers (many-to-many)
CREATE TABLE IF NOT EXISTS bus_drivers (
    bus_id VARCHAR(40) NOT NULL,
    driver_id VARCHAR(40) NOT NULL,
    PRIMARY KEY (bus_id, driver_id),
    CONSTRAINT fk_bus_drivers_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    CONSTRAINT fk_bus_drivers_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_bus_drivers_driver ON bus_drivers(driver_id);

-- Bus route stops with explicit order
CREATE TABLE IF NOT EXISTS bus_stops (
    bus_id VARCHAR(40) NOT NULL,
    stop_id VARCHAR(40) NOT NULL,
    position INT NOT NULL,
    PRIMARY KEY (bus_id, stop_id),
    UNIQUE KEY uniq_bus_position (bus_id, position),
    CONSTRAINT fk_bus_stops_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    CONSTRAINT fk_bus_stops_stop FOREIGN KEY (stop_id) REFERENCES stops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_bus_stops_stop ON bus_stops(stop_id);

-- Optional: Persist the temporary 'Route Order' panel if desired
CREATE TABLE IF NOT EXISTS global_route_order (
    position INT PRIMARY KEY,
    stop_id VARCHAR(40) NOT NULL,
    CONSTRAINT fk_global_order_stop FOREIGN KEY (stop_id) REFERENCES stops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Schedule Management tables for admin panel

-- Bus schedules with date-wise timing
CREATE TABLE IF NOT EXISTS bus_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) UNIQUE,  -- Admin panel ID for syncing
    schedule_date DATE NOT NULL,
    route_type ENUM('Morning To College', 'Return routes') NOT NULL,
    time_slot VARCHAR(10) NOT NULL,  -- e.g., '8:30', '9:00'
    route_category ENUM('All Routes', 'Main Routes', 'Common Routes') NOT NULL,
    bus_count INT NOT NULL DEFAULT 1,
    route_name VARCHAR(100) NULL,    -- e.g., 'anna nagar'
    route_number VARCHAR(20) NULL,   -- e.g., '90'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_schedule_date (schedule_date),
    INDEX idx_route_type (route_type),
    INDEX idx_time_slot (time_slot),
    INDEX idx_route_category (route_category),
    INDEX idx_route_name (route_name),
    INDEX idx_route_number (route_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Announcements for system-wide notices
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50) UNIQUE,  -- Admin panel ID for syncing
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    affected_routes TEXT,  -- JSON array of bus IDs that are affected
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_date_range (start_date, end_date),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO announcements (external_id, title, message, start_date, end_date, affected_routes) VALUES
('announcement_1', 'Metro Work', 'Due to metro construction work, buses will skip certain boarding points. Please check alternative routes.', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), '[]'),
('announcement_2', 'Holiday Schedule', 'Modified bus timings for the upcoming holiday. All routes will operate with reduced frequency.', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), '[]')
ON DUPLICATE KEY UPDATE external_id=VALUES(external_id);