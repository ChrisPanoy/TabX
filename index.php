<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'dean') {
                header("Location: dean/dashboard.php");
            } elseif ($user['role'] === 'panelist') {
                header("Location: panelist/dashboard.php");
            } else {
                header("Location: student/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - TabulationX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-page {
            background: linear-gradient(135deg, #1e40af 0%, #1e1b4b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 3.5rem 3rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .login-logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            background: white;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-card animate-fade-in">
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div class="login-logo">
                <img src="assets/img/logo.png" alt="Logo" style="width: 150%; height: 150%; object-fit: contain;">
            </div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; letter-spacing: -0.02em;">Tabulation<span style="color: var(--primary);">X</span></h1>
            <p style="color: var(--text-light); font-weight: 500;">Secure Access Portal</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 2rem; border-left: 4px solid var(--danger);">
                <span style="font-size: 1.25rem;">🚫</span>
                <span style="font-weight: 600;"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required placeholder=>
            </div>
            <div class="form-group" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <label class="form-label" for="password" style="margin: 0;">Password</label>
                </div>
                <input type="password" id="password" name="password" class="form-control" required placeholder=>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1.125rem; font-size: 1rem; border-radius: var(--radius-lg); box-shadow: 0 10px 15px -3px rgba(59, 66, 243, 0.4);">
                <span style="font-weight: 700;">Sign In to System</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.5rem;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
        </form>
        
        <div style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border); text-align: center;">
            <p style="font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 0.75rem;">Developed By</p>
            <div style="font-weight: 800; color: var(--dark); font-size: 0.9rem; letter-spacing: 1px;">TOPE TAMAD </div>
        </div>
    </div>
</body>
</html>
