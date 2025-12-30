<?php
require_once '../Backend/db.php';
echo '<h2>Debug Driver ID 2</h2>';

// 1. Check user with ID 2
echo '<h3>User ID 2:</h3>';
$userId = 2;
$stmt = $conn->prepare('SELECT id, name, role FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo 'User found: ' . $user['name'] . ' (Role: ' . $user['role'] . ')';
} else {
    echo 'No user found with ID 2';
}
$stmt->close();

// 2. Check drivers table for matching name
echo '<h3>Drivers Table:</h3>';
$drivers = $conn->query('SELECT id, name FROM drivers');
while ($driver = $drivers->fetch_assoc()) {
    echo 'Driver: ' . $driver['id'] . ' - ' . $driver['name'] . '<br>';
}

// 3. Check bus_drivers for driver ID 2
echo '<h3>Bus-Driver Assignments for Driver ID 2:</h3>';
$driverId = 2;
$stmt = $conn->prepare('SELECT bd.bus_id, b.name as bus_name, b.route_no FROM bus_drivers bd LEFT JOIN buses b ON bd.bus_id = b.id WHERE bd.driver_id = ?');
$stmt->bind_param('s', $driverId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo 'Assigned Bus: ' . $row['bus_name'] . ' (ID: ' . $row['bus_id'] . ') - Route No: ' . ($row['route_no'] ?: 'EMPTY') . '<br>';
    }
} else {
    echo 'No buses assigned to driver ID 2';
}
$stmt->close();

// 4. Check schedules
echo '<h3>Total Schedules:</h3>';
$schedules = $conn->query('SELECT COUNT(*) as count FROM bus_schedules');
$count = $schedules->fetch_assoc();
echo 'Total schedules in database: ' . $count['count'];

echo '<h3>Sample Schedules:</h3>';
$samples = $conn->query('SELECT route_name, route_number, schedule_date FROM bus_schedules LIMIT 5');
while ($sample = $samples->fetch_assoc()) {
    echo 'Schedule: ' . $sample['route_name'] . ' - ' . $sample['route_number'] . ' on ' . $sample['schedule_date'] . '<br>';
}

$conn->close();
?>
