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

$status_report = $pdo->query(
    "SELECT status, COUNT(*) AS ticket_count
     FROM tickets
     GROUP BY status
     ORDER BY FIELD(status, 'Pending', 'In Diagnostics', 'Repairing', 'Ready for Release', 'Completed', 'Cancelled')"
)->fetchAll();
$total_reported_tickets = array_sum(array_map('intval', array_column($status_report, 'ticket_count')));

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php $active_dashboard_nav = 'reports'; include 'includes/dashboard_sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-heading">
            <div>
                <p class="dashboard-eyebrow">Operations overview</p>
                <h1>Reports</h1>
                <p>Summary of service tickets by their current repair status.</p>
            </div>
        </div>

        <section class="dashboard-stats report-summary" aria-label="Ticket report summary">
            <article class="dashboard-stat">
                <span class="stat-icon ticket-stat-icon" aria-hidden="true">▱</span>
                <div><span>Total tickets</span><strong><?php echo $total_reported_tickets; ?></strong></div>
            </article>
        </section>

        <section class="dashboard-ticket-panel">
            <div class="ticket-panel-heading">
                <div>
                    <h2>Tickets by status</h2>
                    <p>Current count for each service stage.</p>
                </div>
            </div>
            <div class="dashboard-table-scroll">
                <table class="dashboard-table">
                    <thead>
                        <tr><th>Status</th><th>Ticket count</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_report as $row): ?>
                            <tr>
                                <td><span class="ticket-status"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td><strong><?php echo (int)$row['ticket_count']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$status_report): ?>
                            <tr><td colspan="2" class="dashboard-empty">No service tickets to report yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
