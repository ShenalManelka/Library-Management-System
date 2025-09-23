<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Library - Login</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --error-color: #f72585;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
   body {
    background-color: var(--light-color);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-image: url('images/libBG.png');
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center center;
}
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .login-header {
            background: var(--primary-color);
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            transform: skewY(-3deg);
            z-index: 1;
        }
        
        .login-form {
            padding: 40px 30px;
            position: relative;
            z-index: 2;
        }
        
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 40px;
            cursor: pointer;
            color: #777;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            color: var(--error-color);
            text-align: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 0 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                margin: 0 15px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="images/Brightway.png" style="width: 100px; height: auto;">
            <h1>Brightway LMS</h1>
            <p>Access your account to continue</p>
        </div>
        
        <form id="loginForm" class="login-form" action="login.php" method="POST">
    <?php if (isset($_GET['error'])): ?>
        <div id="errorMessage" class="error-message" style="display: block;">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php else: ?>
        <div id="errorMessage" class="error-message"></div>
    <?php endif; ?>
    
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" required autofocus
               value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>">
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required>
        <span class="password-toggle" id="togglePassword">👁️</span>
    </div>
    
    <button type="submit" class="btn">Login</button>
    
    <div class="login-footer">
        <p>Forgot your password? Contact Librarian <br><center>or send Email to: </center><br><center>librarian@school.edu</center> </p>
    </div>
</form>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
        
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const errorMessage = document.getElementById('errorMessage');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                errorMessage.textContent = 'Please enter both username and password.';
                errorMessage.style.display = 'block';
            }
        });
    </script>
</body>
</html>