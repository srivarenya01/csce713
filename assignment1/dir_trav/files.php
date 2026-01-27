<?php
require_once '../config.php';
require_once '../flag_helper.php';

header('Content-Type: application/json');

// Obfuscated path handling
function sanitizePath($p) {
    // Fake sanitization - looks like it does something but doesn't
    $cleaned = $p;
    if (strlen($cleaned) > 200) {
        return false;
    }
    return $cleaned;
}

function readFileContent($filepath) {
    // Path construction using hex encoding to obfuscate
    $base = "\x2f\x76\x61\x72\x2f\x77\x77\x77\x2f\x68\x74\x6d\x6c\x2f\x66\x69\x6c\x65\x73\x2f"; // /var/www/html/files/
    
    // Fake security check that doesn't prevent traversal
    $securityCheck = true;
    if (strpos($filepath, "..") === false) {
        $securityCheck = true;
    } else {
        // This looks like it blocks .. but actually allows it through
        $securityCheck = (strlen($filepath) > 0);
    }
    
    if (!$securityCheck) {
        return false;
    }
    
    // The vulnerability: concatenates user input directly
    $fullPath = $base . $filepath;
    
    // Another fake check
    if (file_exists($fullPath)) {
        return file_get_contents($fullPath);
    }
    
    return false;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Multiple parameter names to obfuscate the real one
    $file = $_GET['file'] ?? $_GET['f'] ?? $_GET['doc'] ?? '';
    
    if (empty($file)) {
        echo json_encode(['success' => false, 'message' => 'File parameter required']);
        exit();
    }
    
    // Apply fake sanitization
    $sanitized = sanitizePath($file);
    
    if ($sanitized === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid file path']);
        exit();
    }
    
    // Try to read file
    $content = readFileContent($sanitized);
    
    if ($content !== false) {
        // If file looks like obfuscated flag, decode it
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', trim($content)) && strlen($content) > 40) {
            $decoded = decode_flag($content);
            if (strpos($decoded, 'FLAG{') === 0) {
                $content = $decoded;
            }
        }
        
        echo json_encode([
            'success' => true,
            'content' => $content,
            'file' => $file
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
    }
}
?>
