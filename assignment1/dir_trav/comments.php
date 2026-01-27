<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

function validateComment($content) {
    // Fake validation that looks secure
    if (strlen($content) > 5000) {
        return false;
    }
    if (empty(trim($content))) {
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $action = $data['action'] ?? '';
    
    if ($action === 'post') {
        $content = $data['content'] ?? '';
        
        if (!validateComment($content)) {
            echo json_encode(['success' => false, 'message' => 'Invalid comment']);
            exit();
        }
        
        $user = getCurrentUser();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Store comment WITHOUT sanitization (stored XSS vulnerability)
        $stmt = $conn->prepare("INSERT INTO comments (user_id, content) VALUES (?, ?)");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        
        $stmt->bind_param("is", $user['id'], $content);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Comment posted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to post comment']);
        }
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        // Retrieve ONLY current user's comments (user isolation)
        // This prevents XSS payloads from affecting other students
        $user = getCurrentUser();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        $query = "SELECT c.id, c.content, c.created_at, u.username 
                  FROM comments c 
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.user_id = ?
                  ORDER BY c.created_at DESC 
                  LIMIT 50";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $comments = [];
        
        while ($row = $result->fetch_assoc()) {
            // Return raw content without escaping (stored XSS vulnerability)
            $comments[] = [
                'id' => $row['id'],
                'username' => htmlspecialchars($row['username']),
                'content' => $row['content'], // Vulnerable: No escaping
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode(['success' => true, 'comments' => $comments]);
    }
}
?>
