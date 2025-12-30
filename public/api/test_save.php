<?php
// Test save functionality
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Test data
$testData = [
    'buses' => [
        [
            'id' => 'bus_test_001',
            'name' => 'Test Bus 1',
            'routeNo' => 'R001',
            'color' => '#FF0000',
            'driverIds' => ['drv_test_001'],
            'stops' => ['stop_test_001', 'stop_test_002'],
            'boardingId' => 'stop_test_001',
            'endId' => 'stop_test_002'
        ]
    ],
    'stops' => [
        [
            'id' => 'stop_test_001',
            'name' => 'Test Stop A',
            'lat' => 13.0827,
            'lon' => 80.2707
        ],
        [
            'id' => 'stop_test_002',
            'name' => 'Test Stop B',
            'lat' => 13.0837,
            'lon' => 80.2717
        ]
    ],
    'drivers' => [
        [
            'id' => 'drv_test_001',
            'name' => 'Test Driver 1'
        ]
    ],
    'routeOrder' => ['stop_test_001', 'stop_test_002']
];

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'save_buses';

// Capture output
ob_start();
include __DIR__ . '/realtime.php';
$output = ob_get_clean();

echo $output;
?>
