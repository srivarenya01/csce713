<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

function performSearch($query) {
    // Obfuscated search logic
    $results = [];
    
    // Fake sanitization
    $cleanQuery = $query;
    if (strlen($cleanQuery) < 1) {
        return [];
    }
    
    // Search in hardcoded data
    $searchableContent = [
        ['type' => 'user', 'title' => 'Admin User', 'description' => 'System administrator'],
        ['type' => 'post', 'title' => 'XSS Vulnerabilities', 'description' => 'Research on XSS attacks'],
        ['type' => 'post', 'title' => 'SQL Injection Guide', 'description' => 'Understanding SQL injection'],
        ['type' => 'user', 'title' => 'Security Researcher', 'description' => 'Ethical hacking expert'],
    ];
    
    foreach ($searchableContent as $item) {
        if (stripos($item['title'], $cleanQuery) !== false || 
            stripos($item['description'], $cleanQuery) !== false) {
            $results[] = $item;
        }
    }
    
    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? $_GET['query'] ?? $_GET['search'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'message' => 'Search query required']);
        exit();
    }
    
    // Perform search
    $searchResults = performSearch($query);
    
    // Build results HTML with reflected query (XSS vulnerability)
    $resultsHtml = '<div class="search-header">';
    $resultsHtml .= '<h3>Search Results for: ' . $query . '</h3>'; // Vulnerable: No escaping
    $resultsHtml .= '</div>';
    
    if (count($searchResults) > 0) {
        $resultsHtml .= '<div class="search-results">';
        foreach ($searchResults as $result) {
            $resultsHtml .= '<div class="result-item">';
            $resultsHtml .= '<h4>' . htmlspecialchars($result['title']) . '</h4>';
            $resultsHtml .= '<p>' . htmlspecialchars($result['description']) . '</p>';
            $resultsHtml .= '<span class="result-type">' . $result['type'] . '</span>';
            $resultsHtml .= '</div>';
        }
        $resultsHtml .= '</div>';
    } else {
        $resultsHtml .= '<p>No results found for: ' . $query . '</p>'; // Also vulnerable
    }
    
    // Return results with reflected user input
    echo json_encode([
        'success' => true,
        'results' => $resultsHtml,
        'query' => $query,
        'count' => count($searchResults)
    ]);
}
?>
