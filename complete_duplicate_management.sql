-- ========================================
-- COMPLETE DUPLICATE MANAGEMENT FOR PANIMALAR BUS TRACKER
-- Single file solution - handles everything (FIXED VERSION)
-- ========================================

USE panimalar_bus_tracker;

-- ========================================
-- SECTION 1: CURRENT STATUS CHECK
-- ========================================

SELECT '========================================' as divider;
SELECT 'CHECKING CURRENT DATABASE STATUS...' as status_message;
SELECT '========================================' as divider;

-- Check total records
SELECT 'CURRENT RECORD COUNTS:' as info;
SELECT 
    'Buses' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(bus_name,''), '|', COALESCE(route_number,''))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(bus_name,''), '|', COALESCE(route_number,'')))) as duplicates
FROM buses
UNION ALL
SELECT 
    'Bus Stops' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(stop_name,''), '|', COALESCE(latitude,0), '|', COALESCE(longitude,0))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(stop_name,''), '|', COALESCE(latitude,0), '|', COALESCE(longitude,0)))) as duplicates
FROM bus_stops
UNION ALL
SELECT 
    'Routes' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(bus_id,0), '|', COALESCE(stop_id,0), '|', COALESCE(stop_order,0))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(bus_id,0), '|', COALESCE(stop_id,0), '|', COALESCE(stop_order,0)))) as duplicates
FROM routes;

-- ========================================
-- SECTION 2: IDENTIFY EXACT DUPLICATES
-- ========================================

SELECT '========================================' as divider;
SELECT 'IDENTIFYING DUPLICATE RECORDS...' as status_message;
SELECT '========================================' as divider;

-- Find duplicate buses
SELECT 'DUPLICATE BUSES (same name + route):' as check_type;
SELECT 
    COALESCE(bus_name, 'NULL') as bus_name,
    COALESCE(route_number, 'NULL') as route_number,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as duplicate_ids,
    MIN(created_at) as first_created,
    MAX(created_at) as last_created
FROM buses 
GROUP BY bus_name, route_number 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Find duplicate stops
SELECT 'DUPLICATE STOPS (same name + coordinates):' as check_type;
SELECT 
    COALESCE(stop_name, 'NULL') as stop_name,
    COALESCE(latitude, 0) as latitude,
    COALESCE(longitude, 0) as longitude,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as duplicate_ids,
    MIN(created_at) as first_created,
    MAX(created_at) as last_created
FROM bus_stops 
GROUP BY stop_name, latitude, longitude 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Find duplicate routes
SELECT 'DUPLICATE ROUTES (same bus + stop + order):' as check_type;
SELECT 
    bus_id,
    stop_id,
    stop_order,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as duplicate_ids
FROM routes 
GROUP BY bus_id, stop_id, stop_order 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- ========================================
-- SECTION 3: IDENTIFY DATA QUALITY ISSUES
-- ========================================

SELECT '========================================' as divider;
SELECT 'CHECKING DATA QUALITY ISSUES...' as status_message;
SELECT '========================================' as divider;

-- Buses with missing data
SELECT 'BUSES WITH MISSING DATA:' as issue_type;
SELECT 
    id,
    COALESCE(bus_name, '[MISSING]') as bus_name,
    COALESCE(route_number, '[MISSING]') as route_number,
    status,
    CASE 
        WHEN bus_name IS NULL OR TRIM(bus_name) = '' THEN 'Missing Name'
        WHEN route_number IS NULL OR TRIM(route_number) = '' THEN 'Missing Route'
        ELSE 'OK'
    END as issue
FROM buses 
WHERE (bus_name IS NULL OR TRIM(bus_name) = '') 
   OR (route_number IS NULL OR TRIM(route_number) = '')
ORDER BY id;

-- Stops with missing or invalid data
SELECT 'STOPS WITH MISSING/INVALID DATA:' as issue_type;
SELECT 
    id,
    COALESCE(stop_name, '[MISSING]') as stop_name,
    COALESCE(latitude, 0) as latitude,
    COALESCE(longitude, 0) as longitude,
    CASE 
        WHEN stop_name IS NULL OR TRIM(stop_name) = '' THEN 'Missing Name'
        WHEN latitude IS NULL OR latitude = 0 THEN 'Missing/Invalid Latitude'
        WHEN longitude IS NULL OR longitude = 0 THEN 'Missing/Invalid Longitude'
        WHEN latitude < -90 OR latitude > 90 THEN 'Invalid Latitude Range'
        WHEN longitude < -180 OR longitude > 180 THEN 'Invalid Longitude Range'
        ELSE 'OK'
    END as issue
