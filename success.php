<?php
require_once 'config.php';

$ticket_number = trim($_GET['ticket'] ?? '');
$ticket = null;

if ($ticket_number !== '') {
    $stmt = $pdo->prepare("SELECT ticket_number, customer_name, brand, model, problem_description, status, created_at, updated_at FROM tickets WHERE (ticket_number = ? OR legacy_ticket_number = ?) AND service_type = 'new_service'");
    $stmt->execute([$ticket_number, $ticket_number]);
    $ticket = $stmt->fetch();
}

include 'includes/header.php';
?>

<main class="admission-confirmation">
    <?php if (!$ticket): ?>
        <p class="confirmation-intro">We could not find that admission reference.</p>
        <section class="confirmation-card confirmation-not-found">
            <h1>Admission not found</h1>
            <p>Please check your reference number or start a new admission.</p>
        </section>
        <div class="confirmation-actions">
            <a href="register.php?step=1" class="confirmation-button confirmation-button-primary">New admission</a>
        </div>
    <?php else: ?>
        <p class="confirmation-intro">Please wait for your reference number to be called on the TV display. Save this secure link for repair updates.</p>

        <?php
        $status_label = $ticket['status'] === 'Pending' ? 'Pending acceptance' : $ticket['status'];
        $is_pending = $ticket['status'] === 'Pending';
        ?>
        <section class="confirmation-card">
            <div class="confirmation-reference">
                <div>
                    <p class="confirmation-eyebrow">Reference number</p>
                    <h1><?php echo htmlspecialchars($ticket['ticket_number']); ?></h1>
                </div>
                <span class="confirmation-status"><?php echo htmlspecialchars($status_label); ?></span>
            </div>

            <div class="confirmation-state">
                <h2><?php echo $is_pending ? 'Waiting for service admission' : 'Service admission received'; ?></h2>
                <p><?php echo $is_pending ? 'Your queue request is registered. The repair workflow starts after staff acceptance and your signature.' : 'Your repair request is registered. Save this reference to check for service updates.'; ?></p>
            </div>

            <dl class="confirmation-details">
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
                    <dd>Pending assessment</dd>
                </div>
                <div>
                    <dt>Estimated cost</dt>
                    <dd>Not yet available</dd>
                </div>
                <div>
                    <dt>Payment</dt>
                    <dd>Not required</dd>
                </div>
                <div>
                    <dt>Last updated</dt>
                    <dd><?php echo htmlspecialchars(date('n/j/Y, g:i:s A', strtotime($ticket['updated_at']))); ?></dd>
                </div>
                <div class="confirmation-concern">
                    <dt>Reported concern</dt>
                    <dd><?php echo nl2br(htmlspecialchars($ticket['problem_description'])); ?></dd>
                </div>
            </dl>
        </section>

        <div class="confirmation-actions">
            <button type="button" class="confirmation-button confirmation-button-primary" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M17 11h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                </svg>
                Print reference slip
            </button>
            <a href="register.php?step=1" class="confirmation-button confirmation-button-secondary">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1V10Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                New admission
            </a>
        </div>

        <?php
        $print_status_labels = [
            'Pending' => 'PENDING_ACCEPTANCE',
            'In Diagnostics' => 'FOR_DIAGNOSIS',
            'Repairing' => 'ONGOING_REPAIR',
            'Ready for Release' => 'READY_FOR_PICKUP',
            'Completed' => 'RELEASED',
            'Cancelled' => 'CANCELLED',
        ];
        ?>
        <section class="reference-slip" aria-label="Printable reference slip">
            <header class="reference-slip-header">
                <h1>FIX DEVICE</h1>
                <p>SERVICE CENTER</p>
                <h2>REFERENCE SLIP</h2>
            </header>
            <div class="reference-slip-reference">
                <p>REFERENCE NUMBER</p>
                <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
            </div>
            <dl class="reference-slip-details">
                <div>
                    <dt>Customer:</dt>
                    <dd><?php echo htmlspecialchars($ticket['customer_name']); ?></dd>
                </div>
                <div>
                    <dt>Date:</dt>
                    <dd><?php echo htmlspecialchars(date('M j, Y \a\t h:i A', strtotime($ticket['created_at']))); ?></dd>
                </div>
                <div>
                    <dt>Status:</dt>
                    <dd><?php echo htmlspecialchars($print_status_labels[$ticket['status']] ?? strtoupper(str_replace(' ', '_', $ticket['status']))); ?></dd>
                </div>
            </dl>
            <footer class="reference-slip-footer">
                <p>Please keep this reference slip.</p>
                <p>Present it when claiming your device.</p>
            </footer>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
