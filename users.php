<?php
require_once 'config.php';

if (!isset($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$users = $pdo->query("SELECT username, full_name, role, created_at FROM users ORDER BY id")->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php $active_dashboard_nav = 'users'; include 'includes/dashboard_sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-heading">
            <div>
                <p class="dashboard-eyebrow">Administration</p>
                <h1>User Access</h1>
                <p>Review staff accounts and their access roles.</p>
            </div>
        </div>

        <section class="dashboard-ticket-panel">
            <div class="ticket-panel-heading">
                <div>
                    <h2>Staff accounts</h2>
                    <p>Accounts currently registered in the service center.</p>
                </div>
            </div>
            <div class="dashboard-table-scroll">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Access role</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><span class="ticket-status <?php echo $user['role'] === 'admin' ? 'status-ready-for-release' : ''; ?>"><?php echo $user['role'] === 'admin' ? 'Admin' : 'Staff'; ?></span></td>
                                <td><?php echo htmlspecialchars(date('n/j/Y', strtotime($user['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
