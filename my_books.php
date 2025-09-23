<?php
session_start();
require_once "db_config.php";

// Check if user is logged in as student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "student") {
    header("location: home.php?error=You must log in as a student to access this page.");
    exit;
}

$user_id = $_SESSION["id"];
$current_books = [];
$history_books = [];

// Get currently borrowed books
try {
    $stmt = $conn->prepare("SELECT b.book_id, b.title, b.author, b.cover_image, bl.loan_id, bl.checkout_date, bl.due_date, bl.status 
                           FROM book_loans bl 
                           JOIN books b ON bl.book_id = b.book_id 
                           WHERE bl.id = ? AND bl.return_date IS NULL 
                           ORDER BY bl.due_date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_books = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $current_books = [];
}

// Get borrowing history (returned books)
try {
    $stmt = $conn->prepare("SELECT b.book_id, b.title, b.author, b.cover_image, bl.loan_id, bl.checkout_date, bl.due_date, bl.return_date, bl.status 
                           FROM book_loans bl 
                           JOIN books b ON bl.book_id = b.book_id 
                           WHERE bl.id = ? AND bl.return_date IS NOT NULL 
                           ORDER BY bl.return_date DESC 
                           LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_books = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $history_books = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books | School Library</title>
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
        
        /* Book Sections */
        .book-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .book-card {
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .book-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .book-cover {
            height: 150px;
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
            font-size: 2rem;
        }
        
        .book-info {
            padding: 1rem;
        }
        
        .book-info h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .book-info p {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .loan-details {
            border-top: 1px solid var(--light-gray);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .loan-info {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-checked-out {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-overdue {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .status-returned {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .due-date {
            font-weight: 500;
        }
        
        .overdue {
            color: var(--danger);
            font-weight: 600;
        }
        
        .no-books {
            text-align: center;
            color: var(--gray);
            padding: 2rem;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1rem;
            color: var(--gray);
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            margin-bottom: -2px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .book-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: left;
                padding: 0.75rem 1rem;
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
                <a href="browse_books.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Browse Books</span>
                </a>
                <a href="my_books.php" class="nav-item active">
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
             <?php include 'date_time.php'; ?>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h2>
                    <p>My Books</p>
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
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="showTab('current')">Currently Borrowed</button>
                <button class="tab" onclick="showTab('history')">Borrowing History</button>
            </div>
            
            <!-- Currently Borrowed Books -->
            <div id="current-tab" class="tab-content active">
                <div class="book-section">
                    <div class="section-header">
                        <h3 class="section-title">Currently Borrowed Books</h3>
                    </div>
                    
                    <div class="book-grid">
                        <?php if (count($current_books) > 0): ?>
                            <?php foreach ($current_books as $book): ?>
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
                                        
                                        <div class="loan-details">
                                            <div class="loan-info">
                                                Borrowed: <?php echo date('M j, Y', strtotime($book['checkout_date'])); ?>
                                            </div>
                                            <div class="loan-info">
                                                Due: 
                                                <?php
                                                $due_date = new DateTime($book['due_date']);
                                                $today = new DateTime();
                                                $is_overdue = $today > $due_date;
                                                ?>
                                                <span class="due-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                    <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                                    <?php if ($is_overdue): ?>
                                                        (<?php echo $today->diff($due_date)->days; ?> days overdue)
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="loan-info">
                                                Status: 
                                                <span class="status <?php echo $is_overdue ? 'status-overdue' : 'status-checked-out'; ?>">
                                                    <?php echo $is_overdue ? 'Overdue' : 'Checked Out'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-books">
                                <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <p>You don't have any books checked out currently.</p>
                                <a href="browse_books.php" class="btn" style="margin-top: 1rem;">Browse Books</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Borrowing History -->
            <div id="history-tab" class="tab-content">
                <div class="book-section">
                    <div class="section-header">
                        <h3 class="section-title">Borrowing History</h3>
                    </div>
                    
                    <div class="book-grid">
                        <?php if (count($history_books) > 0): ?>
                            <?php foreach ($history_books as $book): ?>
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
                                        
                                        <div class="loan-details">
                                            <div class="loan-info">
                                                Borrowed: <?php echo date('M j, Y', strtotime($book['checkout_date'])); ?>
                                            </div>
                                            <div class="loan-info">
                                                Due: <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                            </div>
                                            <div class="loan-info">
                                                Returned: <?php echo date('M j, Y', strtotime($book['return_date'])); ?>
                                            </div>
                                            <div class="loan-info">
                                                Status: <span class="status status-returned">Returned</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-books">
                                <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <p>No borrowing history found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Find and activate the clicked tab
            const tabs = document.querySelectorAll('.tab');
            for (let i = 0; i < tabs.length; i++) {
                if (tabs[i].textContent.toLowerCase().includes(tabName)) {
                    tabs[i].classList.add('active');
                    break;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
        document.addEventListener("DOMContentLoaded", function () {
    let hash = window.location.hash;
    if (hash === "#history-tab") {
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        document.querySelector(hash).style.display = 'block';
    }
});
    </script>
</body>
</html>