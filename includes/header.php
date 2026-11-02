<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Device - Apple Service Center Workflow</title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/style.css'); ?>">
</head>
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_home_page = $current_page === 'index.php';
$is_registration_page = $current_page === 'register.php';
$is_tracking_page = $current_page === 'track.php';
$is_login_page = $current_page === 'login.php';
$is_success_page = $current_page === 'success.php';
$is_dashboard_page = $current_page === 'dashboard.php';
$is_staff_workspace = in_array($current_page, ['dashboard.php', 'users.php', 'reports.php', 'tv_display.php', 'ticket.php', 'staff_counter.php'], true);
$is_release_page = $is_registration_page && ($_GET['service_type'] ?? $_POST['service_type'] ?? '') === 'for_release';
$body_classes = [];
if ($is_home_page) {
    $body_classes[] = 'home-page';
}
if ($is_registration_page && !$is_release_page) {
    $body_classes[] = 'registration-page';
}
if ($is_release_page) {
    $body_classes[] = 'release-page';
}
if ($is_tracking_page) {
    $body_classes[] = 'tracking-page';
}
if ($is_login_page) {
    $body_classes[] = 'staff-login-page';
}
if ($is_success_page) {
    $body_classes[] = 'success-page';
}
if ($is_staff_workspace) {
    $body_classes[] = 'dashboard-page';
}
?>
<body class="<?php echo implode(' ', $body_classes); ?>">
    <?php if (!$is_login_page && !$is_staff_workspace && !$is_success_page): ?>
    <nav class="navbar">
        <a href="index.php" class="brand">
            <div class="brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3.5 17.5a2.1 2.1 0 0 0 3 3l4.8-4.8a5 5 0 0 0 6.4-6.4l-3.1 3.1-3.1-3.1 3.2-3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="brand-text">
                <h1>Fix Device</h1>
                <span>Service Center</span>
            </div>
        </a>
        <div class="nav-links">
            <?php if ($is_home_page): ?>
                <a href="login.php" class="nav-btn secondary">Staff login</a>
            <?php elseif ($is_release_page): ?>
                <a href="index.php" class="nav-btn secondary release-back">
                    <span aria-hidden="true">&larr;</span> Back
                </a>
            <?php elseif ($is_tracking_page): ?>
                <a href="register.php?step=1" class="nav-btn secondary tracking-new-admission">New admission</a>
            <?php else: ?>
                <a href="index.php" class="nav-btn secondary">Home</a>
                <a href="track.php" class="nav-btn secondary nav-track">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.8"/>
                        <path d="m15.5 15.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Track repair
                </a>
                <?php if(isset($_SESSION['staff_logged_in'])): ?>
                    <a href="dashboard.php" class="nav-btn primary">Dashboard</a>
                    <a href="logout.php" class="nav-btn secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-btn primary">Staff Login</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
    <div class="container">
