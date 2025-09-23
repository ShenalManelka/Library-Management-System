<?php
// db_verify.php
$conn = new mysqli('localhost', 'root', '', 'school_lms');
$result = $conn->query("SELECT username, password FROM users WHERE username = 'librarian1'");
$user = $result->fetch_assoc();

echo "<h2>Database Verification</h2>";
echo "<pre>User Record: "; print_r($user); echo "</pre>";

// Verify password
if ($user && password_verify('password123', $user['password'])) {
    echo "<p style='color:green'>Password verification SUCCESS</p>";
} else {
    echo "<p style='color:red'>Password verification FAILED</p>";
    if ($user) {
        echo "<p>Stored hash: ".$user['password']."</p>";
        echo "<p>Expected hash for 'password123': ".password_hash('password123', PASSWORD_DEFAULT)."</p>";
    }
}
$conn->close();
?>