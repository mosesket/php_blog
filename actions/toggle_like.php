<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to like posts']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    // Check if post exists
    $post_check = "SELECT id FROM posts WHERE id = :post_id AND status = 'published'";
    $post_stmt = $db->prepare($post_check);
    $post_stmt->bindParam(':post_id', $post_id);
    $post_stmt->execute();
    
    if ($post_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Check if user already liked this post
    $like_check = "SELECT id FROM likes WHERE post_id = :post_id AND user_id = :user_id";
    $like_stmt = $db->prepare($like_check);
    $like_stmt->bindParam(':post_id', $post_id);
    $like_stmt->bindParam(':user_id', $user['id']);
    $like_stmt->execute();
    
    if ($like_stmt->rowCount() > 0) {
        // Unlike - remove the like
        $unlike_query = "DELETE FROM likes WHERE post_id = :post_id AND user_id = :user_id";
        $unlike_stmt = $db->prepare($unlike_query);
        $unlike_stmt->bindParam(':post_id', $post_id);
        $unlike_stmt->bindParam(':user_id', $user['id']);
        $unlike_stmt->execute();
        
        $action = 'unliked';
    } else {
        // Like - add new like
        $like_query = "INSERT INTO likes (post_id, user_id) VALUES (:post_id, :user_id)";
        $like_stmt = $db->prepare($like_query);
        $like_stmt->bindParam(':post_id', $post_id);
        $like_stmt->bindParam(':user_id', $user['id']);
        $like_stmt->execute();
        
        $action = 'liked';
    }
    
    // Get updated like count
    $count_query = "SELECT COUNT(*) as like_count FROM likes WHERE post_id = :post_id";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':post_id', $post_id);
    $count_stmt->execute();
    $like_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['like_count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => $like_count,
        'message' => $action === 'liked' ? 'Post liked!' : 'Post unliked!'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>