<?php
// public/profile/update.php
// Handles driver profile updates (name, phone, photo). Identifies user by email.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

require __DIR__ . '/../../Backend/db.php';

// Only accept POST multipart/form-data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([ 'message' => 'Method not allowed' ]);
  exit();
}

$name  = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if (!$email) {
  http_response_code(400);
  echo json_encode([ 'message' => 'Email is required' ]);
  exit();
}

// Check that user exists
$check = $conn->prepare('SELECT id, profile_photo FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
  http_response_code(404);
  echo json_encode([ 'message' => 'User not found' ]);
  $check->close();
  exit();
}
$user = $res->fetch_assoc();
$check->close();

$profilePhotoFilename = $user['profile_photo']; // keep old by default

// Handle optional file upload
if (isset($_FILES['photo']) && is_array($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
  $file = $_FILES['photo'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([ 'message' => 'File upload error' ]);
    exit();
  }
  // Validate type
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $allowed = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' ];
  if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode([ 'message' => 'Only JPG, PNG, WEBP images allowed' ]);
    exit();
  }

  // Ensure uploads dir exists
  $uploadsDir = __DIR__ . '/../uploads';
  if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0775, true)) {
      http_response_code(500);
      echo json_encode([ 'message' => 'Failed to create uploads directory' ]);
      exit();
    }
  }
  if (!is_writable($uploadsDir)) {
    http_response_code(500);
    echo json_encode([ 'message' => 'Uploads directory not writable' ]);
    exit();
  }

  // Generate unique filename
  $base = bin2hex(random_bytes(8));
  $ext = $allowed[$mime];
  $filename = $base . '.' . $ext;
  $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode([ 'message' => 'Failed to save uploaded file' ]);
    exit();
  }
  $profilePhotoFilename = $filename;
}

// Update record
$stmt = $conn->prepare('UPDATE users SET name = COALESCE(NULLIF(?, ""), name), phone_number = COALESCE(NULLIF(?, ""), phone_number), profile_photo = ? WHERE email = ?');
$stmt->bind_param('ssss', $name, $phone, $profilePhotoFilename, $email);

if ($stmt->execute()) {
  echo json_encode([
    'message' => 'Profile updated',
    'profile_photo' => $profilePhotoFilename,
  ]);
} else {
  http_response_code(500);
  echo json_encode([ 'message' => 'Failed to update profile', 'error' => $stmt->error ]);
}

$stmt->close();
$conn->close();
