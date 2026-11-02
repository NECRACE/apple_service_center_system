<?php
require_once 'config.php';

if (!isset($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

$counters = ['Counter 1', 'Counter 2', 'Counter 3', 'Released Counter'];
$counter = $_GET['counter'] ?? $_POST['counter'] ?? $counters[0];
if (!in_array($counter, $counters, true)) {
    $counter = $counters[0];
}

if (empty($_SESSION['counter_csrf_token'])) {
    $_SESSION['counter_csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$voice_announcement = null;
$release_email_ticket = null;
$release_email_signature = null;
$queue_statuses = ['Waiting', 'Called', 'Serving', 'Completed', 'Skipped'];
$queue_action = $_POST['queue_action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $queue_action !== '') {
    if (!hash_equals($_SESSION['counter_csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid request token.');
    }

    $pdo->beginTransaction();
    try {
        $current_stmt = $pdo->prepare(
            "SELECT *
             FROM tickets
             WHERE queue_counter = ? AND queue_status IN ('Called', 'Serving')
             ORDER BY queue_called_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $current_stmt->execute([$counter]);
        $current_ticket = $current_stmt->fetch();

        if ($queue_action === 'call_next') {
            if ($current_ticket) {
                $error = 'Complete or skip the current customer before calling the next ticket.';
            } else {
                $release_counter = $counter === 'Released Counter';
                $service_type = 'new_service';
                $release_filter = $release_counter
                    ? "status = 'Ready for Release' AND release_queue_joined_at IS NOT NULL"
                    : "status <> 'Ready for Release'";
                $next_stmt = $pdo->prepare(
                    "SELECT id, ticket_number, release_ticket_number FROM tickets
                     WHERE queue_status = 'Waiting' AND service_type = ? AND {$release_filter}
                     ORDER BY COALESCE(release_queue_joined_at, created_at) ASC, id ASC
                     LIMIT 1
                     FOR UPDATE"
                );
                $next_stmt->execute([$service_type]);
                $next_ticket = $next_stmt->fetch();
                if ($next_ticket) {
                    $call_stmt = $pdo->prepare("UPDATE tickets SET queue_status = 'Called', queue_counter = ?, queue_called_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $call_stmt->execute([$counter, $next_ticket['id']]);
                    $queue_reference = $release_counter
                        ? ($next_ticket['release_ticket_number'] ?: $next_ticket['ticket_number'])
                        : $next_ticket['ticket_number'];
                    $message = 'Ticket ' . $queue_reference . ' called to ' . $counter . '.';
                    $voice_announcement = ['ticket' => $queue_reference, 'counter' => $counter];
                } else {
                    $message = 'There are no waiting tickets to call.';
                }
            }
        } elseif ($queue_action === 'recall') {
            if (!$current_ticket || $current_ticket['queue_status'] !== 'Called') {
                $error = 'There is no called customer to recall at this counter.';
            } else {
                $recall_stmt = $pdo->prepare("UPDATE tickets SET queue_status = 'Called', queue_called_at = CURRENT_TIMESTAMP WHERE id = ?");
                $recall_stmt->execute([$current_ticket['id']]);
                $queue_reference = $counter === 'Released Counter'
                    ? ($current_ticket['release_ticket_number'] ?: $current_ticket['ticket_number'])
                    : $current_ticket['ticket_number'];
                $message = 'Ticket ' . $queue_reference . ' recalled to ' . $counter . '.';
                $voice_announcement = ['ticket' => $queue_reference, 'counter' => $counter];
            }
        } elseif (in_array($queue_action, ['accept', 'complete', 'skip'], true)) {
            if (!$current_ticket) {
                $error = 'There is no called or serving customer at this counter.';
            } elseif ($queue_action === 'accept' && $current_ticket['queue_status'] !== 'Called') {
                $error = 'Only a called customer can be accepted.';
            } elseif ($queue_action === 'complete' && $current_ticket['queue_status'] !== 'Serving') {
                $error = 'Accept the customer before completing the counter service.';
            } elseif ($queue_action === 'complete' && $counter === 'Released Counter' && $current_ticket['status'] !== 'Ready for Release') {
                $error = 'Only a ticket marked Ready for Release can be released at this counter.';
            } elseif ($queue_action === 'complete' && $counter === 'Released Counter' && ($_POST['release_ready'] ?? '') !== '1') {
                $error = 'Confirm that the device is ready for release before submitting.';
            } elseif ($queue_action === 'complete' && $counter === 'Released Counter' && (int)($_POST['release_ticket_id'] ?? 0) !== (int)$current_ticket['id']) {
                $error = 'The release form does not match the current ticket. Refresh and try again.';
            } elseif ($queue_action === 'complete' && $counter === 'Released Counter' && (
                !is_string($_POST['release_signature'] ?? null)
                || !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $_POST['release_signature'])
                || strlen($_POST['release_signature']) > 2000000
            )) {
                $error = 'A valid client release signature is required.';
            } else {
                if ($queue_action === 'complete' && $counter === 'Released Counter') {
                    $next_status = 'Completed';
                    $release_email_ticket = $current_ticket;
                    $release_email_signature = $_POST['release_signature'];
                    $update_stmt = $pdo->prepare(
                        "UPDATE tickets
                         SET queue_status = 'Completed', status = 'Completed',
                             release_signature = ?, released_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND queue_status = 'Serving' AND status = 'Ready for Release'"
                    );
                    $update_stmt->execute([$release_email_signature, $current_ticket['id']]);
                    if ($update_stmt->rowCount() !== 1) {
                        $release_email_ticket = null;
                        $release_email_signature = null;
                        $message = '';
                        $error = 'The release could not be completed because the ticket changed. Please refresh and try again.';
                    } else {
                        $message = 'Ticket ' . $current_ticket['ticket_number'] . ' released.';
                    }
                } else {
                    $next_status = [
                        'accept' => 'Serving',
                        'complete' => 'Completed',
                        'skip' => 'Skipped',
                    ][$queue_action];
                    $update_stmt = $pdo->prepare('UPDATE tickets SET queue_status = ? WHERE id = ?');
                    $update_stmt->execute([$next_status, $current_ticket['id']]);
                    $message = 'Ticket ' . $current_ticket['ticket_number'] . ' marked ' . strtolower($next_status) . '.';
                }
            }
        } else {
            $error = 'Unknown counter action.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($release_email_ticket !== null) {
        $email_subject = 'Service Report - Ticket #' . $release_email_ticket['ticket_number'] . ' [Fix Device]';
        $email_sent = sendReleaseReportEmail($release_email_ticket, $release_email_signature);
        $log_stmt = $pdo->prepare('INSERT INTO email_logs (ticket_id, recipient_email, subject, status) VALUES (?, ?, ?, ?)');
        $log_stmt->execute([
            $release_email_ticket['id'],
            $release_email_ticket['email'],
            $email_subject,
            $email_sent ? 'Sent' : 'Failed',
        ]);
        if ($email_sent) {
            $message .= ' The service report was emailed to the customer.';
        } else {
            $error = 'The device was released, but the service report email could not be sent. Check the XAMPP mail/SMTP configuration.';
        }
    }

    $_SESSION['counter_flash'] = [
        'message' => $message,
        'error' => $error,
        'voice_announcement' => $voice_announcement,
    ];
    header('Location: staff_counter.php?counter=' . urlencode($counter));
    exit();
}

$flash = $_SESSION['counter_flash'] ?? [];
unset($_SESSION['counter_flash']);
$message = $flash['message'] ?? '';
$error = $flash['error'] ?? '';
$voice_announcement = $flash['voice_announcement'] ?? null;

$stats = [];
foreach ($queue_statuses as $queue_status) {
    $stat_stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE queue_status = ? AND ((DATE(created_at) = CURDATE() AND status <> 'Ready for Release') OR DATE(release_queue_joined_at) = CURDATE())");
    $stat_stmt->execute([$queue_status]);
    $stats[$queue_status] = (int)$stat_stmt->fetchColumn();
}

$current_stmt = $pdo->prepare(
    "SELECT *
     FROM tickets
     WHERE queue_counter = ? AND queue_status IN ('Called', 'Serving')
     ORDER BY queue_called_at DESC, id DESC
     LIMIT 1"
);
$current_stmt->execute([$counter]);
$current_ticket = $current_stmt->fetch();

$today_stmt = $pdo->query(
    "SELECT ticket_number, release_ticket_number, queue_status, queue_counter, created_at, release_queue_joined_at, service_type
     FROM tickets
     WHERE (DATE(created_at) = CURDATE() AND status <> 'Ready for Release')
        OR DATE(release_queue_joined_at) = CURDATE()
     ORDER BY created_at DESC, id DESC"
);
$today_tickets = $today_stmt->fetchAll();

$queue_status_labels = [
    'Waiting' => 'Waiting',
    'Called' => 'Called',
    'Serving' => 'Serving',
    'Completed' => 'Completed',
    'Skipped' => 'Skipped',
];

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php $active_dashboard_nav = 'counter'; include 'includes/dashboard_sidebar.php'; ?>

    <main class="dashboard-main staff-counter-main">
        <div class="dashboard-heading">
            <div>
                <p class="dashboard-eyebrow">Queue operations</p>
                <h1>Staff counter</h1>
                <p>Call and manage today's customer queue.</p>
            </div>
            <a href="register.php?step=1" class="dashboard-create">New admission</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert-success dashboard-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert-danger dashboard-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($voice_announcement): ?>
            <div class="counter-voice-status" id="counter-voice-status" role="status" aria-live="polite">
                Calling ticket <?php echo htmlspecialchars($voice_announcement['ticket']); ?> to <?php echo htmlspecialchars($voice_announcement['counter']); ?>.
                <button type="button" class="counter-voice-repeat" id="repeat-voice-announcement">Repeat announcement</button>
            </div>
            <span id="counter-voice-announcement"
                  data-ticket="<?php echo htmlspecialchars($voice_announcement['ticket'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-counter="<?php echo htmlspecialchars($voice_announcement['counter'], ENT_QUOTES, 'UTF-8'); ?>"
                  hidden></span>
        <?php endif; ?>

        <section class="dashboard-stats counter-stats" aria-label="Today's queue summary">
            <?php foreach (['Waiting', 'Called', 'Serving', 'Completed'] as $status): ?>
                <article class="dashboard-stat counter-stat">
                    <div><span><?php echo htmlspecialchars($status); ?></span><strong><?php echo $stats[$status]; ?></strong></div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-ticket-panel counter-call-panel">
            <form action="staff_counter.php" method="GET" class="counter-select-form">
                <label for="counter-select">Select your counter</label>
                <select id="counter-select" name="counter" class="ticket-control" onchange="this.form.submit()">
                    <?php foreach ($counters as $counter_option): ?>
                        <option value="<?php echo htmlspecialchars($counter_option); ?>" <?php echo $counter === $counter_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($counter_option); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($counter === 'Released Counter' && !$current_ticket): ?>
                <div class="counter-release-notice counter-release-empty">
                    <strong>For release</strong>
                    <span>Customers must verify their ready-for-pickup ticket and join the release queue before you call the next ticket.</span>
                </div>
            <?php endif; ?>

            <div class="<?php echo $counter === 'Released Counter' && $current_ticket ? 'counter-release-layout' : ''; ?>">
                <?php if ($counter === 'Released Counter' && $current_ticket): ?>
                    <aside class="counter-release-details">
                        <div class="counter-release-notice">
                            <strong>For release</strong>
                            <span>Confirm the repair and customer details before handing over the device.</span>
                        </div>
                        <h2>Customer and device</h2>
                        <dl class="ticket-detail-grid">
                            <div><dt>Release reference</dt><dd><?php echo htmlspecialchars($current_ticket['release_ticket_number'] ?: $current_ticket['ticket_number']); ?></dd></div>
                            <div><dt>Customer</dt><dd><?php echo htmlspecialchars($current_ticket['customer_name']); ?></dd></div>
                            <div><dt>Phone</dt><dd><?php echo htmlspecialchars($current_ticket['phone_number']); ?></dd></div>
                            <div><dt>Email</dt><dd><?php echo htmlspecialchars($current_ticket['email']); ?></dd></div>
                            <div class="ticket-detail-wide"><dt>Address</dt><dd><?php echo nl2br(htmlspecialchars($current_ticket['address'])); ?></dd></div>
                            <div><dt>Product</dt><dd><?php echo htmlspecialchars(trim($current_ticket['brand'] . ' ' . $current_ticket['model'])); ?></dd></div>
                            <div><dt>Serial / IMEI</dt><dd><?php echo htmlspecialchars(trim(($current_ticket['serial_number'] ?: '—') . ' / ' . ($current_ticket['imei'] ?: '—'))); ?></dd></div>
                            <div><dt>Color</dt><dd><?php echo htmlspecialchars($current_ticket['color'] ?: '—'); ?></dd></div>
                            <div class="ticket-detail-wide"><dt>Accessories</dt><dd><?php echo htmlspecialchars($current_ticket['accessories'] ?: 'None listed'); ?></dd></div>
                            <div class="ticket-detail-wide"><dt>Reported concern</dt><dd><?php echo nl2br(htmlspecialchars($current_ticket['problem_description'])); ?></dd></div>
                            <div class="ticket-detail-wide"><dt>Diagnosis / repair notes</dt><dd><?php echo nl2br(htmlspecialchars(trim(($current_ticket['diagnosis'] ?? '') . "\n" . ($current_ticket['repair_notes'] ?? '')) ?: 'Not provided')); ?></dd></div>
                        </dl>
                    </aside>
                <?php endif; ?>

                <div>
                    <div class="counter-current-ticket <?php echo $counter === 'Released Counter' && $current_ticket ? 'counter-current-ticket-release' : ''; ?>">
                        <p class="dashboard-eyebrow">Current customer</p>
                        <?php if ($current_ticket): ?>
                            <h2><?php echo htmlspecialchars($counter === 'Released Counter' ? ($current_ticket['release_ticket_number'] ?: $current_ticket['ticket_number']) : $current_ticket['ticket_number']); ?></h2>
                            <p class="counter-current-meta">
                                <?php echo htmlspecialchars($current_ticket['customer_name']); ?>
                                <?php if ($current_ticket['model'] !== ''): ?> &middot; <?php echo htmlspecialchars($current_ticket['model']); ?><?php endif; ?>
                            </p>
                            <p>Status: <strong><?php echo htmlspecialchars($queue_status_labels[$current_ticket['queue_status']]); ?></strong></p>
                        <?php else: ?>
                            <h2>No customer called</h2>
                            <p class="counter-current-meta">Call the next waiting ticket when ready.</p>
                        <?php endif; ?>
                    </div>

                    <div class="counter-actions">
                        <?php
                        $actions = [
                            'call_next' => ['Call Next', 'counter-action-primary'],
                            'recall' => ['Recall', 'counter-action-secondary'],
                            'accept' => ['Accept', 'counter-action-secondary'],
                            'complete' => ['Complete', 'counter-action-secondary'],
                            'skip' => ['Skip', 'counter-action-danger'],
                        ];
                        foreach ($actions as $action => [$label, $class]):
                            if ($counter === 'Released Counter' && $action === 'complete') continue;
                            $disabled = !$current_ticket && $action !== 'call_next';
                            $disabled = $disabled || ($current_ticket && $action === 'call_next');
                            $disabled = $disabled || ($current_ticket && $action === 'recall' && $current_ticket['queue_status'] !== 'Called');
                            $disabled = $disabled || ($current_ticket && $action === 'accept' && $current_ticket['queue_status'] !== 'Called');
                            $disabled = $disabled || ($current_ticket && $action === 'complete' && $current_ticket['queue_status'] !== 'Serving');
                        ?>
                            <form action="staff_counter.php?counter=<?php echo urlencode($counter); ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['counter_csrf_token']); ?>">
                                <input type="hidden" name="counter" value="<?php echo htmlspecialchars($counter); ?>">
                                <button type="submit" name="queue_action" value="<?php echo htmlspecialchars($action); ?>" class="counter-action <?php echo htmlspecialchars($class); ?>" <?php echo $disabled ? 'disabled' : ''; ?>><?php echo htmlspecialchars($label); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($counter === 'Released Counter' && $current_ticket): ?>
                        <?php if ($current_ticket['queue_status'] === 'Serving'): ?>
                            <form action="staff_counter.php?counter=<?php echo urlencode($counter); ?>" method="POST" class="counter-release-form" id="counter-release-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['counter_csrf_token']); ?>">
                                <input type="hidden" name="counter" value="<?php echo htmlspecialchars($counter); ?>">
                                <input type="hidden" name="release_ticket_id" value="<?php echo (int)$current_ticket['id']; ?>">
                                <input type="hidden" name="release_signature" id="release-signature-data">
                                <h2>Confirm device release</h2>
                                <label class="counter-release-confirm">
                                    <input type="checkbox" name="release_ready" value="1" required>
                                    <span>Device is repaired and ready to release to the customer.</span>
                                </label>
                                <p class="ticket-signature-hint">Ask the client to sign to acknowledge receipt of the device.</p>
                                <h3>Client release signature</h3>
                                <div class="ticket-signature-pad">
                                    <canvas id="release-signature-pad" aria-label="Client release signature area"></canvas>
                                </div>
                                <div class="ticket-signature-actions">
                                    <button type="button" class="ticket-clear-signature" id="clear-release-signature">Clear signature</button>
                                </div>
                                <button type="submit" name="queue_action" value="complete" class="dashboard-create counter-release-submit">Submit release &amp; email report</button>
                            </form>
                        <?php else: ?>
                            <p class="counter-release-prompt">Accept the called customer to confirm the release and capture the client signature.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="dashboard-ticket-panel counter-queue-panel">
            <div class="ticket-panel-heading">
                <div>
                    <h2>Today&rsquo;s queue</h2>
                    <p>All queue numbers generated today.</p>
                </div>
            </div>
            <div class="dashboard-table-scroll">
                <table class="dashboard-table counter-queue-table">
                    <thead>
                        <tr>
                            <th>Queue number</th>
                            <th>Status</th>
                            <th>Counter</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$today_tickets): ?>
                            <tr><td colspan="4" class="dashboard-empty">No queue numbers today.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($today_tickets as $queue_ticket): ?>
                            <?php $queue_class = 'queue-status-' . strtolower($queue_ticket['queue_status']); ?>
                            <?php $is_release_queue_ticket = !empty($queue_ticket['release_queue_joined_at']); ?>
                            <?php $queue_time = $is_release_queue_ticket ? $queue_ticket['release_queue_joined_at'] : $queue_ticket['created_at']; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($is_release_queue_ticket ? ($queue_ticket['release_ticket_number'] ?: $queue_ticket['ticket_number']) : $queue_ticket['ticket_number']); ?></strong><small><?php echo $is_release_queue_ticket ? 'For release' : ($queue_ticket['service_type'] === 'new_service' ? 'New client' : 'For release'); ?></small></td>
                                <td><span class="counter-queue-status <?php echo htmlspecialchars($queue_class); ?>"><?php echo htmlspecialchars($queue_status_labels[$queue_ticket['queue_status']] ?? $queue_ticket['queue_status']); ?></span></td>
                                <td><?php echo htmlspecialchars($queue_ticket['queue_counter'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars(date('g:i:s A', strtotime($queue_time))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php if ($voice_announcement): ?>
    <script>
        (() => {
            const announcement = document.getElementById('counter-voice-announcement');
            const status = document.getElementById('counter-voice-status');
            const repeatButton = document.getElementById('repeat-voice-announcement');
            if (!announcement || !status || !repeatButton) return;

            const speakAnnouncement = () => {
                if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
                    status.firstChild.textContent = 'Voice announcements are not supported by this browser. ';
                    repeatButton.hidden = true;
                    return;
                }

                const ticket = announcement.dataset.ticket
                    .split('-')
                    .map(part => part.split('').join(' '))
                    .join(', ');
                const utterance = new SpeechSynthesisUtterance(
                    `Queue number, ${ticket}. Please proceed to ${announcement.dataset.counter}.`
                );
                utterance.lang = 'en-US';
                utterance.rate = 0.88;
                utterance.onstart = () => {
                    status.dataset.voiceState = 'speaking';
                };
                utterance.onend = () => {
                    status.dataset.voiceState = 'complete';
                };
                utterance.onerror = () => {
                    status.dataset.voiceState = 'error';
                    status.firstChild.textContent = 'Could not play the voice announcement. ';
                };
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            };

            repeatButton.addEventListener('click', speakAnnouncement);
            window.setTimeout(speakAnnouncement, 250);
        })();
    </script>
<?php endif; ?>

<?php if ($counter === 'Released Counter' && $current_ticket && $current_ticket['queue_status'] === 'Serving'): ?>
    <script>
        (() => {
            const form = document.getElementById('counter-release-form');
            const canvas = document.getElementById('release-signature-pad');
            const signatureInput = document.getElementById('release-signature-data');
            const clearButton = document.getElementById('clear-release-signature');
            if (!form || !canvas || !signatureInput || !clearButton) return;

            const context = canvas.getContext('2d');
            let drawing = false;
            let hasSignature = false;

            const resizeCanvas = () => {
                const bounds = canvas.getBoundingClientRect();
                const ratio = window.devicePixelRatio || 1;
                canvas.width = Math.max(1, Math.floor(bounds.width * ratio));
                canvas.height = Math.max(1, Math.floor(bounds.height * ratio));
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                context.strokeStyle = '#172033';
                context.lineWidth = 2;
                context.lineCap = 'round';
                context.lineJoin = 'round';
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
                hasSignature = true;
                signatureInput.value = canvas.toDataURL('image/png');
            });
            const finishStroke = () => {
                drawing = false;
                if (hasSignature) signatureInput.value = canvas.toDataURL('image/png');
            };
            canvas.addEventListener('pointerup', finishStroke);
            canvas.addEventListener('pointercancel', finishStroke);
            canvas.addEventListener('pointerleave', finishStroke);
            clearButton.addEventListener('click', () => {
                context.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
                hasSignature = false;
            });
            form.addEventListener('submit', event => {
                const readyCheckbox = form.querySelector('input[name="release_ready"]');
                if (!readyCheckbox.checked || !hasSignature) {
                    event.preventDefault();
                    window.alert(!readyCheckbox.checked
                        ? 'Please confirm that the device is ready for release.'
                        : 'Please capture the client release signature before submitting.');
                }
            });

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
        })();
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
