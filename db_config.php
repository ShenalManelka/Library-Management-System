<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'school_lms');

// Connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
function log_activity($user_id, $activity_type, $description) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO activity_log (id, activity_type, description, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", 
        $user_id, 
        $activity_type,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    );
    $stmt->execute();
    $stmt->close();
}
?>