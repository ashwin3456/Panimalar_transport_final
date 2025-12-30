-- Create comprehensive bus view that shows all data in one table
USE panimalar_bus_tracker;

-- First, add missing columns to buses table if they don't exist
ALTER TABLE buses 
ADD COLUMN IF NOT EXISTS current_lat DECIMAL(10, 8) DEFAULT 0,
ADD COLUMN IF NOT EXISTS current_lon DECIMAL(11, 8) DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS boarding_stop_name VARCHAR(120),
ADD COLUMN IF NOT EXISTS end_stop_name VARCHAR(120);

-- Create a comprehensive view that shows all bus information in one table
CREATE OR REPLACE VIEW comprehensive_bus_view AS
SELECT 
    b.id as bus_id,
    b.bus_name,
    b.route_number,
    b.status,
    b.current_lat,
    b.current_lon,
    b.last_updated,
    
    -- Driver information
    COALESCE(u.name, 'No Driver Assigned') as driver_name,
    COALESCE(u.phone_number, '-') as driver_phone,
    
    -- Boarding stop information (use name directly)
    COALESCE(b.boarding_stop_name, 'Not Set') as boarding_point,
    0 as boarding_lat,
    0 as boarding_lon,
    
    -- End stop information (use name directly)
    COALESCE(b.end_stop_name, 'Not Set') as end_point,
    0 as end_lat,
    0 as end_lon,
    
    -- Route stops (concatenated)
    GROUP_CONCAT(
        CONCAT(r.stop_order, '. ', bs.stop_name) 
        ORDER BY r.stop_order 
        SEPARATOR ' → '
    ) as complete_route,
    
    -- Count of stops
    COUNT(DISTINCT r.stop_id) as total_stops,
    
    b.created_at
    
FROM buses b
LEFT JOIN users u ON b.driver_id = u.id
LEFT JOIN routes r ON b.id = r.bus_id
LEFT JOIN bus_stops bs ON r.stop_id = bs.id
GROUP BY b.id, b.bus_name, b.route_number, b.status, b.current_lat, b.current_lon, 
         b.last_updated, u.name, u.phone_number, b.boarding_stop_name, 
         b.end_stop_name, b.created_at
ORDER BY b.id;

-- Show the comprehensive view
SELECT * FROM comprehensive_bus_view;
