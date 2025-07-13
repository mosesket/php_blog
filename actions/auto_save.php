<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to save drafts']);
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
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

// Only auto-save if there's some content
if (empty($title) && empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Nothing to save']);
    exit;
}

// Set a default title for auto-save if empty
if (empty($title)) {
    $title = 'Untitled Draft - ' . date('M j, Y g:i A');
}

try {
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    if ($post_id > 0) {
        // Update existing draft
        $check_query = "SELECT id FROM posts WHERE id = :post_id AND user_id = :user_id AND status = 'draft'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':post_id', $post_id);
        $check_stmt->bindParam(':user_id', $user['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $update_query = "
                UPDATE posts 
                SET title = :title, content = :content, category = :category, tags = :tags, updated_at = NOW()
                WHERE id = :post_id AND user_id = :user_id
            ";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':title', $title);
            $update_stmt->bindParam(':content', $content);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':tags', $tags);
            $update_stmt->bindParam(':post_id', $post_id);
            $update_stmt->bindParam(':user_id', $user['id']);
            
            if ($update_stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Draft updated',
                    'post_id' => $post_id,
                    'action' => 'updated'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update draft']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Draft not found or already published']);
        }
    } else {
        // Check if user already has an auto-saved draft with similar title
        $existing_draft_query = "
            SELECT id FROM posts 
            WHERE user_id = :user_id AND status = 'draft' 
            AND (title LIKE :title_pattern OR title LIKE 'Untitled Draft%')
            ORDER BY updated_at DESC 
            LIMIT 1
        ";
        
        $title_pattern = '%' . substr($title, 0, 20) . '%';
        $existing_stmt = $db->prepare($existing_draft_query);
        $existing_stmt->bindParam(':user_id', $user['id']);
        $existing_stmt->bindParam(':title_pattern', $title_pattern);
        $existing_stmt->execute();
        
        if ($existing_stmt->rowCount() > 0) {
            // Update the most recent similar draft
            $existing_draft = $existing_stmt->fetch(PDO::FETCH_ASSOC);
            $post_id = $existing_draft['id'];
            
            $update_query = "
                UPDATE posts 
                SET title = :title, content = :content, category = :category, tags = :tags, updated_at = NOW()
                WHERE id = :post_id AND user_id = :user_id
            ";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':title', $title);
            $update_stmt->bindParam(':content', $content);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':tags', $tags);
            $update_stmt->bindParam(':post_id', $post_id);
            $update_stmt->bindParam(':user_id', $user['id']);
            
            if ($update_stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Draft updated',
                    'post_id' => $post_id,
                    'action' => 'updated'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update draft']);
            }
        } else {
            // Create new auto-save draft
            $insert_query = "
                INSERT INTO posts (user_id, title, content, category, tags, status, created_at, updated_at) 
                VALUES (:user_id, :title, :content, :category, :tags, 'draft', NOW(), NOW())
            ";
            
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user['id']);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':content', $content);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':tags', $tags);
            
            if ($insert_stmt->execute()) {
                $post_id = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Draft saved',
                    'post_id' => $post_id,
                    'action' => 'created'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save draft']);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Auto-save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>