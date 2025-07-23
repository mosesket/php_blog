<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Search in posts with media information
    $search_query = "
        SELECT p.id, p.title, p.content, p.created_at, p.has_media, u.username as author,
               COUNT(DISTINCT l.id) as like_count,
               COUNT(DISTINCT c.id) as comment_count
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN likes l ON p.id = l.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        WHERE p.status = 'published' 
        AND (p.title LIKE :query OR p.content LIKE :query OR p.tags LIKE :query)
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    
    $search_param = '%' . $query . '%';
    $stmt = $db->prepare($search_query);
    $stmt->bindParam(':query', $search_param);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Create excerpt
        $content = strip_tags($row['content']);
        $excerpt = substr($content, 0, 150);
        if (strlen($content) > 150) {
            $excerpt .= '...';
        }
        
        // Get media count if post has media
        $media_count = 0;
        if ($row['has_media']) {
            $media_count_query = "SELECT COUNT(*) FROM post_media WHERE post_id = :post_id";
            $media_count_stmt = $db->prepare($media_count_query);
            $media_count_stmt->bindParam(':post_id', $row['id']);
            $media_count_stmt->execute();
            $media_count = $media_count_stmt->fetchColumn();
        }
        
        $results[] = [
            'id' => $row['id'],
            'title' => htmlspecialchars($row['title']),
            'excerpt' => htmlspecialchars($excerpt),
            'author' => htmlspecialchars($row['author']),
            'created_at' => timeAgo($row['created_at']),
            'like_count' => $row['like_count'],
            'comment_count' => $row['comment_count'],
            'has_media' => $row['has_media'],
            'media_count' => $media_count
        ];
    }
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => 'Search failed']);
}
?>