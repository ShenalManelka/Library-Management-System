<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in as librarian
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "librarian") {
    header("location: home.php?error=You must log in as a librarian to access this page.");
    exit;
}

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php?error=Invalid student ID");
    exit;
}

$student_id = $_GET['id'];

// Fetch student name for activity log
$query = "SELECT full_name FROM users WHERE id = $student_id AND role = 'student'";
$result = $conn->query($query);
$student = $result->fetch_assoc();

if (!$student) {
    header("Location: students.php?error=Student not found");
    exit;
}

// Delete student
$query = "DELETE FROM users WHERE id = $student_id";
if ($conn->query($query)) {
    // Log activity
    $activity = "Deleted student: " . $student['full_name'];
    $conn->query("INSERT INTO activity_log (activity_type, description) VALUES ('student_deleted', '$activity')");
    
    header("Location: students.php?success=Student deleted successfully");
} else {
    header("Location: students.php?error=Failed to delete student: " . urlencode($conn->error));
}

$conn->close();
exit;
?>