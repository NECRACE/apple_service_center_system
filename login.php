<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

include 'includes/header.php';
?>

<div class="login-wrapper">
    <!-- Left Dark Banner -->
    <div class="login-banner">
        <a href="index.php" class="login-brand">
            <span class="login-brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3.5 17.5a2.1 2.1 0 0 0 3 3l4.8-4.8a5 5 0 0 0 6.4-6.4l-3.1 3.1-3.1-3.1 3.2-3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="login-brand-text">
                <strong>Fix Device</strong>
                <small>Service Center</small>
            </span>
        </a>
        <div class="login-banner-copy">
            <p class="login-eyebrow">Service Operations</p>
            <h1>Repair management that feels effortless.</h1>
            <p>Receive devices, track every repair, and give customers a professional service experience.</p>
        </div>
    </div>

    <!-- Right Login Form -->
    <div class="login-form-container">
        <div class="login-card">
            <p class="login-card-eyebrow">Staff Portal</p>
            <h2>Welcome back</h2>
            <p class="login-card-description">Sign in to manage admissions and repairs.</p>

            <?php if (!empty($error)): ?>
                <div class="alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="login-input-wrap password-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        <input id="username" type="text" name="username" class="form-control" value="admin" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="login-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        <input id="password" type="password" name="password" class="form-control" value="admin123" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
                            <svg class="password-eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                            <svg class="password-eye-closed" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a17 17 0 0 1-3.1 3.6M6.2 6.2C3.9 7.8 2.5 12 2.5 12s3.4 6 9.5 6c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Sign in</button>
            </form>

            <a href="index.php" class="login-admission-link">Open customer admission form</a>
        </div>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');

    passwordToggle.addEventListener('click', function () {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
    });
</script>

<?php include 'includes/footer.php'; ?>
