<?php
require_once 'config.php';

$repair_stages = [
    'Received',
    'Diagnosis',
    'Parts order approved',
    'Parts arrived',
    'Ongoing repair',
    'Ready for pickup',
    'Released',
];
$status_stage_fallback = [
    'Pending' => 'Received',
    'In Diagnostics' => 'Diagnosis',
    'Repairing' => 'Ongoing repair',
    'Ready for Release' => 'Ready for pickup',
    'Completed' => 'Released',
    'Cancelled' => 'Received',
];
$ticket = null;
$searched = false;
$ticket_number = trim($_GET['ticket_number'] ?? '');
$email = trim($_GET['email'] ?? '');

if ($ticket_number !== '' && $email !== '') {
    $searched = true;
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE (ticket_number = ? OR legacy_ticket_number = ?) AND email = ?");
    $stmt->execute([$ticket_number, $ticket_number, $email]);
    $ticket = $stmt->fetch();
}

include 'includes/header.php';
?>

<main class="tracking-card <?php echo $ticket ? 'tracking-card-result' : ''; ?>">
    <?php if ($ticket): ?>
        <?php
        $tracking_stage = $ticket['repair_stage'] ?: ($status_stage_fallback[$ticket['status']] ?? 'Received');
        if ($ticket['status'] === 'Completed') {
            $tracking_stage = 'Released';
        }
        $tracking_stage_index = array_search($tracking_stage, $repair_stages, true);
        $tracking_stage_index = $tracking_stage_index === false ? 0 : $tracking_stage_index;
        $tracking_status = $ticket['status'] === 'Cancelled' ? 'Cancelled' : $tracking_stage;
        $is_under_warranty = $ticket['warranty_status'] === 'Under Warranty';
        $estimated_cost_label = $is_under_warranty
            ? 'No charge'
            : ($ticket['estimated_cost'] !== null ? '₱' . number_format((float)$ticket['estimated_cost'], 2) : 'Not yet available');
        $payment_label = $is_under_warranty ? 'NOT REQUIRED' : ($ticket['estimated_cost'] !== null ? 'Due at pickup' : 'Not yet determined');
        ?>
        <header class="tracking-result-heading">
            <h1>Your queue and service reference</h1>
            <p>Please wait for your reference number to be called on the TV display. Save this secure link for repair updates.</p>
        </header>

        <section class="tracking-service-card" aria-label="Repair status and service details">
            <div class="tracking-reference">
                <div>
                    <p class="tracking-reference-label">Reference number</p>
                    <h2><?php echo htmlspecialchars($ticket['ticket_number']); ?></h2>
                </div>
                <span class="tracking-current-status <?php echo $ticket['status'] === 'Cancelled' ? 'is-cancelled' : ''; ?>"><?php echo htmlspecialchars($tracking_status); ?></span>
            </div>

            <ol class="tracking-progress" aria-label="Repair progress">
                <?php foreach ($repair_stages as $index => $stage): ?>
                    <?php $stage_state = $ticket['status'] !== 'Cancelled' && $index < $tracking_stage_index ? 'is-complete' : ($ticket['status'] !== 'Cancelled' && $index === $tracking_stage_index ? 'is-current' : ''); ?>
                    <li class="<?php echo $stage_state; ?>">
                        <span class="tracking-progress-marker"><?php echo $index < $tracking_stage_index && $ticket['status'] !== 'Cancelled' ? '&#10003;' : $index + 1; ?></span>
                        <span class="tracking-progress-label"><?php echo htmlspecialchars($stage); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <dl class="tracking-service-details">
                <div>
                    <dt>Customer</dt>
                    <dd><?php echo htmlspecialchars($ticket['customer_name']); ?></dd>
                </div>
                <div>
                    <dt>Device</dt>
                    <dd><?php echo htmlspecialchars(trim($ticket['brand'] . ' ' . $ticket['model'])); ?></dd>
                </div>
                <div>
                    <dt>Warranty</dt>
                    <dd><?php echo htmlspecialchars($ticket['warranty_status'] ?: 'Pending assessment'); ?></dd>
                </div>
                <div>
                    <dt>Estimated cost</dt>
                    <dd><?php echo htmlspecialchars($estimated_cost_label); ?></dd>
                </div>
                <div>
                    <dt>Payment</dt>
                    <dd><?php echo htmlspecialchars($payment_label); ?></dd>
                </div>
                <div>
                    <dt>Last updated</dt>
                    <dd><?php echo htmlspecialchars(date('n/j/Y, g:i:s A', strtotime($ticket['updated_at']))); ?></dd>
                </div>
            </dl>

            <section class="tracking-detail-block">
                <h3>Reported concern</h3>
                <p><?php echo nl2br(htmlspecialchars($ticket['problem_description'])); ?></p>
            </section>

            <section class="tracking-detail-block">
                <h3>Diagnosis</h3>
                <p><?php echo $ticket['diagnosis'] !== null && $ticket['diagnosis'] !== '' ? nl2br(htmlspecialchars($ticket['diagnosis'])) : 'Diagnosis not available yet.'; ?></p>
            </section>

            <section class="tracking-detail-block">
                <h3>Service Notes</h3>
                <p><?php echo $ticket['repair_notes'] !== null && $ticket['repair_notes'] !== '' ? nl2br(htmlspecialchars($ticket['repair_notes'])) : 'No service notes yet.'; ?></p>
            </section>
        </section>

        <a href="track.php" class="tracking-check-another">Check another reference</a>
    <?php else: ?>
        <p class="tracking-eyebrow">Customer tracking</p>
        <h1>Check your repair status</h1>
        <p class="tracking-description">Enter the reference number and the same email used during admission.</p>

        <form action="track.php" method="GET" class="tracking-form">
            <div class="form-group">
                <label for="ticket-number">Reference number</label>
                <input id="ticket-number" type="text" name="ticket_number" class="form-control" placeholder="SC-20260711-1234" value="<?php echo htmlspecialchars($ticket_number); ?>" required>
            </div>
            <div class="form-group">
                <label for="tracking-email">Email address</label>
                <input id="tracking-email" type="email" name="email" class="form-control" placeholder="name@email.com" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <button type="submit" class="btn-submit tracking-submit">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.8"/>
                    <path d="m15.5 15.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Track ticket
            </button>
        </form>

        <?php if ($searched): ?>
            <div class="alert-danger tracking-alert" role="alert">No service ticket found with that reference number and email address.</div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
