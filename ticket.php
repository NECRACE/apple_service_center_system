<?php
require_once 'config.php';

if (!isset($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

$ticket_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ticket_id) {
    http_response_code(400);
    exit('A valid ticket ID is required.');
}

$repair_stages = [
    'Received',
    'Diagnosis',
    'Parts order approved',
    'Parts arrived',
    'Ongoing repair',
    'Ready for pickup',
];
$stage_statuses = [
    'Received' => 'Pending',
    'Diagnosis' => 'In Diagnostics',
    'Parts order approved' => 'In Diagnostics',
    'Parts arrived' => 'In Diagnostics',
    'Ongoing repair' => 'Repairing',
    'Ready for pickup' => 'Ready for Release',
];
$valid_warranty_statuses = ['Pending assessment', 'Under Warranty', 'Not Warranty'];
$valid_priorities = ['Low', 'Normal', 'High', 'Urgent'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_processing'])) {
    $repair_stage = $_POST['repair_stage'] ?? '';
    $new_status = $stage_statuses[$repair_stage] ?? '';
    $warranty_status = $_POST['warranty_status'] ?? 'Pending assessment';
    $priority = $_POST['priority'] ?? 'Normal';
    $assigned_technician = trim($_POST['assigned_technician'] ?? '');
    $estimated_cost_input = trim($_POST['estimated_cost'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $repair_notes = trim($_POST['repair_notes'] ?? '');
    $signature = $_POST['customer_signature'] ?? '';
    $clear_signature = ($_POST['clear_signature'] ?? '') === '1';

    if (!in_array($repair_stage, $repair_stages, true)) {
        $error = 'Choose a valid repair stage.';
    } elseif (!in_array($warranty_status, $valid_warranty_statuses, true)) {
        $error = 'Choose a valid warranty option.';
    } elseif (!in_array($priority, $valid_priorities, true)) {
        $error = 'Choose a valid repair priority.';
    } elseif (strlen($assigned_technician) > 100) {
        $error = 'Technician name must be 100 characters or fewer.';
    } elseif ($warranty_status === 'Not Warranty' && $estimated_cost_input !== '' && (!is_numeric($estimated_cost_input) || (float)$estimated_cost_input < 0 || (float)$estimated_cost_input > 99999999.99)) {
        $error = 'Enter a valid non-negative estimated cost.';
    } elseif (strlen($diagnosis) > 20000 || strlen($repair_notes) > 20000) {
        $error = 'Diagnosis and repair notes must be 20,000 characters or fewer.';
    } elseif ($signature !== '' && (strlen($signature) > 2000000 || !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature))) {
        $error = 'The customer signature is invalid. Please sign again.';
    } else {
        $current_stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $current_stmt->execute([$ticket_id]);
        $current_ticket = $current_stmt->fetch();

        if (!$current_ticket) {
            http_response_code(404);
            exit('Ticket not found.');
        }

        if ($clear_signature) {
            $signature_value = null;
        } elseif ($signature !== '') {
            $signature_value = $signature;
        } else {
            $signature_value = $current_ticket['customer_signature'];
        }

        $estimated_cost = $warranty_status === 'Not Warranty' && $estimated_cost_input !== ''
            ? number_format((float)$estimated_cost_input, 2, '.', '')
            : null;
        $update_stmt = $pdo->prepare(
            'UPDATE tickets
             SET status = ?, repair_stage = ?, warranty_status = ?, priority = ?, assigned_technician = ?,
                 estimated_cost = ?, diagnosis = ?, repair_notes = ?, customer_signature = ?
             WHERE id = ?'
        );
        $update_stmt->execute([
            $new_status,
            $repair_stage,
            $warranty_status,
            $priority,
            $assigned_technician !== '' ? $assigned_technician : null,
            $estimated_cost,
            $diagnosis !== '' ? $diagnosis : null,
            $repair_notes !== '' ? $repair_notes : null,
            $signature_value,
            $ticket_id,
        ]);

        if (($current_ticket['repair_stage'] ?? '') !== $repair_stage) {
            sendStatusEmail($current_ticket['email'], $current_ticket['customer_name'], $current_ticket['ticket_number'], $repair_stage);
            $log_stmt = $pdo->prepare('INSERT INTO email_logs (ticket_id, recipient_email, subject, status) VALUES (?, ?, ?, ?)');
            $log_stmt->execute([$ticket_id, $current_ticket['email'], "Repair stage updated to: {$repair_stage}", 'Sent']);
            $message = 'Repair details saved and customer notified about the stage update.';
        } else {
            $message = 'Repair details saved.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();
if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php $active_dashboard_nav = 'dashboard'; include 'includes/dashboard_sidebar.php'; ?>

    <main class="dashboard-main ticket-workspace">
        <div class="dashboard-heading">
            <div>
                <p class="dashboard-eyebrow">Service ticket</p>
                <h1><?php echo htmlspecialchars($ticket['ticket_number']); ?></h1>
                <p>Review customer information and manage repair processing.</p>
            </div>
            <a href="dashboard.php" class="dashboard-create">Back to dashboard</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert-success dashboard-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert-danger dashboard-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="ticket.php?id=<?php echo (int)$ticket['id']; ?>" method="POST" class="ticket-workspace-grid" id="ticket-processing-form">
            <section class="dashboard-ticket-panel ticket-info-panel">
                <div class="ticket-panel-heading">
                    <div><h2>Customer and device</h2></div>
                </div>
                <dl class="ticket-detail-grid">
                    <div><dt>Customer</dt><dd><?php echo htmlspecialchars($ticket['customer_name']); ?></dd></div>
                    <div><dt>Phone</dt><dd><?php echo htmlspecialchars($ticket['phone_number']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo htmlspecialchars($ticket['email']); ?></dd></div>
                    <div class="ticket-detail-wide"><dt>Address</dt><dd><?php echo nl2br(htmlspecialchars($ticket['address'])); ?></dd></div>
                    <div><dt>Device</dt><dd><?php echo htmlspecialchars(trim($ticket['brand'] . ' ' . $ticket['model'])); ?></dd></div>
                    <div><dt>Serial / IMEI</dt><dd><?php echo htmlspecialchars(trim(($ticket['serial_number'] ?: '—') . ' / ' . ($ticket['imei'] ?: '—'))); ?></dd></div>
                    <div class="ticket-detail-wide"><dt>Accessories</dt><dd><?php echo htmlspecialchars($ticket['accessories'] ?: 'None listed'); ?></dd></div>
                </dl>

                <div class="ticket-concern">
                    <p class="ticket-processing-eyebrow">Reported concern</p>
                    <p><?php echo nl2br(htmlspecialchars($ticket['problem_description'])); ?></p>
                </div>

                <div class="ticket-workflow">
                    <p class="ticket-processing-eyebrow">Workflow</p>
                    <h3><?php echo htmlspecialchars($ticket['warranty_status'] ?: 'Pending assessment'); ?></h3>
                    <div class="ticket-workflow-steps" aria-label="Repair workflow">
                        <?php foreach (['Received', 'Diagnosis', 'Parts order approved', 'Parts arrived', 'Ongoing repair', 'Ready for pickup'] as $workflow_step): ?>
                            <span class="<?php echo ($ticket['repair_stage'] ?? '') === $workflow_step ? 'current' : ''; ?>"><?php echo htmlspecialchars($workflow_step); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="dashboard-ticket-panel ticket-processing-panel">
                <div class="ticket-panel-heading">
                    <div><h2>Repair processing</h2></div>
                </div>

                <div class="ticket-processing-fields">
                    <fieldset class="ticket-field-wide">
                        <legend>Warranty</legend>
                        <div class="ticket-choice-grid">
                            <?php foreach (['Under Warranty', 'Not Warranty'] as $warranty_option): ?>
                                <label class="ticket-choice-card <?php echo $ticket['warranty_status'] === $warranty_option ? 'selected' : ''; ?>">
                                    <input type="radio" name="warranty_status" value="<?php echo htmlspecialchars($warranty_option); ?>" <?php echo $ticket['warranty_status'] === $warranty_option ? 'checked' : ''; ?>>
                                    <span><strong><?php echo htmlspecialchars($warranty_option); ?></strong><small><?php echo $warranty_option === 'Under Warranty' ? 'No repair cost required.' : 'Payment is required before parts ordering.'; ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="ticket-processing-control">
                        <label for="ticket-repair-stage">Status</label>
                        <select id="ticket-repair-stage" name="repair_stage" class="ticket-control">
                            <?php foreach ($repair_stages as $repair_stage_option): ?>
                                <option value="<?php echo htmlspecialchars($repair_stage_option); ?>" <?php echo ($ticket['repair_stage'] ?? 'Received') === $repair_stage_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($repair_stage_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ticket-processing-control">
                        <label for="ticket-priority">Priority</label>
                        <select id="ticket-priority" name="priority" class="ticket-control">
                            <?php foreach ($valid_priorities as $priority_option): ?>
                                <option value="<?php echo htmlspecialchars($priority_option); ?>" <?php echo ($ticket['priority'] ?: 'Normal') === $priority_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($priority_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ticket-processing-control">
                        <label for="assigned-technician">Assigned technician</label>
                        <input id="assigned-technician" name="assigned_technician" class="ticket-control" type="text" maxlength="100" placeholder="Technician name" value="<?php echo htmlspecialchars($ticket['assigned_technician'] ?? ''); ?>">
                    </div>
                    <div class="ticket-processing-control">
                        <label for="estimated-cost">Estimated cost</label>
                        <div class="ticket-cost-input">
                            <span aria-hidden="true">₱</span>
                            <input id="estimated-cost" name="estimated_cost" class="ticket-control" type="number" min="0" max="99999999.99" step="0.01" placeholder="<?php echo $ticket['warranty_status'] === 'Under Warranty' ? 'Not required under warranty' : 'Estimated cost'; ?>" value="<?php echo htmlspecialchars($ticket['estimated_cost'] ?? ''); ?>" <?php echo $ticket['warranty_status'] === 'Not Warranty' ? '' : 'disabled'; ?>>
                        </div>
                    </div>
                    <div class="ticket-processing-control ticket-field-wide">
                        <label for="ticket-diagnosis">Diagnosis</label>
                        <textarea id="ticket-diagnosis" name="diagnosis" class="ticket-control ticket-textarea" maxlength="20000" placeholder="Technician diagnosis..."><?php echo htmlspecialchars($ticket['diagnosis'] ?? ''); ?></textarea>
                    </div>
                    <div class="ticket-processing-control ticket-field-wide">
                        <label for="ticket-repair-notes">Service notes</label>
                        <textarea id="ticket-repair-notes" name="repair_notes" class="ticket-control ticket-textarea ticket-notes-textarea" maxlength="20000" placeholder="Parts replaced, work completed, customer instructions..."><?php echo htmlspecialchars($ticket['repair_notes'] ?? ''); ?></textarea>
                    </div>

                    <?php if (($ticket['repair_stage'] ?? 'Received') === 'Received'): ?>
                        <section class="ticket-signature-panel ticket-field-wide" id="ticket-signature-panel">
                            <p class="ticket-processing-eyebrow">Customer signatory required</p>
                            <h3>Customer acceptance signature</h3>
                            <p class="ticket-signature-hint">Ask the customer to sign in the box below.</p>
                            <div class="ticket-signature-pad">
                                <canvas id="customer-signature-pad" aria-label="Customer signature area"></canvas>
                                <img id="saved-customer-signature" alt="Saved customer signature" src="<?php echo !empty($ticket['customer_signature']) ? htmlspecialchars($ticket['customer_signature']) : ''; ?>" <?php echo empty($ticket['customer_signature']) ? 'hidden' : ''; ?>>
                            </div>
                            <input type="hidden" name="customer_signature" id="customer-signature-data">
                            <input type="hidden" name="clear_signature" id="clear-customer-signature" value="">
                            <div class="ticket-signature-actions">
                                <button type="button" class="ticket-clear-signature" id="clear-signature-button">Clear signature</button>
                            </div>
                        </section>
                    <?php endif; ?>

                    <div class="ticket-save-row ticket-field-wide">
                        <button type="submit" name="save_processing" value="1" class="dashboard-create">Save repair details</button>
                    </div>
                </div>
            </section>
        </form>
    </main>
</div>

<script>
(() => {
    const repairStage = document.getElementById('ticket-repair-stage');
    const signaturePanel = document.getElementById('ticket-signature-panel');
    if (repairStage && signaturePanel) {
        repairStage.addEventListener('change', () => {
            signaturePanel.hidden = repairStage.value !== 'Received';
        });
    }

    const warrantyInputs = Array.from(document.querySelectorAll('input[name="warranty_status"]'));
    const estimatedCost = document.getElementById('estimated-cost');
    const updateEstimatedCost = () => {
        const notWarranty = warrantyInputs.some(input => input.checked && input.value === 'Not Warranty');
        if (estimatedCost) {
            estimatedCost.disabled = !notWarranty;
            estimatedCost.placeholder = notWarranty ? 'Estimated cost' : 'Not required under warranty';
            if (!notWarranty) estimatedCost.value = '';
        }
        warrantyInputs.forEach(input => {
            const card = input.closest('.ticket-choice-card');
            if (card) card.classList.toggle('selected', input.checked);
        });
    };
    warrantyInputs.forEach(input => input.addEventListener('change', updateEstimatedCost));
    updateEstimatedCost();

    const canvas = document.getElementById('customer-signature-pad');
    const image = document.getElementById('saved-customer-signature');
    const signatureInput = document.getElementById('customer-signature-data');
    const clearInput = document.getElementById('clear-customer-signature');
    const clearButton = document.getElementById('clear-signature-button');
    if (!canvas || !signatureInput || !clearInput || !clearButton) return;

    const context = canvas.getContext('2d');
    let drawing = false;
    let hasNewSignature = false;

    const resizeCanvas = () => {
        const ratio = window.devicePixelRatio || 1;
        const bounds = canvas.getBoundingClientRect();
        const previous = hasNewSignature ? canvas.toDataURL() : '';
        canvas.width = Math.max(1, Math.floor(bounds.width * ratio));
        canvas.height = Math.max(1, Math.floor(bounds.height * ratio));
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.strokeStyle = '#172033';
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        if (previous) {
            const saved = new Image();
            saved.onload = () => context.drawImage(saved, 0, 0, bounds.width, bounds.height);
            saved.src = previous;
        }
    };

    const point = event => {
        const bounds = canvas.getBoundingClientRect();
        return { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
    };

    canvas.addEventListener('pointerdown', event => {
        drawing = true;
        canvas.setPointerCapture(event.pointerId);
        const position = point(event);
        context.beginPath();
        context.moveTo(position.x, position.y);
    });

    canvas.addEventListener('pointermove', event => {
        if (!drawing) return;
        const position = point(event);
        context.lineTo(position.x, position.y);
        context.stroke();
        hasNewSignature = true;
        clearInput.value = '';
        signatureInput.value = canvas.toDataURL('image/png');
        if (image) image.hidden = true;
    });

    const finishStroke = () => {
        drawing = false;
        if (hasNewSignature) signatureInput.value = canvas.toDataURL('image/png');
    };
    canvas.addEventListener('pointerup', finishStroke);
    canvas.addEventListener('pointercancel', finishStroke);
    canvas.addEventListener('pointerleave', finishStroke);

    clearButton.addEventListener('click', () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        signatureInput.value = '';
        clearInput.value = '1';
        hasNewSignature = false;
        if (image) image.hidden = true;
    });

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
})();
</script>

<?php include 'includes/footer.php'; ?>
