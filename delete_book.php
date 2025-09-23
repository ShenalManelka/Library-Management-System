<?php
// delete_book.php - Script to delete a book from the database

// Include database connection
require_once 'db_config.php';

// Check if book ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $book_id = $_GET['id'];
    
    try {
        // Prepare the delete statement
        $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        
        // Bind parameters
        $stmt->bind_param("i", $book_id);
        
        // Execute the query
        if ($stmt->execute()) {
            // Check if any row was affected
            if ($stmt->affected_rows > 0) {
                header("Location: books.php?delete_success=1");
                exit();
            } else {
                header("Location: books.php?error=no_book_found");
                exit();
            }
        } else {
            header("Location: books.php?error=delete_failed");
            exit();
        }
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Delete Book Error: " . $e->getMessage());
        header("Location: books.php?error=database_error");
        exit();
    }
} else {
    // No ID provided, redirect back
    header("Location: books.php?error=invalid_id");
    exit();
}

// Close connection
$conn->close();
?>