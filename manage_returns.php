<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is a librarian
if (!isset($_SESSION["loggedin"])) {
    header("location: home.php?error=You must log in to access this page.");
    exit;
}
if ($_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You don't have permission to access this page.");
    exit;
}

$error = $success = '';
$student_id = $loan_id = '';

// Process return form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = isset($_POST["student_id"]) ? trim($_POST["student_id"]) : '';
    $loan_id = isset($_POST["loan_id"]) ? trim($_POST["loan_id"]) : '';

    if (empty($student_id)) {
        $error = "Please select a student.";
    } elseif (empty($loan_id)) {
        $error = "Please select a book to return.";
    } else {
        // Fetch loan record
        $stmt = $conn->prepare("
    SELECT book_id 
    FROM book_loans 
    WHERE loan_id = ? 
    AND id = ? 
    AND (status = 'checked_out' OR return_date IS NULL)");
$stmt->bind_param("ii", $loan_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $loan = $result->fetch_assoc();
            $book_id = $loan['book_id'];

            // Start transaction
            $conn->begin_transaction();
            try {
                // 1. Mark loan as returned
               $update_loan = $conn->prepare("UPDATE book_loans SET status = 'returned' , return_date = ? WHERE loan_id = ?");
               $update_loan->bind_param("si", $return_date, $loan_id);
               $update_loan->execute();


                // 2. Increase available copies
                $update_book = $conn->prepare("UPDATE books SET available_copies = total_copies + 1 WHERE book_id = ?");
                $update_book->bind_param("i", $book_id);
                $update_book->execute();

                // Commit
                $conn->commit();

                log_activity($_SESSION['id'], 'return', "Returned book ID $book_id from student ID $student_id");

                $success = "Book successfully returned!";
                $student_id = $loan_id = '';
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error processing return: " . $e->getMessage();
            }
        } else {
            $error = "Invalid loan selected.";
        }
    }
}

// Fetch students with active loans
$students = [];
$student_query = $conn->query("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u 
    INNER JOIN book_loans bl ON u.id = bl.id 
    WHERE bl.return_date IS NULL 
    ORDER BY u.full_name
");
while ($row = $student_query->fetch_assoc()) {
    $students[] = $row;
}

// Fetch books on loan for selected student
$loans = [];
if (!empty($student_id)) {
    $loan_query = $conn->prepare("
        SELECT bl.loan_id, b.title, b.author, bl.checkout_date, bl.due_date 
        FROM book_loans bl 
        INNER JOIN books b ON bl.book_id = b.book_id 
        WHERE bl.id = ? AND bl.return_date IS NULL
        ORDER BY bl.checkout_date
    ");
    $loan_query->bind_param("i", $student_id);
    $loan_query->execute();
    $loan_result = $loan_query->get_result();

    while ($row = $loan_result->fetch_assoc()) {
        $loans[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Returns | School Library</title>
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
        
        /* Sidebar Styles (same as your dashboard) */
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
        
        .page-title h2 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .page-title p {
            color: var(--gray);
        }
        
        /* Checkout Form Styles */
        .checkout-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .alert-danger {
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
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
                <a href="librarian_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="new_checkout.php" class="nav-item">
                    <i class="fas fa-book-medical"></i>
                    <span>New Checkout</span>
                </a>
                <a href="manage_returns.php" class="nav-item active">
                    <i class="fas fa-book-arrow-up"></i>
                    <span>Manage Returns</span>
                </a>
                <a href="checkouts.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Manage Books</span>
                </a>
                <a href="students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Students</span>
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
                <div class="page-title">
                    <h2>Manage Book Returns</h2>
                    <p>Return books checked out by students</p>
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
            
            <!-- Return Form -->
            <div class="checkout-form">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="student_id">Student</label>
                        <select name="student_id" id="student_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Select a student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($student_id == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name'] . " (" . $student['id'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($student_id) && !empty($loans)): ?>
                        <div class="form-group">
                            <label for="loan_id">Books on Loan</label>
                            <select name="loan_id" id="loan_id" class="form-control" required>
                                <option value="">Select a book to return</option>
                                <?php foreach ($loans as $loan): ?>
                                    <option value="<?php echo $loan['loan_id']; ?>">
                                        <?php echo htmlspecialchars($loan['title'] . " by " . $loan['author'] . " (Due: " . date('M j, Y', strtotime($loan['due_date'])) . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-block">
                                <i class="fas fa-book-arrow-up"></i> Return Book
                            </button>
                        </div>
                    <?php elseif (!empty($student_id)): ?>
                        <div class="alert alert-info">This student has no books to return.</div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
