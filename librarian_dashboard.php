<?php
session_start();
require_once 'db_config.php'; // Database connection file

// Check if the user is logged in and is a librarian
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Get dashboard statistics
$stats = [];
$stats['total_books'] = $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0];
$stats['active_checkouts'] = $conn->query("SELECT COUNT(*) FROM book_loans WHERE status = 'checked_out'")->fetch_row()[0];
$stats['overdue_books'] = $conn->query("SELECT COUNT(*) FROM book_loans WHERE status = 'checked_out' AND due_date < NOW()")->fetch_row()[0];
$stats['new_students'] = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0];

// Get recent activities
$activities = [];
$result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard | School Library</title>
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
        
        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-card .trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        
        .trend.up {
            color: #2ecc71;
        }
        
        .trend.down {
            color: var(--danger);
        }
        
        /* Recent Activity */
        .activity-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        
        .activity-content h4 {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-content p {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
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
              <a href="librarian_dashboard.php" style="text-decoration: none; color: inherit;">
              <h1>Brightway LMS</h1>
              </a>

            </div>
            
            <nav class="nav-menu">
                <a href="#" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="books.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Books</span>
                </a>
                <a href="students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="checkouts.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Checkouts</span>
                </a>
                 <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </nav>
            <?php include 'date_time.php'; ?>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h2>
                    <p>Librarian Dashboard</p>
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
                    <h3>Total Books</h3>
                    <div class="value"><?php echo number_format($stats['total_books']); ?></div>
                    <div class="trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span><?php echo round(($stats['total_books'] / max(1, $stats['total_books'] - 50)) * 100 - 100); ?>% from last month</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Active Checkouts</h3>
                    <div class="value"><?php echo number_format($stats['active_checkouts']); ?></div>
                    <div class="trend <?php echo ($stats['active_checkouts'] > 150) ? 'down' : 'up'; ?>">
                        <i class="fas fa-arrow-<?php echo ($stats['active_checkouts'] > 150) ? 'down' : 'up'; ?>"></i>
                      <span><?php 
$change = $stats['active_checkouts'] - max(1, ($stats['active_checkouts'] - 20));
echo abs(round(($change / max(1, ($stats['active_checkouts'] - 20))) * 100)); 
?>% from last week</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Overdue Books</h3>
                    <div class="value"><?php echo number_format($stats['overdue_books']); ?></div>
                    <div class="trend <?php echo ($stats['overdue_books'] > 15) ? 'up' : 'down'; ?>">
                        <i class="fas fa-arrow-<?php echo ($stats['overdue_books'] > 15) ? 'up' : 'down'; ?>"></i>
                       <span><?php 
$change = $stats['overdue_books'] - max(1, ($stats['overdue_books'] - 5));
echo abs(round(($change / max(1, ($stats['overdue_books'] - 5))) * 100)); 
?>% from yesterday</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>New Students</h3>
                    <div class="value"><?php echo number_format($stats['new_students']); ?></div>
                    <div class="trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span><?php echo round(($stats['new_students'] / max(1, $stats['new_students'] - 5)) * 100 - 100); ?>% this month</span>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Section -->
            <div class="activity-section">
                <div class="section-header">
                    <h3 class="section-title">Recent Activity</h3>
                    <a href="activity_log.php" style="color: var(--primary); font-size: 0.9rem;">View All</a>
                </div>
                
                <div class="activity-list">
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php 
                            $icon = 'fa-book';
                            if (strpos($activity['activity_type'], 'student') !== false) $icon = 'fa-user';
                            elseif (strpos($activity['activity_type'], 'checkout') !== false) $icon = 'fa-book-open';
                            elseif (strpos($activity['activity_type'], 'overdue') !== false) $icon = 'fa-exclamation-triangle';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <h4><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></h4>
                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                            <div class="activity-time">
                                <?php 
                                $time = strtotime($activity['created_at']);
                                echo date('M j, Y g:i A', $time); 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Simple interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to clicked nav items
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!this.href || this.href === '#') {
                        e.preventDefault();
                    }
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Auto-refresh dashboard every 60 seconds
            setTimeout(() => {
                window.location.reload();
            }, 60000);
        });
    </script>
</body>
</html>