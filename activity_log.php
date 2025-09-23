<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in as librarian
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Get filter parameters
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : null;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get all librarians for filter dropdown
$librarians = [];
$result = $conn->query("SELECT id, full_name FROM users WHERE role = 'librarian' ORDER BY full_name");
while ($row = $result->fetch_assoc()) {
    $librarians[] = $row;
}

// Get activity types for filter dropdown
$activity_types = [];
$result = $conn->query("SELECT DISTINCT activity_type FROM activity_log ORDER BY activity_type");
while ($row = $result->fetch_assoc()) {
    $activity_types[] = $row['activity_type'];
}

// Build query for activity logs
$query = "SELECT al.*, u.full_name 
          FROM activity_log al
          LEFT JOIN users u ON al.id = u.id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if ($user_filter) {
    $query .= " AND al.id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

if (!empty($type_filter)) {
    $query .= " AND al.activity_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (al.description LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query .= " ORDER BY al.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log | School Library</title>
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
        
        /* Activity Log Section */
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
        
        /* Filters */
        .filters {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .filter-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
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
        
        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background: #d1d7dc;
        }
        
        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            padding: 1rem;
            border-left: 4px solid var(--primary);
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .activity-user {
            font-weight: 600;
            color: var(--primary);
        }
        
        .activity-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: var(--light-gray);
            color: var(--dark);
            border-radius: 4px;
            font-size: 0.8rem;
            text-transform: capitalize;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .activity-description {
            margin-top: 0.5rem;
        }
        
        .activity-ip {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }
        
        .no-activities {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .filter-actions {
                justify-content: flex-start;
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
                <a href="checkouts.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Checkouts</span>
                </a>
                <a href="activity_log.php" class="nav-item active">
                    <i class="fas fa-history"></i>
                    <span>Activity Log</span>
                </a>
            </nav>
            <?php include 'date_time.php'; ?>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Activity Log</h2>
                    <p>View all system activities and events</p>
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
            
            <!-- Activity Log Section -->
            <div class="activity-section">
                <div class="section-header">
                    <h3 class="section-title">System Activities</h3>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <form method="GET" action="activity_log.php">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="user" class="filter-label">Librarian</label>
                                <select id="user" name="user" class="filter-control">
                                    <option value="">All Librarians</option>
                                    <?php foreach ($librarians as $librarian): ?>
                                        <option value="<?php echo $librarian['id']; ?>" <?php echo $user_filter == $librarian['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($librarian['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="type" class="filter-label">Activity Type</label>
                                <select id="type" name="type" class="filter-control">
                                    <option value="">All Types</option>
                                    <?php foreach ($activity_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="date_from" class="filter-label">Date From</label>
                                <input type="date" id="date_from" name="date_from" class="filter-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to" class="filter-label">Date To</label>
                                <input type="date" id="date_to" name="date_to" class="filter-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="search" class="filter-label">Search</label>
                                <input type="text" id="search" name="search" class="filter-control" placeholder="Search activities..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i>
                                <span>Apply Filters</span>
                            </button>
                            <a href="activity_log.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                <span>Clear Filters</span>
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Activity List -->
                <div class="activity-list">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-header">
                                    <div>
                                        <span class="activity-user"><?php echo htmlspecialchars($activity['full_name'] ?: 'Librarian'); ?></span>
                                        <span class="activity-type"><?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?></span>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="activity-description">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                                <?php if (!empty($activity['ip_address'])): ?>
                                    <div class="activity-ip">
                                        <i class="fas fa-network-wired"></i>
                                        <?php echo htmlspecialchars($activity['ip_address']); ?>
                                        <?php if (!empty($activity['user_agent'])): ?>
                                            • <?php echo htmlspecialchars($activity['user_agent']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-activities">
                            <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>No activities found matching your criteria</p>
                            <?php if ($user_filter || $type_filter || $date_from || $date_to || $search): ?>
                                <a href="activity_log.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-times"></i>
                                    <span>Clear filters</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
            
            // Set max date for date_to to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_to').max = today;
            document.getElementById('date_from').max = today;
            
            // Validate date range
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            dateFrom.addEventListener('change', function() {
                dateTo.min = this.value;
            });
            
            dateTo.addEventListener('change', function() {
                dateFrom.max = this.value;
            });
        });
    </script>
</body>
</html>