FROM bus_stops 
WHERE (stop_name IS NULL OR TRIM(stop_name) = '') 
   OR (latitude IS NULL OR latitude = 0)
   OR (longitude IS NULL OR longitude = 0)
   OR (latitude < -90 OR latitude > 90)
   OR (longitude < -180 OR longitude > 180)
ORDER BY id;

-- Orphaned routes
SELECT 'ORPHANED ROUTES (pointing to deleted records):' as issue_type;
SELECT 
    r.id as route_id,
    r.bus_id,
    r.stop_id,
    r.stop_order,
    CASE 
        WHEN b.id IS NULL THEN 'Bus Not Found'
        WHEN s.id IS NULL THEN 'Stop Not Found'
        ELSE 'OK'
    END as issue
FROM routes r
LEFT JOIN buses b ON r.bus_id = b.id
LEFT JOIN bus_stops s ON r.stop_id = s.id
WHERE b.id IS NULL OR s.id IS NULL
ORDER BY r.id;

-- ========================================
-- SECTION 4: SAFE DUPLICATE CLEANUP
-- ========================================

SELECT '========================================' as divider;
SELECT 'STARTING SAFE DUPLICATE CLEANUP...' as status_message;
SELECT '========================================' as divider;

-- Create backup tables first
SELECT 'Creating backup tables...' as action_message;

-- Backup buses
DROP TABLE IF EXISTS buses_backup;
CREATE TABLE buses_backup AS SELECT * FROM buses;
SELECT CONCAT('Backed up ', COUNT(*), ' buses to buses_backup') as result_message FROM buses_backup;

-- Backup bus_stops
DROP TABLE IF EXISTS bus_stops_backup;
CREATE TABLE bus_stops_backup AS SELECT * FROM bus_stops;
SELECT CONCAT('Backed up ', COUNT(*), ' stops to bus_stops_backup') as result_message FROM bus_stops_backup;

-- Backup routes
DROP TABLE IF EXISTS routes_backup;
CREATE TABLE routes_backup AS SELECT * FROM routes;
SELECT CONCAT('Backed up ', COUNT(*), ' routes to routes_backup') as result_message FROM routes_backup;

-- Clean duplicate buses (keep the oldest - lowest ID)
SELECT 'Cleaning duplicate buses...' as action_message;
DELETE b1 FROM buses b1 
INNER JOIN buses b2 
WHERE b1.id > b2.id 
AND COALESCE(b1.bus_name, '') = COALESCE(b2.bus_name, '')
AND COALESCE(b1.route_number, '') = COALESCE(b2.route_number, '');

SELECT CONCAT('Duplicate buses cleaned. Remaining buses: ', COUNT(*)) as result_message FROM buses;

-- Clean duplicate stops (keep the oldest - lowest ID)
SELECT 'Cleaning duplicate stops...' as action_message;
DELETE s1 FROM bus_stops s1 
INNER JOIN bus_stops s2 
WHERE s1.id > s2.id 
AND COALESCE(s1.stop_name, '') = COALESCE(s2.stop_name, '')
AND ABS(COALESCE(s1.latitude, 0) - COALESCE(s2.latitude, 0)) < 0.000001
AND ABS(COALESCE(s1.longitude, 0) - COALESCE(s2.longitude, 0)) < 0.000001;

SELECT CONCAT('Duplicate stops cleaned. Remaining stops: ', COUNT(*)) as result_message FROM bus_stops;

-- Clean duplicate routes (keep the oldest - lowest ID)
SELECT 'Cleaning duplicate routes...' as action_message;
DELETE r1 FROM routes r1 
INNER JOIN routes r2 
WHERE r1.id > r2.id 
AND r1.bus_id = r2.bus_id 
AND r1.stop_id = r2.stop_id 
AND r1.stop_order = r2.stop_order;

SELECT CONCAT('Duplicate routes cleaned. Remaining routes: ', COUNT(*)) as result_message FROM routes;

-- Clean orphaned routes
SELECT 'Cleaning orphaned routes...' as action_message;
DELETE FROM routes 
WHERE bus_id NOT IN (SELECT id FROM buses)
   OR stop_id NOT IN (SELECT id FROM bus_stops);

SELECT CONCAT('Orphaned routes cleaned. Remaining routes: ', COUNT(*)) as result_message FROM routes;

-- ========================================
-- SECTION 5: ADD UNIQUE CONSTRAINTS
-- ========================================

