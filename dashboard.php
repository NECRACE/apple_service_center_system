<?php
require_once 'config.php';

if (!isset($_SESSION['staff_logged_in'])) {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$valid_statuses = ['Pending', 'In Diagnostics', 'Repairing', 'Ready for Release', 'Completed', 'Cancelled'];
$status_labels = [
    'Pending' => 'Pending acceptance',
    'In Diagnostics' => 'For diagnosis',
    'Repairing' => 'Ongoing repair',
    'Ready for Release' => 'Ready for pickup',
    'Completed' => 'Released',
    'Cancelled' => 'Cancelled',
];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = '';
}

$total_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$received_today = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$active_repairs = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('Completed', 'Cancelled')")->fetchColumn();
$ready_for_pickup = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Ready for Release'")->fetchColumn();

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(ticket_number LIKE ? OR customer_name LIKE ? OR phone_number LIKE ? OR model LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}
if ($status_filter !== '') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}
$ticket_sql = "SELECT * FROM tickets";
if ($where) {
    $ticket_sql .= " WHERE " . implode(' AND ', $where);
}
$ticket_sql .= " ORDER BY id DESC";
$ticket_stmt = $pdo->prepare($ticket_sql);
$ticket_stmt->execute($params);
$tickets = $ticket_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php $active_dashboard_nav = 'dashboard'; include 'includes/dashboard_sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-heading">
            <div>
                <p class="dashboard-eyebrow">Operations overview</p>
                <h1>Service dashboard</h1>
                <p>Monitor admissions and move repairs through every stage.</p>
            </div>
            <a href="register.php?step=1" class="dashboard-create">Create admission</a>
        </div>

        <?php if (isset($msg)): ?>
            <div class="alert-success dashboard-message"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <section class="dashboard-stats" aria-label="Service overview">
            <article class="dashboard-stat">
                <span class="stat-icon ticket-stat-icon" aria-hidden="true">▱</span>
                <div><span>Total tickets</span><strong><?php echo $total_tickets; ?></strong></div>
            </article>
            <article class="dashboard-stat">
                <span class="stat-icon today-stat-icon" aria-hidden="true">◷</span>
                <div><span>Received today</span><strong><?php echo $received_today; ?></strong></div>
            </article>
            <article class="dashboard-stat">
                <span class="stat-icon active-stat-icon" aria-hidden="true">∿</span>
                <div><span>Active repairs</span><strong><?php echo $active_repairs; ?></strong></div>
            </article>
            <article class="dashboard-stat">
                <span class="stat-icon ready-stat-icon" aria-hidden="true">✓</span>
                <div><span>Ready for pickup</span><strong><?php echo $ready_for_pickup; ?></strong></div>
            </article>
        </section>

        <section class="dashboard-ticket-panel" id="service-tickets">
            <div class="ticket-panel-heading">
                <div>
                    <h2>Service tickets</h2>
                    <p>Search by reference, customer, phone, or model.</p>
                </div>
                <form action="dashboard.php" method="GET" class="ticket-filter-form">
                    <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="⌕  Search tickets..." aria-label="Search tickets">
                    <select name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <?php foreach ($valid_statuses as $status_option): ?>
                            <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $status_filter === $status_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_labels[$status_option]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Search</button>
                </form>
            </div>

            <div class="dashboard-table-scroll">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th>Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$tickets): ?>
                            <tr><td colspan="6" class="dashboard-empty">No service tickets found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php
                            $status_class = 'status-' . strtolower(str_replace(' ', '-', $t['status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['ticket_number']); ?></strong></td>
                                <td>
                                    <span class="ticket-customer-name"><?php echo htmlspecialchars($t['customer_name']); ?></span>
                                    <small><?php echo htmlspecialchars($t['phone_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($t['brand'] . ' ' . $t['model']); ?></td>
                                <td><span class="ticket-status <?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars($t['repair_stage'] ?? ($status_labels[$t['status']] ?? $t['status'])); ?></span></td>
                                <td><?php echo htmlspecialchars(date('n/j/Y', strtotime($t['created_at']))); ?></td>
                                <td><a class="ticket-open-link" href="ticket.php?id=<?php echo (int)$t['id']; ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
