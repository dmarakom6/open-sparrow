<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/includes/db.php';
    require __DIR__ . '/includes/api_helpers.php';
    $conn = db_connect();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql = 'SELECT id, password_hash FROM "app"."users" WHERE username = $1';
    $res = pg_query_params($conn, $sql, [$username]);

    if (!$res) {
        $error = 'Technical error. Contact administrator.';
    } else {
        $user = pg_fetch_assoc($res);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            
            // Log login action
            log_user_action($conn, $user['id'], 'LOGIN');
            
            header("Location: dashboard.php"); 
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Login | OpenSparrow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="assets/css/styles.css" rel="stylesheet" /> 
    <style>
        body { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            background: var(--bg, #F1F1F1); 
            margin: 0;
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
        }
        .login-box { 
            background: var(--panel, #ffffff); 
            padding: 2.5rem 2rem; 
            border-radius: var(--radius-lg, 10px); 
            box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,.10)); 
            width: 100%; 
            max-width: 360px; 
            box-sizing: border-box;
        }
        .login-box h2 { 
            margin-top: 0; 
            color: var(--accent-dark, #003366); 
            text-align: center; 
            margin-bottom: 1.5rem;
        }
        .login-box input { 
            width: 100%; 
            padding: 0.85rem; 
            margin-bottom: 1rem; 
            border: 1px solid var(--border, #AAB8C2); 
            border-radius: var(--radius, 6px); 
            font-size: 14px; 
            box-sizing: border-box;
            transition: border-color 150ms ease;
        }
        .login-box input:focus { 
            outline: none; 
            border-color: var(--accent, #007ACC); 
            box-shadow: 0 0 0 2px rgba(0,122,204,.15);
        }
        .login-box button { 
            width: 100%; 
            justify-content: center; 
            padding: 0.85rem; 
            background: var(--accent, #007ACC); 
            color: white; 
            border: none; 
            font-size: 15px; 
            font-weight: 500;
            border-radius: var(--radius, 6px);
            cursor: pointer; 
            transition: background 150ms ease; 
        }
        .login-box button:hover { 
            background: var(--accent-dark, #003366); 
        }
        .error { 
            color: var(--danger, #dc2626); 
            font-size: 13.5px; 
            text-align: center; 
            margin-bottom: 1rem; 
            background: #fef2f2;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #fca5a5;
        }
    </style>
</head>
<body>
    <div class="login-box">
	<center><img src="assets/img/logo-brown.png" alt="Logo" class="footer-logo" height="48" /></center>
        <h2>OpenSparrow</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Login" required autofocus autocomplete="username" />
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password" />
            <button type="submit">Submit</button>
        </form>
    </div>
</body>
</html>