SELECT '========================================' as divider;
SELECT 'ADDING UNIQUE CONSTRAINTS...' as status_message;
SELECT '========================================' as divider;

-- Add unique constraint for buses (simple approach)
SELECT 'Adding unique constraint for buses...' as action_message;

-- Check if constraint already exists
SELECT COUNT(*) INTO @bus_constraint_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'panimalar_bus_tracker' 
AND TABLE_NAME = 'buses' 
AND CONSTRAINT_NAME = 'unique_bus_route';

-- Add constraint if it doesn't exist
SET @sql_add_bus_constraint = IF(@bus_constraint_exists = 0, 
    'ALTER TABLE buses ADD CONSTRAINT unique_bus_route UNIQUE (bus_name, route_number)',
    'SELECT "Bus constraint already exists" as result_message'
);

PREPARE stmt FROM @sql_add_bus_constraint;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique constraint for stops
SELECT 'Adding unique constraint for stops...' as action_message;

-- Check if constraint already exists
SELECT COUNT(*) INTO @stop_constraint_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'panimalar_bus_tracker' 
AND TABLE_NAME = 'bus_stops' 
AND CONSTRAINT_NAME = 'unique_stop_location';

-- Add constraint if it doesn't exist
SET @sql_add_stop_constraint = IF(@stop_constraint_exists = 0, 
    'ALTER TABLE bus_stops ADD CONSTRAINT unique_stop_location UNIQUE (stop_name, latitude, longitude)',
    'SELECT "Stop constraint already exists" as result_message'
);

PREPARE stmt FROM @sql_add_stop_constraint;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- SECTION 6: FINAL VERIFICATION
-- ========================================

SELECT '========================================' as divider;
SELECT 'FINAL VERIFICATION AND SUMMARY...' as status_message;
SELECT '========================================' as divider;

-- Final count check
SELECT 'FINAL RECORD COUNTS:' as info;
SELECT 
    'Buses' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(bus_name,''), '|', COALESCE(route_number,''))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(bus_name,''), '|', COALESCE(route_number,'')))) as remaining_duplicates
FROM buses
UNION ALL
SELECT 
    'Bus Stops' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(stop_name,''), '|', COALESCE(latitude,0), '|', COALESCE(longitude,0))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(stop_name,''), '|', COALESCE(latitude,0), '|', COALESCE(longitude,0)))) as remaining_duplicates
FROM bus_stops
UNION ALL
SELECT 
    'Routes' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT CONCAT(COALESCE(bus_id,0), '|', COALESCE(stop_id,0), '|', COALESCE(stop_order,0))) as unique_records,
    (COUNT(*) - COUNT(DISTINCT CONCAT(COALESCE(bus_id,0), '|', COALESCE(stop_id,0), '|', COALESCE(stop_order,0)))) as remaining_duplicates
FROM routes;

-- Check constraints
SELECT 'CONSTRAINT STATUS:' as info;
SELECT 
    CONSTRAINT_NAME as constraint_name,
    TABLE_NAME as table_name,
    CONSTRAINT_TYPE as constraint_type
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'panimalar_bus_tracker' 
AND CONSTRAINT_TYPE = 'UNIQUE'
AND TABLE_NAME IN ('buses', 'bus_stops', 'routes')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Success message
SELECT '========================================' as divider;
SELECT 'DUPLICATE MANAGEMENT COMPLETED SUCCESSFULLY!' as status_message;
SELECT '========================================' as divider;

SELECT 'WHAT WAS DONE:' as summary_info;
SELECT '1. Created backup tables (buses_backup, bus_stops_backup, routes_backup)' as action_info
UNION ALL SELECT '2. Removed duplicate buses (kept oldest records)' as action_info
UNION ALL SELECT '3. Removed duplicate stops (kept oldest records)' as action_info
UNION ALL SELECT '4. Removed duplicate routes (kept oldest records)' as action_info
UNION ALL SELECT '5. Cleaned orphaned routes (pointing to deleted records)' as action_info
UNION ALL SELECT '6. Added unique constraints to prevent future duplicates' as action_info
UNION ALL SELECT '7. Verified final data integrity' as action_info;

SELECT 'BACKUP TABLES CREATED:' as backup_info;
SELECT 'buses_backup - Contains all original bus data' as backup_detail
UNION ALL SELECT 'bus_stops_backup - Contains all original stop data' as backup_detail
UNION ALL SELECT 'routes_backup - Contains all original route data' as backup_detail;

SELECT 'YOUR DATA IS NOW CLEAN AND DUPLICATE-FREE!' as final_message;
