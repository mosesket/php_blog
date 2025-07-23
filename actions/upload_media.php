<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to upload files']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$user = getCurrentUser();

// File validation
$max_size = 50 * 1024 * 1024; // 50MB
$allowed_types = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'
];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 50MB']);
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images and videos are allowed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/';
$yearly_dir = $upload_dir . date('Y') . '/';
$monthly_dir = $yearly_dir . date('m') . '/';

if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_dir($yearly_dir)) mkdir($yearly_dir, 0755, true);
if (!is_dir($monthly_dir)) mkdir($monthly_dir, 0755, true);

// Generate unique filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = uniqid() . '_' . time() . '.' . $file_extension;
$file_path = $monthly_dir . $filename;
$relative_path = 'uploads/' . date('Y') . '/' . date('m') . '/' . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $file_path)) {
    try {
        // Save file info to database
        $database = new Database();
        $db = $database->connect();
        
        $file_type = strpos($file['type'], 'image/') === 0 ? 'image' : 'video';
        
        $insert_query = "
            INSERT INTO media_uploads (user_id, filename, original_name, file_path, file_type, file_size, mime_type, created_at) 
            VALUES (:user_id, :filename, :original_name, :file_path, :file_type, :file_size, :mime_type, NOW())
        ";
        
        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':original_name', $file['name']);
        $stmt->bindParam(':file_path', $relative_path);
        $stmt->bindParam(':file_type', $file_type);
        $stmt->bindParam(':file_size', $file['size']);
        $stmt->bindParam(':mime_type', $file['type']);
        
        if ($stmt->execute()) {
            $media_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'media_id' => $media_id,
                'file_path' => $relative_path,
                'file_type' => $file_type,
                'filename' => $filename,
                'original_name' => $file['name']
            ]);
        } else {
            // Delete uploaded file if database insert fails
            unlink($file_path);
            echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
        }
        
    } catch (Exception $e) {
        // Delete uploaded file if error occurs
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
}
?>