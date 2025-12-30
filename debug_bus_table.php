<?php
require_once 'Backend/db.php';

echo "=== DEBUGGING BUS TABLE ===\n";

// 1. Check if columns exist
echo "1. Checking table structure:\n";
$result = $conn->query('DESCRIBE buses');
$hasBoardingName = false;
$hasEndName = false;

while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    if ($row['Field'] === 'boarding_stop_name') $hasBoardingName = true;
    if ($row['Field'] === 'end_stop_name') $hasEndName = true;
}

echo "\n2. Column status:\n";
echo "- boarding_stop_name: " . ($hasBoardingName ? "EXISTS" : "MISSING") . "\n";
echo "- end_stop_name: " . ($hasEndName ? "EXISTS" : "MISSING") . "\n";

// 2. Add columns if missing
if (!$hasBoardingName) {
    echo "\n3. Adding boarding_stop_name column...\n";
    $conn->query("ALTER TABLE buses ADD COLUMN boarding_stop_name VARCHAR(120) NULL");
    echo "Added boarding_stop_name\n";
}

if (!$hasEndName) {
    echo "\n4. Adding end_stop_name column...\n";
    $conn->query("ALTER TABLE buses ADD COLUMN end_stop_name VARCHAR(120) NULL");
    echo "Added end_stop_name\n";
}

// 3. Show current data
echo "\n5. Current bus data:\n";
$result = $conn->query("SELECT id, name, boarding_stop_name, end_stop_name FROM buses LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . ", Name: " . $row['name'] . 
             ", Boarding: " . ($row['boarding_stop_name'] ?? 'NULL') . 
             ", End: " . ($row['end_stop_name'] ?? 'NULL') . "\n";
    }
} else {
    echo "No buses found\n";
}

$conn->close();
echo "\n=== DONE ===\n";
?>
