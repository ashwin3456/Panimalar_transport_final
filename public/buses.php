<?php
// public/buses.php
// Returns an array of buses with stops in a generic format compatible with driver dashboard.
// Tries public/buses.json (saved by /api/data). If missing, falls back to MySQL tables.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

$publicDir = __DIR__;
$jsonPath = $publicDir . DIRECTORY_SEPARATOR . 'buses.json';

function output_generic_from_saved(array $saved) {
  $buses = $saved['buses'] ?? [];
  $stops = $saved['stops'] ?? [];
  $stopsById = [];
  foreach ($stops as $s) {
    if (!isset($s['id'])) continue;
    $stopsById[$s['id']] = $s;
  }
  $out = [];
  foreach ($buses as $b) {
    $stopsArr = [];
    foreach (($b['stops'] ?? []) as $sid) {
      if (isset($stopsById[$sid])) {
        $ss = $stopsById[$sid];
        $stopsArr[] = [
          'latitude' => isset($ss['lat']) ? (float)$ss['lat'] : null,
          'longitude'=> isset($ss['lon']) ? (float)$ss['lon'] : null,
          'stop_name'=> $ss['name'] ?? ''
        ];
      }
    }
    if (!empty($stopsArr)) {
      $out[] = [
        'id'         => $b['id'] ?? null,
        'bus_number' => $b['routeNo'] ?? '',
        'route_name' => $b['name'] ?? 'Bus',
        'stops'      => $stopsArr,
      ];
    }
  }
  echo json_encode($out);
  exit();
}

if (file_exists($jsonPath)) {
  $raw = file_get_contents($jsonPath);
  $data = json_decode($raw, true);
  if (is_array($data)) {
    output_generic_from_saved($data);
  }
}

// Fallback to DB schema
require __DIR__ . '/../Backend/db.php';

$sql = "SELECT b.id as bus_id, b.bus_name, b.route_number,
               r.stop_order, s.stop_name, s.latitude, s.longitude
        FROM buses b
        LEFT JOIN routes r ON r.bus_id = b.id
        LEFT JOIN bus_stops s ON s.id = r.stop_id
        WHERE b.status = 'active'
        ORDER BY b.id ASC, r.stop_order ASC";

$res = $conn->query($sql);
$buses = [];
while ($row = $res->fetch_assoc()) {
  $bid = $row['bus_id'];
  if (!isset($buses[$bid])) {
    $buses[$bid] = [
      'id' => (int)$bid,
      'bus_number' => $row['route_number'],
      'route_name' => $row['bus_name'],
      'stops' => []
    ];
  }
  if (!is_null($row['stop_order'])) {
    $buses[$bid]['stops'][] = [
      'latitude' => (float)$row['latitude'],
      'longitude'=> (float)$row['longitude'],
      'stop_name'=> $row['stop_name']
    ];
  }
}

echo json_encode(array_values($buses));
