<?php
session_start();
require_once "db_config.php"; // Make sure this file creates a MySQLi connection

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "student") {
    header("location: home.php?error=You must log in as a student to access this page.");
    exit;
}

$user_id = $_SESSION["id"];

// ---- Stats ---- //
$books_checked_out = 0;
$books_read_month = 0;
$overdue_books = 0;
$reading_goal = 0;

// Assuming $conn is your MySQLi connection object from db_config.php
try {
    // Total checked out
    $stmt = $conn->prepare("SELECT COUNT(*) FROM book_loans WHERE id = ? AND return_date IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($books_checked_out);
    $stmt->fetch();
    $stmt->close();

    // Books read this month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM book_loans WHERE id = ? AND return_date IS NOT NULL AND MONTH(return_date) = MONTH(CURRENT_DATE()) AND YEAR(return_date) = YEAR(CURRENT_DATE())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($books_read_month);
    $stmt->fetch();
    $stmt->close();

    // Overdue books
    $stmt = $conn->prepare("SELECT COUNT(*) FROM book_loans WHERE id = ? AND return_date IS NULL AND due_date < CURRENT_DATE()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($overdue_books);
    $stmt->fetch();
    $stmt->close();

    // Simple goal progress (assume 5 books/month goal)
    $reading_goal = min(100, round(($books_read_month / 5) * 100));
} catch (Exception $e) {
    die("Error fetching stats: " . $e->getMessage());
}

// ---- Currently Borrowed ---- //
$borrowed_books = [];
try {
    $stmt = $conn->prepare("SELECT b.book_id, b.title, b.author, bl.due_date 
                           FROM book_loans bl
                           JOIN books b ON bl.book_id = b.book_id
                           WHERE bl.id = ? AND bl.return_date IS NULL
                           ORDER BY bl.due_date ASC LIMIT 4");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $borrowed_books = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $borrowed_books = [];
}

// ---- Recommended Books ---- //
$recommended_books = [];
try {
    $stmt = $conn->prepare("SELECT book_id, title, author FROM books 
                           WHERE book_id NOT IN (SELECT book_id FROM book_loans WHERE id = ?) 
                           ORDER BY RAND() LIMIT 4");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recommended_books = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $recommended_books = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | School Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> :root { --primary: #4361ee; --primary-dark: #3a56d4; --secondary: #3f37c9; --accent: #4895ef; --light: #f8f9fa; --dark: #212529; --success: #4cc9f0; --danger: #f72585; --gray: #6c757d; --light-gray: #e9ecef; } * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; } body { background-color: #f5f7fa; color: var(--dark); line-height: 1.6; } .dashboard-container { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; } /* Sidebar Styles */ .sidebar { background: var(--primary); color: white; padding: 1.5rem; box-shadow: 2px 0 10px rgba(0,0,0,0.1); } .brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); } .brand i { font-size: 1.5rem; } .brand h1 { font-size: 1.25rem; font-weight: 600; } .nav-menu { display: flex; flex-direction: column; gap: 0.5rem; } .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 8px; color: white; text-decoration: none; transition: all 0.3s ease; } .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); } .nav-item i { width: 20px; text-align: center; } /* Main Content Styles */ .main-content { padding: 2rem; } .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; } .welcome-message h2 { font-size: 1.5rem; color: var(--dark); margin-bottom: 0.25rem; } .welcome-message p { color: var(--gray); } .user-profile { display: flex; align-items: center; gap: 1rem; } .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; } .logout-btn { background: none; border: none; color: var(--danger); cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; } .logout-btn:hover { text-decoration: underline; } /* Dashboard Cards */ .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; } .stat-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.3s ease; } .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); } .stat-card h3 { font-size: 0.9rem; color: var(--gray); margin-bottom: 0.5rem; } .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--primary); } /* Book Sections */ .book-section { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; } .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; } .section-title { font-size: 1.25rem; font-weight: 600; } .view-all { color: var(--primary); font-size: 0.9rem; text-decoration: none; } .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; } .book-card { border-radius: 8px; overflow: hidden; transition: transform 0.3s ease; } .book-card:hover { transform: translateY(-5px); } .book-cover { height: 200px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; color: var(--gray); margin-bottom: 0.75rem; } .book-cover i { font-size: 3rem; } .book-info h4 { font-size: 0.95rem; margin-bottom: 0.25rem; } .book-info p { font-size: 0.85rem; color: var(--gray); } .due-date { font-size: 0.75rem; color: var(--danger); margin-top: 0.5rem; display: flex; align-items: center; gap: 0.25rem; } /* Responsive Design */ @media (max-width: 768px) { .dashboard-container { grid-template-columns: 1fr; } .sidebar { display: none; } .book-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); } } </style>
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
                <a href="student_dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="browse_books.php" class="nav-item">
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
            <?php include 'date_time.php'; ?>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h2>
                    <p>Student Dashboard</p>
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
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Books Checked Out</h3>
                    <div class="value"><?php echo $books_checked_out; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Books Read This Month</h3>
                    <div class="value"><?php echo $books_read_month; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Overdue Books</h3>
                    <div class="value"><?php echo $overdue_books; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Reading Goal</h3>
                    <div class="value"><?php echo $reading_goal; ?>%</div>
                </div>
            </div>
            
            <!-- Currently Borrowed Books -->
            <div class="book-section">
                <div class="section-header">
                    <h3 class="section-title">Currently Borrowed</h3>
                    <a href="my_books.php" class="view-all">View All</a>
                </div>
                
                <div class="book-grid">
                    <?php if (count($borrowed_books) > 0): ?>
                        <?php foreach ($borrowed_books as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="book-info">
                                    <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($book['author']); ?></p>
                                    <?php
                                        $due = new DateTime($book['due_date']);
                                        $today = new DateTime();
                                        if ($today > $due) {
                                            $over = $today->diff($due)->days;
                                            echo "<div class='due-date' style='color: var(--danger);'><i class='fas fa-exclamation-triangle'></i> {$over} days overdue</div>";
                                        } else {
                                            $remaining = $today->diff($due)->days;
                                            echo "<div class='due-date'><i class='fas fa-check-circle'></i> Due in {$remaining} days</div>";
                                        }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No books currently borrowed.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recommended Books -->
            <div class="book-section">
                <div class="section-header">
                    <h3 class="section-title">Recommended For You</h3>
                    <a href="browse_books.php" class="view-all">Browse All</a>
                </div>
                
                <div class="book-grid">
                    <?php if (count($recommended_books) > 0): ?>
                        <?php foreach ($recommended_books as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="book-info">
                                    <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($book['author']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No recommendations available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
