<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if (!$post_id || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
    exit;
}

if (strlen($content) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Comment is too long (max 1000 characters)']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    // Check if post exists and is published
    $post_check = "SELECT id, title FROM posts WHERE id = :post_id AND status = 'published'";
    $post_stmt = $db->prepare($post_check);
    $post_stmt->bindParam(':post_id', $post_id);
    $post_stmt->execute();
    
    if ($post_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Post not found or not published']);
        exit;
    }
    
    $post = $post_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Insert comment
    $insert_query = "INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':post_id', $post_id);
    $insert_stmt->bindParam(':user_id', $user['id']);
    $insert_stmt->bindParam(':content', $content);
    
    if ($insert_stmt->execute()) {
        $comment_id = $db->lastInsertId();
        
        // Return the new comment data
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully!',
            'comment' => [
                'id' => $comment_id,
                'content' => htmlspecialchars($content),
                'username' => htmlspecialchars($user['username']),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>