<?php
session_start();
require_once "db_config.php";

// Check if user is logged in as student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "student") {
    header("location: home.php?error=You must log in as a student to access this page.");
    exit;
}

$user_id = $_SESSION["id"];

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8; // Books per page
$offset = ($page - 1) * $limit;

// Get available genres for filter dropdown
$genres = [];
$genre_query = $conn->query("SELECT DISTINCT genre FROM books WHERE genre IS NOT NULL ORDER BY genre");
if ($genre_query) {
    $genres = $genre_query->fetch_all(MYSQLI_ASSOC);
    $genre_query->free();
}

// Build main query with filters
$query = "SELECT * FROM books WHERE available_copies > 0";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($genre)) {
    $query .= " AND genre = ?";
    $params[] = $genre;
    $types .= 's';
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_books);
$count_stmt->fetch();
$count_stmt->close();

// Add pagination to main query
$query .= " ORDER BY title LIMIT ? OFFSET ?";
$params = array_merge($params, [$limit, $offset]);
$types .= 'ii';

// Execute main query
$books = [];
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books | School Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: var(--primary);
            color: white;
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .brand i {
            font-size: 1.5rem;
        }
        
        .brand h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content Styles */
        .main-content {
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .welcome-message h2 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .welcome-message p {
            color: var(--gray);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            text-decoration: underline;
        }
        
        /* Search and Filter */
        .search-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .search-box i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .filter-dropdown {
            min-width: 180px;
        }
        
        .filter-dropdown select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1em;
        }
        
        /* Book Grid */
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .book-card {
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .book-cover {
            height: 200px;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .default-cover {
            color: var(--gray);
            font-size: 3rem;
        }
        
        .book-info {
            padding: 1rem;
        }
        
        .book-info h4 {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        
        .book-info p {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .availability {
            font-size: 0.75rem;
            color: var(--primary);
            margin-top: 0.5rem;
        }
        
        .view-btn {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .pagination a {
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }
        
        .pagination a:hover {
            background: var(--light);
        }
        
        .pagination .current {
            background: var(--primary);
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .book-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand">
                <i class="fas fa-book-open"></i>
                  <a href="student_dashboard.php" style="text-decoration: none; color: inherit;">
              <h1>Brightway LMS</h1>
              </a>
            </div>
            
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="browse_books.php" class="nav-item active">
                    <i class="fas fa-book"></i>
                    <span>Browse Books</span>
                </a>
                <a href="my_books.php" class="nav-item">
                    <i class="fas fa-bookmark"></i>
                    <span>My Books</span>
                </a>
                <a href="my_books.php#history-tab" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h2>
                    <p>Browse Books</p>
                </div>
                
                <div class="user-profile">
                    <div class="avatar">
                        <?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <form method="GET" class="search-container">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </div>
                
                <div class="filter-dropdown">
                    <select name="genre">
                        <option value="">All Genres</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?php echo htmlspecialchars($g['genre']); ?>" <?php echo $genre === $g['genre'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['genre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="search-btn" style="display: none;">Search</button>
            </form>
            
            <!-- Book Grid -->
            <div class="book-grid">
                <?php if (count($books) > 0): ?>
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-cover">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?> cover">
                                <?php else: ?>
                                    <i class="fas fa-book default-cover"></i>
                                <?php endif; ?>
                            </div>
                            <div class="book-info">
                                <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                <p><?php echo htmlspecialchars($book['author']); ?></p>
                                <p><?php echo htmlspecialchars($book['genre']); ?></p>
                                <div class="availability">
                                    <?php echo $book['available_copies']; ?> of <?php echo $book['total_copies']; ?> available
                                </div>
                                <a href="book_details.php?id=<?php echo $book['book_id']; ?>" class="view-btn">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No books found matching your criteria.</p>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_books > $limit): ?>
                <div class="pagination">
                    <?php $total_pages = ceil($total_books / $limit); ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Auto-submit form when filter changes
            document.querySelector('select[name="genre"]').addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>