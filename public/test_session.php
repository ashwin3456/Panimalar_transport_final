<?php
session_start();

echo "<h2>Session Test</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session data: ";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>User is logged in with ID: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color: red;'>No user session found</p>";
}

echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='driver_dashboard.html'>Go to Dashboard</a></p>";
?>
