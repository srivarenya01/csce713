<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HackerHub</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1 class="nav-logo">🔐 HackerHub</h1>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#" onclick="showSection('files')">Files</a></li>
                <li><a href="#" onclick="showSection('search')">Search</a></li>
                <li><a href="#" onclick="showSection('users')">Users</a></li>
                <li><a href="#" onclick="showSection('profile')">Profile</a></li>
                <li><a href="api/auth.php?action=logout">Logout</a></li>
            </ul>
            <span class="user-info">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</span>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Comments Section (Default) -->
        <div id="section-comments" class="section active">
            <h2>Community Feed</h2>
            <div class="comment-form">
                <h3>Post a Comment</h3>
                <form id="commentForm">
                    <textarea id="comment-content" name="content" placeholder="Share your thoughts..." required></textarea>
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </form>
                <div id="comment-message" class="message"></div>
            </div>
            <div class="comments-list">
                <h3>Recent Comments</h3>
                <div id="comments-container">
                    <p>Loading comments...</p>
                </div>
            </div>
        </div>

        <!-- Files Section -->
        <div id="section-files" class="section">
            <h2>File Browser</h2>
            <p>Browse available documentation and resources.</p>
            <div class="file-browser">
                <div class="file-list">
                    <div class="file-item" onclick="viewFile('readme.txt')">
                        <span class="file-icon">📄</span>
                        <span class="file-name">readme.txt</span>
                    </div>
                    <div class="file-item" onclick="viewFile('docs/api.txt')">
                        <span class="file-icon">📄</span>
                        <span class="file-name">api.txt</span>
                    </div>
                    <div class="file-item" onclick="viewFile('docs/security.txt')">
                        <span class="file-icon">📄</span>
                        <span class="file-name">security.txt</span>
                    </div>
                </div>
                <div class="file-content">
                    <h3>File Viewer</h3>
                    <div id="file-viewer">
                        <p>Select a file to view its contents.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div id="section-search" class="section">
            <h2>Search</h2>
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Search users, content, research...">
                <button onclick="performSearch()" class="btn btn-primary">Search</button>
            </div>
            <div id="search-results" class="results-container">
                <p>Enter a search query to find content.</p>
            </div>
        </div>

        <!-- Users Section -->
        <div id="section-users" class="section">
            <h2>User Directory</h2>
            <div class="users-search">
                <input type="text" id="user-search" placeholder="Search by username...">
                <button onclick="searchUsers()" class="btn btn-primary">Search</button>
            </div>
            <div class="users-filter">
                <label>Filter by ID:</label>
                <input type="number" id="user-id" placeholder="User ID">
                <button onclick="filterById()" class="btn btn-secondary">Filter</button>
            </div>
            <div class="users-advanced">
                <label>Advanced Filter:</label>
                <input type="text" id="user-filter" placeholder="Custom filter expression">
                <button onclick="advancedFilter()" class="btn btn-secondary">Apply</button>
            </div>
            <div id="users-results" class="results-container">
                <p>Use the search above to find users.</p>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="section-profile" class="section">
            <h2>User Profile</h2>
            <div class="profile-search">
                <input type="text" id="profile-user" placeholder="Enter username to view profile">
                <button onclick="viewProfile()" class="btn btn-primary">View Profile</button>
            </div>
            <div id="profile-container" class="profile-display">
                <p>Enter a username to view their profile.</p>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        // Load comments on page load
        loadComments();

        function showSection(section) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            const sectionMap = {
                'files': 'section-files',
                'search': 'section-search',
                'users': 'section-users',
                'profile': 'section-profile',
                'comments': 'section-comments'
            };
            document.getElementById(sectionMap[section] || 'section-comments').classList.add('active');
        }

        async function loadComments() {
            try {
                const response = await fetch('api/comments.php?action=list');
                const data = await response.json();
                const container = document.getElementById('comments-container');
                
                if (data.success && data.comments.length > 0) {
                    container.innerHTML = data.comments.map(comment => `
                        <div class="comment">
                            <div class="comment-header">
                                <strong>${comment.username}</strong>
                                <span class="comment-date">${comment.created_at}</span>
                            </div>
                            <div class="comment-body">${comment.content}</div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<p>No comments yet.</p>';
                }
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }

        document.getElementById('commentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const content = document.getElementById('comment-content').value;
            const messageDiv = document.getElementById('comment-message');
            
            try {
                const response = await fetch('api/comments.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'post', content: content})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = 'Comment posted successfully!';
                    document.getElementById('comment-content').value = '';
                    loadComments();
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || 'Failed to post comment';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Error: ' + error.message;
            }
        });

        async function viewFile(filename) {
            try {
                const response = await fetch('api/files.php?file=' + encodeURIComponent(filename));
                const data = await response.json();
                const viewer = document.getElementById('file-viewer');
                
                if (data.success) {
                    viewer.innerHTML = '<pre>' + data.content + '</pre>';
                } else {
                    viewer.innerHTML = '<p class="error">' + data.message + '</p>';
                }
            } catch (error) {
                document.getElementById('file-viewer').innerHTML = '<p class="error">Error loading file</p>';
            }
        }

        async function performSearch() {
            const query = document.getElementById('search-input').value;
            const resultsDiv = document.getElementById('search-results');
            
            try {
                const response = await fetch('api/search.php?q=' + encodeURIComponent(query));
                const data = await response.json();
                
                if (data.success) {
                    resultsDiv.innerHTML = data.results;
                } else {
                    resultsDiv.innerHTML = '<p>No results found.</p>';
                }
            } catch (error) {
                resultsDiv.innerHTML = '<p class="error">Search error</p>';
            }
        }

        async function searchUsers() {
            const search = document.getElementById('user-search').value;
            const resultsDiv = document.getElementById('users-results');
            
            try {
                const response = await fetch('api/users.php?search=' + encodeURIComponent(search));
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    resultsDiv.innerHTML = data.users.map(user => `
                        <div class="user-card">
                            <h4>${user.username}</h4>
                            <p>${user.email}</p>
                            <p class="user-bio">${user.bio || 'No bio'}</p>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<p>No users found.</p>';
                }
            } catch (error) {
                resultsDiv.innerHTML = '<p class="error">Search error</p>';
            }
        }

        async function filterById() {
            const id = document.getElementById('user-id').value;
            const resultsDiv = document.getElementById('users-results');
            
            try {
                const response = await fetch('api/users.php?id=' + encodeURIComponent(id));
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    resultsDiv.innerHTML = data.users.map(user => `
                        <div class="user-card">
                            <h4>${user.username}</h4>
                            <p>${user.email}</p>
                            <p class="user-bio">${user.bio || 'No bio'}</p>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<p>No users found.</p>';
                }
            } catch (error) {
                resultsDiv.innerHTML = '<p class="error">Search error</p>';
            }
        }

        async function advancedFilter() {
            const filter = document.getElementById('user-filter').value;
            const resultsDiv = document.getElementById('users-results');
            
            try {
                const response = await fetch('api/users.php?filter=' + encodeURIComponent(filter));
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    resultsDiv.innerHTML = data.users.map(user => `
                        <div class="user-card">
                            <h4>${user.username}</h4>
                            <p>${user.email}</p>
                            <p class="user-bio">${user.bio || 'No bio'}</p>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<p>No users found.</p>';
                }
            } catch (error) {
                resultsDiv.innerHTML = '<p class="error">Filter error</p>';
            }
        }

        function viewProfile() {
            const username = document.getElementById('profile-user').value;
            loadUserProfile(username);
        }
    </script>
</body>
</html>
