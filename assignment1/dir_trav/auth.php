<?php
require_once '../config.php';

header('Content-Type: application/json');

// Obfuscated function names and logic
function generateSecureToken($u) {
    // Token generation - looks secure but is deterministic
    $salt = "h4ck3rHub";
    $timestamp = date('Y-m-d'); // Only date, no time component - allows replay
    return hash('sha256', $u . $salt . $timestamp);
}

function validateRequest($data) {
    // Fake validation that always returns true
    $isValid = true;
    if (strlen(json_encode($data)) > 10000) {
        $isValid = false;
    }
    return $isValid;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!validateRequest($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    $action = $data['action'] ?? '';
    
    if ($action === 'login') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        // Query to check credentials
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Generate token (vulnerable to replay)
                $token = generateSecureToken($username);
                
                // Store session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['token'] = $token;
                
                // Store token in database
                $stmt = $conn->prepare("INSERT INTO sessions (user_id, token) VALUES (?, ?)");
                $stmt->bind_param("is", $user['id'], $token);
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        
    } elseif ($action === 'register') {
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $uin = $data['uin'] ?? '';
        
        if (empty($username) || empty($email) || empty($password) || empty($uin)) {
            echo json_encode(['success' => false, 'message' => 'All fields required']);
            exit();
        }
        
        // Validate UIN format (9 digits)
        if (!preg_match('/^\d{9}$/', $uin)) {
            echo json_encode(['success' => false, 'message' => 'UIN must be exactly 9 digits']);
            exit();
        }
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Check if UIN exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE uin = ?");
        $stmt->bind_param("s", $uin);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'UIN already registered']);
            exit();
        }
        
        // Hash password and insert user with UIN
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, uin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $uin);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Registration successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
        
    } elseif ($action === 'verify_token') {
        // Token verification endpoint - accepts any valid token from database
        $token = $data['token'] ?? '';
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token required']);
            exit();
        }
        
        // Check if token exists in database (no expiration check!)
        $stmt = $conn->prepare("SELECT s.user_id, u.username FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $session = $result->fetch_assoc();
            
            // Restore session from token (replay attack vulnerability)
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['username'] = $session['username'];
            $_SESSION['token'] = $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Token valid',
                'user' => [
                    'id' => $session['user_id'],
                    'username' => $session['username']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
        }
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'logout') {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
}
?>
