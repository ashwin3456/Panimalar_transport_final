<?php
// public/api/geocode.php
// Server-side proxy to Nominatim to avoid CORS/User-Agent issues from the browser.
// Usage: GET /api/geocode.php?q=search+text&limit=5

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if ($limit <= 0 || $limit > 10) $limit = 5;

if ($q === '') {
  http_response_code(400);
  echo json_encode([ 'error' => 'Missing search query' ]);
  exit();
}

$results = [];
$services_tried = [];

// Service 1: Nominatim (OpenStreetMap)
$services_tried[] = 'Nominatim';
$url = 'https://nominatim.openstreetmap.org/search?format=json'
     . '&q=' . urlencode($q)
     . '&limit=' . $limit;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Accept: application/json',
  'User-Agent: PanimalarBusTracker/1.0'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
  $data = json_decode($response, true);
  if (is_array($data) && !empty($data)) {
    $results = $data;
  }
}

// Service 2: If Nominatim failed, try a simple fallback for Indian locations
if (empty($results)) {
  $services_tried[] = 'Fallback';
  
  // Common Indian city coordinates as fallback
  $indian_cities = [
    'chennai' => ['13.0827', '80.2707'],
    'bangalore' => ['12.9716', '77.5946'],
    'mumbai' => ['19.0760', '72.8777'],
    'delhi' => ['28.7041', '77.1025'],
    'kolkata' => ['22.5726', '88.3639'],
    'hyderabad' => ['17.3850', '78.4867'],
    'coimbatore' => ['11.0168', '76.9558'],
    'madurai' => ['9.9252', '78.1198'],
    'trichy' => ['10.7905', '78.7047'],
    'salem' => ['11.6645', '78.1460']
  ];
  
  $query_lower = strtolower($q);
  $found_city = null;
  
  // Check if query matches any Indian city
  foreach ($indian_cities as $city => $coords) {
    if (strpos($query_lower, $city) !== false) {
      $found_city = $city;
      break;
    }
  }
  
  if ($found_city) {
    $results = [[
      'display_name' => ucwords($found_city) . ', Tamil Nadu, India',
      'lat' => $indian_cities[$found_city][0],
      'lon' => $indian_cities[$found_city][1]
    ]];
  } else {
    // Default to Chennai if no match found
    $results = [[
      'display_name' => $q . ', Chennai, Tamil Nadu, India',
      'lat' => '13.0827',
      'lon' => '80.2707'
    ]];
  }
}

if (empty($results)) {
  http_response_code(404);
  echo json_encode([ 
    'error' => 'Location not found',
    'services_tried' => $services_tried,
    'query' => $q
  ]);
  exit();
}

// Return results in the same format as Nominatim
echo json_encode($results);
?>
