-- Add missing location columns to buses table
USE panimalar_bus_tracker;

-- Add location tracking columns to buses table if they don't exist
ALTER TABLE buses 
ADD COLUMN current_lat DECIMAL(10, 8) DEFAULT 0,
ADD COLUMN current_lon DECIMAL(11, 8) DEFAULT 0,
ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Show the updated table structure
DESCRIBE buses;
