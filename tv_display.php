<?php
require_once 'config.php';

$serving_stmt = $pdo->query(
    "SELECT ticket_number, release_ticket_number, queue_counter, created_at
     FROM tickets
     WHERE queue_status IN ('Called', 'Serving')
     ORDER BY queue_called_at ASC, id ASC
     LIMIT 1"
);
$now_serving = $serving_stmt->fetch();

$waiting_stmt = $pdo->query(
    "SELECT ticket_number, release_ticket_number, status, release_queue_joined_at, created_at
     FROM tickets
     WHERE queue_status = 'Waiting' AND service_type = 'new_service'
       AND (status <> 'Ready for Release' OR release_queue_joined_at IS NOT NULL)
     ORDER BY created_at ASC, id ASC
     LIMIT 6"
);
$waiting_tickets = $waiting_stmt->fetchAll();

$waiting_count = (int)$pdo->query(
    "SELECT COUNT(*) FROM tickets
     WHERE queue_status = 'Waiting' AND service_type = 'new_service'
       AND (status <> 'Ready for Release' OR release_queue_joined_at IS NOT NULL)"
)->fetchColumn();

$current_time = new DateTimeImmutable('now');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="20">
    <title>TV Display - Fix Device Service Center</title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/assets/style.css'); ?>">
</head>
<body class="tv-display-page">
    <header class="tv-display-header">
        <a href="index.php" class="tv-display-brand" aria-label="Fix Device Service Center">
            <span class="tv-brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3.5 17.5a2.1 2.1 0 0 0 3 3l4.8-4.8a5 5 0 0 0 6.4-6.4l-3.1 3.1-3.1-3.1 3.2-3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span><strong>Fix Device</strong><small>Service Center</small></span>
        </a>
        <div class="tv-clock">
            <span class="tv-clock-time" id="tv-clock-time"><?php echo htmlspecialchars($current_time->format('g:i A')); ?></span>
            <span class="tv-clock-date"><?php echo htmlspecialchars($current_time->format('l, F j')); ?></span>
        </div>
    </header>

    <main class="tv-display-main">
        <section class="tv-serving-panel" aria-labelledby="tv-serving-heading">
            <div class="tv-serving-label" id="tv-serving-heading">
                <span class="tv-live-dot" aria-hidden="true"></span>
                Now serving
            </div>

            <?php if ($now_serving): ?>
                <?php $serving_reference = $now_serving['queue_counter'] === 'Released Counter'
                    ? ($now_serving['release_ticket_number'] ?: $now_serving['ticket_number'])
                    : $now_serving['ticket_number']; ?>
                <p class="tv-serving-number"><?php echo htmlspecialchars($serving_reference); ?></p>
                <p class="tv-counter-message">Please proceed to <strong><?php echo htmlspecialchars($now_serving['queue_counter'] ?: 'Counter 1'); ?></strong></p>
                <div class="tv-ticket-chip"><?php echo htmlspecialchars($serving_reference); ?></div>
            <?php else: ?>
                <p class="tv-empty-serving">No ticket is being served</p>
                <p class="tv-counter-message">Please wait for your number to be called.</p>
            <?php endif; ?>
        </section>

        <aside class="tv-waiting-panel" aria-labelledby="tv-waiting-heading">
            <div class="tv-waiting-heading">
                <span class="tv-waiting-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M3.5 19v-1a5.5 5.5 0 0 1 11 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a4.5 4.5 0 0 1 2.5 4v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <span>Up next</span>
                    <h2 id="tv-waiting-heading">Waiting Queue</h2>
                </div>
            </div>

            <?php if ($waiting_tickets): ?>
                <ol class="tv-waiting-list">
                    <?php foreach ($waiting_tickets as $index => $waiting_ticket): ?>
                        <?php $waiting_reference = $waiting_ticket['status'] === 'Ready for Release' && $waiting_ticket['release_queue_joined_at']
                            ? ($waiting_ticket['release_ticket_number'] ?: $waiting_ticket['ticket_number'])
                            : $waiting_ticket['ticket_number']; ?>
                        <li>
                            <span class="tv-queue-position"><?php echo $index + 1; ?></span>
                            <span class="tv-queue-number"><?php echo htmlspecialchars($waiting_reference); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($waiting_count > count($waiting_tickets)): ?>
                    <p class="tv-more-waiting">And <?php echo $waiting_count - count($waiting_tickets); ?> more waiting</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="tv-no-waiting">No customers waiting</p>
            <?php endif; ?>
        </aside>
    </main>

    <script>
        const clock = document.getElementById('tv-clock-time');
        const updateClock = () => {
            clock.textContent = new Intl.DateTimeFormat([], {
                hour: 'numeric',
                minute: '2-digit'
            }).format(new Date());
        };
        updateClock();
        window.setInterval(updateClock, 30000);
    </script>
</body>
</html>
