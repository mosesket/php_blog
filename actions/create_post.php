<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to create posts']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$category = isset($_POST['category']) ? sanitize($_POST['category']) : 'General';
$tags = isset($_POST['tags']) ? sanitize($_POST['tags']) : '';
$status = isset($_POST['status']) ? sanitize($_POST['status']) : 'published';

// Validation
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Post title is required']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Post content is required']);
    exit;
}

if (strlen($title) > 200) {
    echo json_encode(['success' => false, 'message' => 'Title is too long (max 200 characters)']);
    exit;
}

if (strlen($content) > 10000) {
    echo json_encode(['success' => false, 'message' => 'Content is too long (max 10,000 characters)']);
    exit;
}

// Validate status
if (!in_array($status, ['draft', 'published'])) {
    $status = 'published';
}

try {
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    // Check if user exists and is active
    $user_check = "SELECT id, is_active FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_check);
    $user_stmt->bindParam(':user_id', $user['id']);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'User account not found']);
        exit;
    }
    
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Your account is not active']);
        exit;
    }
    
    // Insert the post
    $insert_query = "
        INSERT INTO posts (user_id, title, content, category, tags, status, created_at, updated_at) 
        VALUES (:user_id, :title, :content, :category, :tags, :status, NOW(), NOW())
    ";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':user_id', $user['id']);
    $insert_stmt->bindParam(':title', $title);
    $insert_stmt->bindParam(':content', $content);
    $insert_stmt->bindParam(':category', $category);
    $insert_stmt->bindParam(':tags', $tags);
    $insert_stmt->bindParam(':status', $status);
    
    if ($insert_stmt->execute()) {
        $post_id = $db->lastInsertId();
        
        // Return success response
        $response = [
            'success' => true,
            'post_id' => $post_id,
            'message' => $status === 'published' ? 'Post published successfully!' : 'Post saved as draft!'
        ];
        
        // Set redirect URL based on status
        if ($status === 'published') {
            $response['redirect'] = "post.php?id=" . $post_id;
        } else {
            $response['redirect'] = "my_posts.php";
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create post. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Create post error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>