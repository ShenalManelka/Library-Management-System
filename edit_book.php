<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in as librarian
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Check if book ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: books.php?error=Invalid book ID");
    exit;
}

$book_id = $_GET['id'];

// Initialize variables
$book = [];
$errors = [];
$success = '';

// Fetch book data from database
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("location: books.php?error=Book not found");
    exit;
}

$book = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $publisher = trim($_POST['publisher']);
    $publication_year = trim($_POST['publication_year']);
    $genre = trim($_POST['genre']);
    $edition = trim($_POST['edition']);
    $pages = trim($_POST['pages']);
    $description = trim($_POST['description']);
    $shelf_location = trim($_POST['shelf_location']);
    $total_copies = trim($_POST['total_copies']);
    
    // Calculate available copies based on current checkouts
    $stmt = $conn->prepare("SELECT COUNT(*) FROM book_loans WHERE book_id = ? AND status = 'checked_out'");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $checked_out = $result->fetch_row()[0];
    $stmt->close();
    
    $available_copies = $total_copies - $checked_out;
    if ($available_copies < 0) {
        $errors['total_copies'] = "Cannot reduce total copies below currently checked out count ($checked_out)";
    }
    
    // Validate required fields
    if (empty($title)) {
        $errors['title'] = "Title is required";
    }
    if (empty($author)) {
        $errors['author'] = "Author is required";
    }
    if (empty($total_copies) || !is_numeric($total_copies) || $total_copies < 1) {
        $errors['total_copies'] = "Valid number of copies is required";
    }
    
    // Handle file upload
    $cover_image = $book['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/book_covers/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['cover_image']['type'];
        if (!in_array($file_type, $allowed_types)) {
            $errors['cover_image'] = "Only JPG, PNG, and GIF images are allowed";
        }
        
        if ($_FILES['cover_image']['size'] > 5000000) { // 5MB max
            $errors['cover_image'] = "Image size must be less than 5MB";
        }
        
        if (empty($errors['cover_image'])) {
            $file_ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'cover_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                // Delete old cover image if it exists
                if (!empty($book['cover_image']) && file_exists($book['cover_image'])) {
                    unlink($book['cover_image']);
                }
                $cover_image = $upload_path;
            } else {
                $errors['cover_image'] = "Failed to upload image";
            }
        }
    }
    
    // Handle cover image removal
    if (isset($_POST['remove_cover']) && $_POST['remove_cover'] == '1') {
        if (!empty($book['cover_image']) && file_exists($book['cover_image'])) {
            unlink($book['cover_image']);
        }
        $cover_image = null;
    }
    
    // If no errors, update database
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE books SET title=?, author=?, isbn=?, publisher=?, publication_year=?, genre=?, edition=?, pages=?, description=?, cover_image=?, total_copies=?, available_copies=?, shelf_location=? WHERE book_id=?");
        $stmt->bind_param("ssssssssssiiss", $title, $author, $isbn, $publisher, $publication_year, $genre, $edition, $pages, $description, $cover_image, $total_copies, $available_copies, $shelf_location, $book_id);
        
        if ($stmt->execute()) {
            $success = "Book updated successfully!";
            // Refresh book data
            $book['title'] = $title;
            $book['author'] = $author;
            $book['isbn'] = $isbn;
            $book['publisher'] = $publisher;
            $book['publication_year'] = $publication_year;
            $book['genre'] = $genre;
            $book['edition'] = $edition;
            $book['pages'] = $pages;
            $book['description'] = $description;
            $book['cover_image'] = $cover_image;
            $book['total_copies'] = $total_copies;
            $book['available_copies'] = $available_copies;
            $book['shelf_location'] = $shelf_location;
            
            // Log activity
            log_activity($_SESSION['id'], 'book_updated', 'Updated book: ' . $title);
        } else {
            $errors['database'] = "Error updating book: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book | School Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse all styles from add_book.php */
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
        
        /* Form Styles */
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .form-header {
            margin-bottom: 1.5rem;
        }
        
        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-col {
            flex: 1;
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .success-message {
            color: var(--success);
            background: #d4edda;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.25rem;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 1rem;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #e11d48;
        }
        
        .preview-image {
            max-width: 150px;
            max-height: 200px;
            margin-top: 0.5rem;
        }
        
        .current-image {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .remove-cover {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--danger);
            cursor: pointer;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
                <a href="add_book.php" class="nav-item">
                    <i class="fas fa-plus"></i>
                    <span>Add Book</span>
                </a>
                <a href="students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="checkouts.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Checkouts</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content Area -->
        <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h2>Edit Book</h2>
                    <p>Update the details of this book</p>
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
            
            <!-- Form Section -->
            <div class="form-section">
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['database'])): ?>
                    <div class="error-message" style="margin-bottom: 1rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $errors['database']; ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="edit_book.php?id=<?php echo $book_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" id="title" name="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['title']); ?>" required>
                                <?php if (!empty($errors['title'])): ?>
                                    <div class="error-message"><?php echo $errors['title']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="author" class="form-label">Author *</label>
                                <input type="text" id="author" name="author" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['author']); ?>" required>
                                <?php if (!empty($errors['author'])): ?>
                                    <div class="error-message"><?php echo $errors['author']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" id="isbn" name="isbn" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['isbn']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="publisher" class="form-label">Publisher</label>
                                <input type="text" id="publisher" name="publisher" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['publisher']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                                <img id="imagePreview" src="#" alt="Preview" class="preview-image" style="display:none;">
                                
                                <?php if (!empty($book['cover_image'])): ?>
                                <div class="current-image">
                                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Current Cover" class="preview-image">
                                    <div class="remove-cover" onclick="document.getElementById('removeCover').value = '1'; this.parentElement.style.display = 'none';">
                                        <i class="fas fa-trash"></i>
                                        <span>Remove current cover</span>
                                    </div>
                                    <input type="hidden" id="removeCover" name="remove_cover" value="0">
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($errors['cover_image'])): ?>
                                    <div class="error-message"><?php echo $errors['cover_image']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="total_copies" class="form-label">Total Copies *</label>
                                <input type="number" id="total_copies" name="total_copies" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['total_copies']); ?>" min="1" required>
                                <?php if (!empty($errors['total_copies'])): ?>
                                    <div class="error-message"><?php echo $errors['total_copies']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Available Copies</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['available_copies']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="publication_year" class="form-label">Publication Year</label>
                                <input type="number" id="publication_year" name="publication_year" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['publication_year']); ?>" min="1000" max="<?php echo date('Y'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="genre" class="form-label">Genre</label>
                                <input type="text" id="genre" name="genre" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['genre']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="edition" class="form-label">Edition</label>
                                <input type="text" id="edition" name="edition" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['edition']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="pages" class="form-label">Number of Pages</label>
                                <input type="number" id="pages" name="pages" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['pages']); ?>" min="1">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="shelf_location" class="form-label">Shelf Location</label>
                                <input type="text" id="shelf_location" name="shelf_location" class="form-control" 
                                       value="<?php echo htmlspecialchars($book['shelf_location']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($book['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>
                        <a href="books.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                        <a href="delete_book.php?id=<?php echo $book_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this book?');">
                            <i class="fas fa-trash"></i>
                            <span>Delete Book</span>
                        </a>
                    </div>
                </form>
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
            
            // Image preview for cover image
            document.getElementById('cover_image').addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreview');
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                if (file) {
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>