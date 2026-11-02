<?php
require_once 'config.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : 'new_service';
$release_error = '';
$release_ticket = null;
$release_queue_joined = false;
$release_queue_already_joined = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['join_release_queue'])) {
        $service_type = 'for_release';
        $ticket_number = trim($_POST['ticket_number'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $stmt = $pdo->prepare("SELECT id, ticket_number, release_ticket_number, customer_name, email, brand, model, status, queue_status, release_queue_joined_at, created_at FROM tickets WHERE (ticket_number = ? OR legacy_ticket_number = ?) AND email = ? AND service_type = 'new_service'");
        $stmt->execute([$ticket_number, $ticket_number, $email]);
        $release_ticket = $stmt->fetch();

        if (!$release_ticket) {
            $release_error = 'We could not find a service ticket with that reference number and email address.';
        } elseif ($release_ticket['status'] !== 'Ready for Release') {
            $release_error = 'This ticket is not ready for pickup yet. Please check the repair status and try again later.';
        } elseif (in_array($release_ticket['queue_status'], ['Called', 'Serving'], true)) {
            $release_error = 'This ticket is already being served at a counter.';
        } elseif ($release_ticket['queue_status'] === 'Waiting' && $release_ticket['release_queue_joined_at'] !== null) {
            if ($release_ticket['release_ticket_number'] !== null) {
                $release_queue_joined = true;
                $release_queue_already_joined = true;
            } else {
                $release_error = 'This ticket is already waiting in the release queue. Please contact staff for your release reference.';
            }
        } else {
            $release_reference_exists_stmt = $pdo->prepare('SELECT 1 FROM tickets WHERE release_ticket_number = ?');
            $attempts = 0;
            do {
                $release_ticket_number = 'REL-' . date('Ymd') . '-' . random_int(1000, 9999);
                $release_reference_exists_stmt->execute([$release_ticket_number]);
                $reference_exists = (bool)$release_reference_exists_stmt->fetchColumn();
                $attempts++;
            } while ($reference_exists && $attempts < 10);

            if ($reference_exists) {
                throw new RuntimeException('Unable to generate a unique release reference number.');
            }

            $join_stmt = $pdo->prepare(
                "UPDATE tickets
                 SET queue_status = 'Waiting', queue_counter = NULL, queue_called_at = NULL,
                     release_queue_joined_at = CURRENT_TIMESTAMP, release_ticket_number = ?
                 WHERE id = ? AND status = 'Ready for Release'
                   AND queue_status NOT IN ('Called', 'Serving')
                   AND (release_queue_joined_at IS NULL OR queue_status IN ('Skipped', 'Completed'))"
            );
            $join_stmt->execute([$release_ticket_number, $release_ticket['id']]);
            if ($join_stmt->rowCount() !== 1) {
                $release_error = 'This ticket could not be added to the release queue. Please refresh and try again.';
                $release_ticket = null;
            } else {
                $release_queue_joined = true;
                $release_ticket['release_ticket_number'] = $release_ticket_number;
                $release_ticket['release_queue_joined_at'] = date('Y-m-d H:i:s');
            }
        }
    } elseif (isset($_POST['submit_registration'])) {
        // Collect & Sanitize Form Data
        $full_name = trim($_POST['full_name']);
        $phone_number = trim($_POST['phone_number']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $serial_number = trim($_POST['serial_number']);
        $imei = trim($_POST['imei']);
        $color = trim($_POST['color']);
        $accessories = trim($_POST['accessories']);
        $problem_description = trim($_POST['problem_description']);
        $service_type = $_POST['service_type'] ?? 'new_service';
        
        $reference_stmt = $pdo->prepare("SELECT 1 FROM tickets WHERE ticket_number = ?");
        $reference_date = date('Ymd');
        $attempts = 0;
        do {
            $ticket_number = 'SC-' . $reference_date . '-' . random_int(1000, 9999);
            $reference_stmt->execute([$ticket_number]);
            $reference_exists = (bool)$reference_stmt->fetchColumn();
            $attempts++;
        } while ($reference_exists && $attempts < 10);

        if ($reference_exists) {
            throw new RuntimeException('Unable to generate a unique service reference number.');
        }

        // Insert into Database
        $stmt = $pdo->prepare("INSERT INTO tickets 
            (ticket_number, customer_name, phone_number, email, address, brand, model, serial_number, imei, color, accessories, problem_description, service_type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        $inserted = $stmt->execute([
            $ticket_number, $full_name, $phone_number, $email, $address,
            $brand, $model, $serial_number, $imei, $color, $accessories, $problem_description, $service_type
        ]);

        if ($inserted) {
            $ticket_id = $pdo->lastInsertId();
            // Send Automated Email Notification
            sendStatusEmail($email, $full_name, $ticket_number, 'Pending Admission');
            
            // Log Email
            $log_stmt = $pdo->prepare("INSERT INTO email_logs (ticket_id, recipient_email, subject, status) VALUES (?, ?, ?, 'Sent')");
            $log_stmt->execute([$ticket_id, $email, "Service Ticket Created #{$ticket_number}"]);

            header("Location: success.php?ticket=" . urlencode($ticket_number));
            exit();
        }
    }
}

include 'includes/header.php';
?>

<?php if ($step === 1 && $service_type !== 'for_release'): ?>
    <div class="privacy-overlay" id="privacy-modal">
        <section class="privacy-modal" role="dialog" aria-modal="true" aria-labelledby="privacy-title">
            <p class="privacy-eyebrow">Before you continue</p>
            <h2 id="privacy-title">Data Privacy</h2>
            <div class="privacy-copy" tabindex="0">
                <p>As part of our commitment to providing quality and personalized services, we may request certain personal information when you purchase a product, request repair or technical services, contact us for assistance, or participate in activities, events, surveys, promotions, or training programs.</p>
                <p>Providing personal information is voluntary. However, certain information may be necessary for us to process transactions, provide requested products or services, perform repairs or technical support, respond to inquiries, or facilitate participation in applicable programs and activities. If the required information is not provided, we may be unable to provide or complete the requested service.</p>
                <h3>Protection</h3>
                <p>We are committed to protecting the privacy, integrity, accuracy, and confidentiality of the personal information entrusted to us. Appropriate organizational, physical, and technical security measures are implemented to protect personal information against unauthorized access, disclosure, alteration, loss, or misuse.</p>
                <p>Personal information may be stored in secured physical or electronic systems, including authorized cloud-based platforms where appropriate security measures, such as encryption and access controls, are implemented.</p>
                <p>Personal information will be retained only for as long as necessary to fulfill the purpose for which it was collected, comply with legal and regulatory requirements, or meet legitimate business requirements. Once the applicable retention period has expired, personal information will be securely disposed of or deleted in accordance with applicable policies and data privacy laws.</p>
            </div>
            <div class="privacy-actions">
                <button type="button" class="btn-submit" id="privacy-agree">I Agree &amp; Continue</button>
                <a href="index.php" class="privacy-cancel">Cancel</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($service_type === 'for_release'): ?>
    <main class="release-card">
        <div class="release-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="m4.5 7.7 7.5 4.2 7.5-4.2M12 12v8.5M15.5 14.5l1.5 1.5 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <p class="release-eyebrow">For Release</p>
        <h1>Pick up your repaired device</h1>
        <p class="release-description">Enter the reference number and email used during admission. Your ticket must already be Ready for Pickup.</p>

        <?php if ($release_error !== ''): ?>
            <div class="alert-danger release-alert" role="alert"><?php echo htmlspecialchars($release_error); ?></div>
        <?php elseif ($release_ticket && !$release_queue_joined): ?>
            <div class="alert-success release-alert" role="status">
                <?php if ($release_queue_joined): ?>
                    Your <?php echo htmlspecialchars($release_ticket['brand'] . ' ' . $release_ticket['model']); ?> has been added to the release queue.
                <?php else: ?>
                    Ticket verified. Your <?php echo htmlspecialchars($release_ticket['brand'] . ' ' . $release_ticket['model']); ?> is ready for pickup.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($release_queue_joined && $release_ticket): ?>
            <div class="alert-success release-alert" role="status">
                <?php echo $release_queue_already_joined
                    ? 'Your device is already waiting in the release queue.'
                    : 'Your device is ready for release and has been added to the queue.'; ?>
            </div>
            <section class="reference-slip release-reference-slip" aria-label="Printable release reference slip">
                <header class="reference-slip-header">
                    <h1>FIX DEVICE</h1>
                    <p>SERVICE CENTER</p>
                    <h2>RELEASE REFERENCE SLIP</h2>
                </header>
                <div class="reference-slip-reference">
                    <p>RELEASE REFERENCE NUMBER</p>
                    <strong><?php echo htmlspecialchars($release_ticket['release_ticket_number']); ?></strong>
                </div>
                <dl class="reference-slip-details">
                    <div>
                        <dt>Customer:</dt>
                        <dd><?php echo htmlspecialchars($release_ticket['customer_name']); ?></dd>
                    </div>
                    <div>
                        <dt>Device:</dt>
                        <dd><?php echo htmlspecialchars(trim($release_ticket['brand'] . ' ' . $release_ticket['model'])); ?></dd>
                    </div>
                    <div>
                        <dt>Date:</dt>
                        <dd><?php echo htmlspecialchars(date('M j, Y \a\t h:i A', strtotime($release_ticket['release_queue_joined_at']))); ?></dd>
                    </div>
                    <div>
                        <dt>Status:</dt>
                        <dd>FOR RELEASE</dd>
                    </div>
                </dl>
                <footer class="reference-slip-footer">
                    <p>Please keep this release reference slip.</p>
                    <p>Present it when claiming your repaired device.</p>
                </footer>
            </section>
            <div class="confirmation-actions release-confirmation-actions">
                <button type="button" class="confirmation-button confirmation-button-primary" onclick="window.print()">
                    Print reference slip
                </button>
                <a href="index.php" class="confirmation-button confirmation-button-secondary">Home</a>
            </div>
        <?php else: ?>
            <form action="register.php?service_type=for_release" method="POST" class="release-form">
                <input type="hidden" name="service_type" value="for_release">
                <div class="form-group">
                    <label for="ticket-number">Reference number</label>
                    <input id="ticket-number" type="text" name="ticket_number" class="form-control" placeholder="SC-20260904-1234" value="<?php echo htmlspecialchars($_POST['ticket_number'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="release-email">Email address</label>
                    <input id="release-email" type="email" name="email" class="form-control" placeholder="name@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <button type="submit" name="join_release_queue" class="btn-submit">Join release queue</button>
            </form>
        <?php endif; ?>
    </main>
<?php else: ?>
<div class="registration-wrapper">
    <!-- Left Hero Column -->
    <div class="hero-side">
        <p style="color: #2563eb; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Device Service Admission</p>
        <h2>Tell us what happened. We'll take care of the rest.</h2>
        <p>Complete the form using the service-center device. We will email your secure tracking link and status updates after submission.</p>
        
        <div class="hero-features">
            <span class="feature-tag"><span aria-hidden="true">✓</span> Secure details</span>
            <span class="feature-tag"><span aria-hidden="true">✓</span> Email updates</span>
            <span class="feature-tag"><span aria-hidden="true">✓</span> Live status tracking</span>
        </div>
    </div>

    <div class="registration-form-column">
        <form action="register.php" method="POST">
            <input type="hidden" name="service_type" value="<?php echo htmlspecialchars($service_type); ?>">

            <section class="form-card admission-section">
                <div class="step-indicator">
                    <div class="step-number" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <span class="step-label">Step 1</span>
                        <h3 class="step-title">Customer information</h3>
                    </div>
                </div>

                <div class="form-group">
                    <label for="full-name">Full name</label>
                    <input id="full-name" type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone-number">Phone number</label>
                        <input id="phone-number" type="tel" name="phone_number" class="form-control" placeholder="09XX XXX XXXX" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" class="form-control" placeholder="name@email.com" required>
                        <span class="field-hint">Status notifications will be sent here.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Complete address</label>
                    <textarea id="address" name="address" class="form-control" placeholder="House number, street, barangay, city, province" required></textarea>
                </div>
            </section>

            <section class="form-card admission-section">
                <div class="step-indicator">
                    <div class="step-number" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="6.5" y="2.5" width="11" height="19" rx="2" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M11 18.5h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <span class="step-label">Step 2</span>
                        <h3 class="step-title">Device and concern</h3>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="brand">Brand</label>
                        <input id="brand" type="text" name="brand" class="form-control" value="iPhone" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input id="model" type="text" name="model" class="form-control" placeholder="17 Pro" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="serial-number">Serial number</label>
                        <input id="serial-number" type="text" name="serial_number" class="form-control" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="imei">IMEI</label>
                        <input id="imei" type="text" name="imei" class="form-control" placeholder="Optional">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input id="color" type="text" name="color" class="form-control" placeholder="Black">
                    </div>
                    <div class="form-group">
                        <label for="accessories">Accessories included</label>
                        <input id="accessories" type="text" name="accessories" class="form-control" placeholder="Charger, case, SIM tray">
                    </div>
                </div>

                <div class="form-group">
                    <label for="problem-description">Describe the problem</label>
                    <textarea id="problem-description" name="problem_description" class="form-control" placeholder="Example: The screen does not turn on after the phone was dropped." required></textarea>
                </div>
            </section>

            <button type="submit" name="submit_registration" class="btn-submit admission-submit">Submit admission <span aria-hidden="true">&rarr;</span></button>
            <p class="admission-note">By submitting, you confirm that the information above is correct and that you agreed to the Data Privacy notice.</p>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($step === 1 && $service_type !== 'for_release'): ?>
    <script>
        document.getElementById('privacy-agree').addEventListener('click', function () {
            document.getElementById('privacy-modal').hidden = true;
        });
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
