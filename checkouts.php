<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in as librarian
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Get all checkouts from database with book and user details
$checkouts = [];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT 
            bl.loan_id, bl.checkout_date, bl.due_date, bl.return_date, bl.status,
            b.title AS book_title, b.book_id,
            u.full_name AS user_name, u.id
          FROM book_loans bl
          JOIN books b ON bl.book_id = b.book_id
          JOIN users u ON bl.id = u.id";

$params = [];
$types = '';

// Apply filters
$where = [];
if (!empty($search)) {
    $where[] = "(b.title LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($status_filter == 'checked_out') {
    $where[] = "bl.status = 'checked_out' AND bl.due_date >= NOW()";
} elseif ($status_filter == 'overdue') {
    $where[] = "bl.status = 'checked_out' AND bl.due_date < NOW()";
} elseif ($status_filter == 'returned') {
    $where[] = "bl.status = 'returned'";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

$query .= " ORDER BY bl.due_date ASC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $checkouts[] = $row;
}

$stmt->close();

// Get counts for status tabs
$status_counts = [
    'all' => 0,
    'checked_out' => 0,
    'overdue' => 0,
    'returned' => 0
];

$count_query = "SELECT 
                SUM(CASE WHEN status = 'checked_out' AND due_date >= NOW() THEN 1 ELSE 0 END) AS checked_out,
                SUM(CASE WHEN status = 'checked_out' AND due_date < NOW() THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned,
                COUNT(*) AS all_count
                FROM book_loans";

$count_result = $conn->query($count_query);
if ($count_row = $count_result->fetch_assoc()) {
    $status_counts = [
        'all' => $count_row['all_count'],
        'checked_out' => $count_row['checked_out'],
        'overdue' => $count_row['overdue'],
        'returned' => $count_row['returned']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkouts Management | School Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse all styles from librarian_dashboard.php */
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
        
        /* Checkouts Section Styles */
        .checkouts-section {
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
        
        .search-add {
            display: flex;
            gap: 1rem;
        }
        
        .search-box {
            padding: 0.5rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            width: 250px;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        /* Status Tabs */
        .status-tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 1.5rem;
        }
        
        .status-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            position: relative;
            color: var(--gray);
            font-weight: 500;
        }
        
        .status-tab.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .status-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .status-count {
            margin-left: 0.5rem;
            background: var(--light-gray);
            color: var(--dark);
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: normal;
        }
        
        .status-tab.active .status-count {
            background: var(--primary);
            color: white;
        }
        
        /* Checkouts Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            background: var(--light-gray);
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(72, 149, 239, 0.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-checked_out {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-returned {
            background: #d4edda;
            color: #155724;
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: var(--dark);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .search-add {
                flex-direction: column;
                width: 100%;
            }
            
            .search-box {
                width: 100%;
            }
            
            .status-tabs {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
            }
            
            .status-tab {
                padding: 0.5rem 1rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
                <a href="librarian_dashboard.php" class="nav-item">
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
                <a href="checkouts.php" class="nav-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Checkouts</span>
                </a>
                 <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Checkouts Management</h2>
                    <p>View and manage all book checkouts</p>
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
            
            <!-- Checkouts Section -->
            <div class="checkouts-section">
                <div class="section-header">
                    <h3 class="section-title">All Checkouts</h3>
                    <div class="search-add">
                        <form method="GET" action="checkouts.php">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="text" name="search" class="search-box" placeholder="Search checkouts..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                        <a href="new_checkout.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            <span>New Checkout</span>
                        </a>
                    </div>
                </div>
                
                <!-- Status Tabs -->
                <div class="status-tabs">
                    <a href="checkouts.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="status-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                        All
                        <span class="status-count"><?php echo $status_counts['all']; ?></span>
                    </a>
                    <a href="checkouts.php?status=checked_out<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="status-tab <?php echo $status_filter == 'checked_out' ? 'active' : ''; ?>">
                        Checked Out
                        <span class="status-count"><?php echo $status_counts['checked_out']; ?></span>
                    </a>
                    <a href="checkouts.php?status=overdue<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="status-tab <?php echo $status_filter == 'overdue' ? 'active' : ''; ?>">
                        Overdue
                        <span class="status-count"><?php echo $status_counts['overdue']; ?></span>
                    </a>
                    <a href="checkouts.php?status=returned<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="status-tab <?php echo $status_filter == 'returned' ? 'active' : ''; ?>">
                        Returned
                        <span class="status-count"><?php echo $status_counts['returned']; ?></span>
                    </a>
                </div>
                
                <!-- Checkouts Table -->
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Student</th>
                            <th>Checkout Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkouts as $checkout): 
                            $is_overdue = $checkout['status'] == 'checked_out' && strtotime($checkout['due_date']) < time();
                            $status = $is_overdue ? 'overdue' : $checkout['status'];
                        ?>
                        <tr>
                            <td>
                                <a href="books.php?id=<?php echo $checkout['book_id']; ?>">
                                    <?php echo htmlspecialchars($checkout['book_title']); ?>
                                </a>
                            </td>
                            <td>
                                <a href="students.php?id=<?php echo $checkout['id']; ?>">
                                    <?php echo htmlspecialchars($checkout['user_name']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($checkout['checkout_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($checkout['due_date'])); ?></td>
                            <td>
                                <?php echo $checkout['return_date'] ? date('M j, Y', strtotime($checkout['return_date'])) : '--'; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php 
                                    echo ucfirst(str_replace('_', ' ', $status));
                                    if ($is_overdue) {
                                        $days_overdue = floor((time() - strtotime($checkout['due_date'])) / (60 * 60 * 24));
                                        echo " ($days_overdue day" . ($days_overdue != 1 ? 's' : '') . ")";
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="action-btns">
                                <?php if ($checkout['status'] == 'checked_out'): ?>
                                    <a href="manage_returns.php?action=return&id=<?php echo $checkout['loan_id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-undo"></i>
                                        <span>Return</span>
                                    </a>
                                    <a href="manage_returns.php?action=renew&id=<?php echo $checkout['loan_id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="fas fa-sync"></i>
                                        <span>Renew</span>
                                    </a>
                                <?php endif; ?>
                            <!--    <a href="checkout_details.php?id=<?php echo $checkout['loan_id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    <span>Details</span> -->
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($checkouts)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">
                                No checkouts found
                                <?php if (!empty($search) || $status_filter != 'all'): ?>
                                    <p style="margin-top:0.5rem;">
                                        <a href="checkouts.php" class="btn btn-sm btn-primary">Clear filters</a>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            
            // Auto-submit search form when typing stops
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimer;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        });
    </script>
</body>
</html>