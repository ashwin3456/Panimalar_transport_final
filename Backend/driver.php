<?php
include 'db.php';
header('Content-Type: application/json');

$sql = "SELECT * FROM buses WHERE FIND_IN_SET('driver_id_here', driverIds)";
$result = $conn->query($sql);

$buses = [];
while($row = $result->fetch_assoc()) {
    $buses[] = $row;
}
echo json_encode(["buses" => $buses]);
?>
