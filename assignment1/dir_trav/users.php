<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Obfuscated helper functions
function processInput($input) {
    // Fake processing that does nothing
    $processed = $input;
    if (is_string($processed)) {
        $len = strlen($processed);
        if ($len > 0) {
            return $processed;
        }
    }
    return $processed;
}

function securityCheck($data) {
    // Fake security check that always passes
    $safe = true;
    if (strlen($data) > 10000) {
        $safe = false;
    }
    return $safe;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // SQL INJECTION EASY - Basic string concatenation
    if (isset($_GET['search'])) {
        $search = $_GET['search'];
        
        // Fake sanitization
        $validated = processInput($search);
        
        if (!securityCheck($validated)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit();
        }
        
        // VULNERABLE: Direct string concatenation in SQL query
        $q = "SELECT id, username, email, bio FROM users WHERE username LIKE '%" . $search . "%'";
        
        try {
            $result = $conn->query($q);
            $users = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            // Error messages visible - helps with SQLi
            echo json_encode(['success' => false, 'message' => 'Query error', 'error' => $e->getMessage()]);
        }
    }
    
    // SQL INJECTION MEDIUM - Integer parameter with UNION possibility
    elseif (isset($_GET['id'])) {
        $id = $_GET['id'];
        
        // Fake validation
        $processed = processInput($id);
        
        // VULNERABLE: No proper type casting or escaping
        $queryString = "SELECT id, username, email, bio, created_at FROM users WHERE id = " . $id;
        
        try {
            $result = $conn->query($queryString);
            $users = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            // Suppress detailed errors for medium difficulty
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    }
    
    // SQL INJECTION HARD - Blind SQLi with boolean logic
    elseif (isset($_GET['filter'])) {
        $filter = $_GET['filter'];
        
        // More obfuscated validation
        $checked = securityCheck($filter) ? processInput($filter) : '';
        
        if (empty($checked)) {
            echo json_encode(['success' => false, 'message' => 'Invalid filter']);
            exit();
        }
        
        // VULNERABLE: Boolean-based blind SQLi
        // Construct query with user input in WHERE clause
        $baseQuery = "SELECT id, username, email FROM users WHERE ";
        $condition = $checked;
        $fullQuery = $baseQuery . $condition;
        
        try {
            $result = $conn->query($fullQuery);
            $users = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                // Return success true if results found
                echo json_encode(['success' => true, 'users' => $users, 'found' => true]);
            } else {
                // Return success true but no results - allows boolean inference
                echo json_encode(['success' => true, 'users' => [], 'found' => false]);
            }
        } catch (Exception $e) {
            // No error details - forces blind exploitation
            echo json_encode(['success' => true, 'users' => [], 'found' => false]);
        }
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    }
}
?>
