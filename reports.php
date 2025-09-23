<?php
session_start();
require_once 'db_config.php';

// Check librarian session
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Fetch stats for reports
$stats = [];
$stats['total_books'] = $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0];
$stats['active_checkouts'] = $conn->query("SELECT COUNT(*) FROM book_loans WHERE status = 'checked_out'")->fetch_row()[0];
$stats['returned_books'] = $conn->query("SELECT COUNT(*) FROM book_loans WHERE status = 'returned'")->fetch_row()[0];
$stats['overdue_books'] = $conn->query("SELECT COUNT(*) FROM book_loans WHERE status = 'checked_out' AND due_date < NOW()")->fetch_row()[0];

// Fetch monthly checkout counts
$monthlyData = [];
$result = $conn->query("
    SELECT DATE_FORMAT(checkout_date, '%Y-%m') as month, COUNT(*) as count
    FROM book_loans
    GROUP BY month
    ORDER BY month ASC
");
while ($row = $result->fetch_assoc()) {
    $monthlyData[] = $row;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="library_report.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, ["Metric", "Value"]);
    foreach ($stats as $key => $val) {
        fputcsv($output, [ucwords(str_replace("_"," ",$key)), $val]);
    }
    fclose($output);
    exit;
}

// Export PDF (basic)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require('fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Brightway LMS Report',0,1,'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial','',12);
    foreach ($stats as $key => $val) {
        $pdf->Cell(0,10,ucwords(str_replace("_"," ",$key)).": ".$val,0,1);
    }
    $pdf->Output('D','library_report.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | School Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        <?php // reuse the same CSS from your dashboard ?>
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
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
        body{background-color:#f5f7fa;color:var(--dark);line-height:1.6;}
        .dashboard-container{display:grid;grid-template-columns:250px 1fr;min-height:100vh;}
        .sidebar{background:var(--primary);color:white;padding:1.5rem;box-shadow:2px 0 10px rgba(0,0,0,0.1);}
        .brand{display:flex;align-items:center;gap:.75rem;margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid rgba(255,255,255,0.1);}
        .brand i{font-size:1.5rem;}
        .brand h1{font-size:1.25rem;font-weight:600;}
        .nav-menu{display:flex;flex-direction:column;gap:.5rem;}
        .nav-item{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:8px;color:white;text-decoration:none;transition:.3s;}
        .nav-item:hover,.nav-item.active{background:rgba(255,255,255,0.1);}
        .nav-item i{width:20px;text-align:center;}
        .main-content{padding:2rem;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;}
        .welcome-message h2{font-size:1.5rem;color:var(--dark);margin-bottom:.25rem;}
        .welcome-message p{color:var(--gray);}
        .user-profile{display:flex;align-items:center;gap:1rem;}
        .avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;}
        .logout-btn{background:none;border:none;color:var(--danger);cursor:pointer;display:flex;align-items:center;gap:.5rem;font-size:.9rem;}
        .logout-btn:hover{text-decoration:underline;}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:1.5rem;margin-bottom:2rem;}
        .stat-card{background:white;border-radius:10px;padding:1.5rem;box-shadow:0 4px 6px rgba(0,0,0,0.05);transition:.3s;}
        .stat-card:hover{transform:translateY(-5px);box-shadow:0 10px 15px rgba(0,0,0,0.1);}
        .stat-card h3{font-size:.9rem;color:var(--gray);margin-bottom:.5rem;}
        .stat-card .value{font-size:2rem;font-weight:700;color:var(--primary);}
        .report-actions{margin-bottom:2rem;}
        .report-actions a{background:var(--accent);color:white;padding:.6rem 1rem;margin-right:.5rem;border-radius:6px;text-decoration:none;transition:.3s;}
        .report-actions a:hover{background:var(--secondary);}
        .chart-container{background:white;padding:1.5rem;border-radius:10px;box-shadow:0 4px 6px rgba(0,0,0,0.05);}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <i class="fas fa-book-open"></i>
                <a href="librarian_dashboard.php" style="text-decoration:none;color:inherit;">
                    <h1>Brightway LMS</h1>
                </a>
            </div>
            <nav class="nav-menu">
                <a href="librarian_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
                <a href="books.php" class="nav-item"><i class="fas fa-book"></i><span>Books</span></a>
                <a href="students.php" class="nav-item"><i class="fas fa-users"></i><span>Students</span></a>
                <a href="checkouts.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Checkouts</span></a>
                <a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
            </nav>
            <?php include 'date_time.php'; ?>
        </aside>

        <!-- Main -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Reports & Analytics</h2>
                    <p>Library Performance Overview</p>
                </div>
                <div class="user-profile">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?></div>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="report-actions">
                <a href="reports.php?export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>
                <a href="reports.php?export=pdf"><i class="fas fa-file-pdf"></i> Export PDF</a>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card"><h3>Total Books</h3><div class="value"><?php echo $stats['total_books']; ?></div></div>
                <div class="stat-card"><h3>Active Checkouts</h3><div class="value"><?php echo $stats['active_checkouts']; ?></div></div>
                <div class="stat-card"><h3>Returned Books</h3><div class="value"><?php echo $stats['returned_books']; ?></div></div>
                <div class="stat-card"><h3>Overdue Books</h3><div class="value"><?php echo $stats['overdue_books']; ?></div></div>
            </div>

            <!-- Charts -->
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyData,'month')); ?>,
                datasets: [{
                    label: 'Monthly Checkouts',
                    data: <?php echo json_encode(array_column($monthlyData,'count')); ?>,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67,97,238,0.1)',
                    fill: true,
                    tension: 0.3
                }]
            }
        });
    </script>
</body>
</html>
