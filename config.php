<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'apple_service_center');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

session_start();

// Email Helper Function (Simulated & Mail Hook)
function sendStatusEmail($to, $name, $ticketNo, $status) {
    $subject = "Service Update - Ticket #{$ticketNo} [Fix Device]";
    $message = "Hello {$name},\n\n";
    $message .= "Your service ticket #{$ticketNo} status has been updated to: {$status}.\n\n";
    $message .= "Thank you for choosing Fix Device Service Center!\n";
    $headers = "From: no-reply@fixdevice.com\r\nReply-To: support@fixdevice.com";
    
    // Attempt standard mail send (or log)
    @mail($to, $subject, $message, $headers);
    return true;
}

function sendReleaseReportEmail(array $ticket, string $releaseSignature): bool {
    $to = $ticket['email'] ?? '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $escape = static function ($value): string {
        return htmlspecialchars((string)($value ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $dateReceived = !empty($ticket['created_at']) ? date('Y-m-d h:i A', strtotime($ticket['created_at'])) : '—';
    $releasedAt = date('Y-m-d h:i A');
    $cost = isset($ticket['estimated_cost']) && $ticket['estimated_cost'] !== null
        ? 'PHP ' . number_format((float)$ticket['estimated_cost'], 2)
        : 'N/A';
    $diagnosis = trim((string)($ticket['diagnosis'] ?? '')) ?: 'Not provided';
    $serviceNotes = trim((string)($ticket['repair_notes'] ?? '')) ?: 'Not provided';
    $recommendation = 'Device released to customer';

    $signatures = [];
    foreach ([
        'admission-signature' => ['Customer admission signature', $ticket['customer_signature'] ?? ''],
        'release-signature' => ['Client release signature', $releaseSignature],
    ] as $contentId => [$label, $dataUri]) {
        if (is_string($dataUri) && preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUri, $matches)) {
            $imageData = base64_decode($matches[1], true);
            if ($imageData !== false) {
                $signatures[$contentId] = ['label' => $label, 'data' => $imageData];
            }
        }
    }

    $signatureMarkup = static function (string $contentId, string $label) use ($signatures, $escape): string {
        if (!isset($signatures[$contentId])) {
            return '<span>Not recorded</span>';
        }
        return '<img src="cid:' . $escape($contentId) . '" alt="' . $escape($label) . '" style="display:block;width:180px;height:60px;object-fit:contain;border-bottom:1px solid #94a3b8">';
    };

    $customerRows = [
        ['Name', $ticket['customer_name'] ?? '—'],
        ['Address', $ticket['address'] ?? '—'],
        ['Contact', $ticket['phone_number'] ?? '—'],
        ['Email', $to],
    ];
    $productRows = [
        ['Product', trim(($ticket['brand'] ?? '') . ' ' . ($ticket['model'] ?? ''))],
        ['Serial', $ticket['serial_number'] ?? '—'],
        ['IMEI', $ticket['imei'] ?? '—'],
        ['Color', $ticket['color'] ?? '—'],
    ];
    $ticketRows = [
        ['SR No', $ticket['ticket_number'] ?? '—'],
        ['Date received', $dateReceived],
        ['Status', 'Released'],
        ['Warranty', $ticket['warranty_status'] ?? 'Pending assessment'],
        ['Priority', $ticket['priority'] ?? 'Normal'],
    ];
    $serviceRows = [
        ['Accessories', $ticket['accessories'] ?: 'None listed'],
        ['Assigned technician', $ticket['assigned_technician'] ?: '—'],
        ['Payment status', 'N/A'],
        ['Payment reference', 'N/A'],
    ];
    $renderRows = static function (array $rows) use ($escape): string {
        $markup = '';
        foreach ($rows as [$label, $value]) {
            $markup .= '<tr><th style="padding:4px 3px;text-align:left;color:#1766a3;font-size:11px">' . $escape($label) . '</th><td style="padding:4px 3px;font-size:11px">' . nl2br($escape($value)) . '</td></tr>';
        }
        return $markup;
    };
    $plainLines = [];
    foreach (array_merge($customerRows, $productRows, $ticketRows, $serviceRows, [['Total cost', $cost], ['Total paid', 'N/A'], ['Released at', $releasedAt]]) as [$label, $value]) {
        $plainLines[] = $label . ': ' . (string)$value;
    }

    $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,sans-serif;color:#263246">'
        . '<main style="max-width:850px;margin:auto;padding:22px;background:#fff">'
        . '<h1 style="margin:0 0 18px;padding:12px;background:#12609a;color:#fff;text-align:center;font-size:20px">SERVICE REPORT</h1>'
        . '<table style="width:100%;border-collapse:collapse"><tr>'
        . '<td style="width:34%;vertical-align:top;padding:6px"><h2 style="margin:0;color:#1766a3;font-size:13px">Customer Information</h2><table style="width:100%;border-collapse:collapse">' . $renderRows($customerRows) . '</table></td>'
        . '<td style="width:33%;vertical-align:top;padding:6px"><h2 style="margin:0;color:#1766a3;font-size:13px">Product Information</h2><table style="width:100%;border-collapse:collapse">' . $renderRows($productRows) . '</table></td>'
        . '<td style="width:33%;vertical-align:top;padding:6px"><h2 style="margin:0;color:#1766a3;font-size:13px">Service Ticket</h2><table style="width:100%;border-collapse:collapse">' . $renderRows($ticketRows) . '</table></td>'
        . '</tr></table>'
        . '<table style="width:100%;margin-top:16px;border-collapse:collapse;font-size:13px"><tr>'
        . '<th style="padding:8px;text-align:left;border-bottom:1px solid #cbd5e1">Symptoms / Customer Concern</th>'
        . '<th style="padding:8px;text-align:left;border-bottom:1px solid #cbd5e1">Diagnosis / Repair Notes</th>'
        . '<th style="padding:8px;text-align:left;border-bottom:1px solid #cbd5e1">Recommendation</th></tr><tr>'
        . '<td style="padding:8px;vertical-align:top;border:1px solid #e2e8f0">' . nl2br($escape($ticket['problem_description'] ?? '—')) . '</td>'
        . '<td style="padding:8px;vertical-align:top;border:1px solid #e2e8f0"><strong>Diagnosis</strong><br>' . nl2br($escape($diagnosis)) . '<br><br><strong>Service notes</strong><br>' . nl2br($escape($serviceNotes)) . '</td>'
        . '<td style="padding:8px;vertical-align:top;border:1px solid #e2e8f0">' . $escape($recommendation) . '</td>'
        . '</tr></table>'
        . '<h2 style="margin:16px 0 4px;color:#1766a3;font-size:14px">Functional Test / Service Details</h2>'
        . '<table style="width:100%;border-collapse:collapse">' . $renderRows($serviceRows) . '</table>'
        . '<table style="width:100%;margin:12px 0 0 auto;border-collapse:collapse;max-width:320px;font-size:12px">'
        . '<tr><th style="padding:5px;text-align:left;color:#1766a3">TOTAL COST</th><td style="padding:5px;text-align:right">' . $escape($cost) . '</td></tr>'
        . '<tr><th style="padding:5px;text-align:left;color:#1766a3">TOTAL PAID</th><td style="padding:5px;text-align:right">N/A</td></tr></table>'
        . '<table style="width:100%;margin-top:24px;font-size:12px"><tr>'
        . '<td style="width:50%;vertical-align:bottom;padding:8px">' . $signatureMarkup('admission-signature', 'Customer admission signature') . '<br>CLIENT\'S ADMISSION SIGNATURE<br>' . $escape($dateReceived) . '</td>'
        . '<td style="width:50%;vertical-align:bottom;padding:8px">' . $signatureMarkup('release-signature', 'Client release signature') . '<br>CLIENT\'S RELEASE SIGNATURE<br>' . $escape($releasedAt) . '</td>'
        . '</tr></table></main></body></html>';

    $plain = "SERVICE REPORT\r\n\r\n" . implode("\r\n", $plainLines)
        . "\r\n\r\nSymptoms / Customer Concern:\r\n" . (string)($ticket['problem_description'] ?? '—')
        . "\r\n\r\nDiagnosis:\r\n" . $diagnosis
        . "\r\n\r\nService notes:\r\n" . $serviceNotes
        . "\r\n\r\nRecommendation:\r\n" . $recommendation
        . "\r\n\r\nCustomer admission signature: " . (isset($signatures['admission-signature']) ? 'Included' : 'Not recorded')
        . "\r\nClient release signature: Included";

    $relatedBoundary = 'related_' . bin2hex(random_bytes(12));
    $alternativeBoundary = 'alternative_' . bin2hex(random_bytes(12));
    $body = "--{$relatedBoundary}\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"{$alternativeBoundary}\"\r\n\r\n";
    $body .= "--{$alternativeBoundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$plain}\r\n";
    $body .= "--{$alternativeBoundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$html}\r\n";
    $body .= "--{$alternativeBoundary}--\r\n";
    foreach ($signatures as $contentId => $signature) {
        $filename = $contentId === 'release-signature' ? 'client-release-signature.png' : 'customer-admission-signature.png';
        $body .= "--{$relatedBoundary}\r\n";
        $body .= "Content-Type: image/png; name=\"{$filename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-ID: <{$contentId}>\r\n";
        $body .= "Content-Disposition: inline; filename=\"{$filename}\"\r\n\r\n";
        $body .= chunk_split(base64_encode($signature['data'])) . "\r\n";
    }
    $body .= "--{$relatedBoundary}--\r\n";

    $subject = 'Service Report - Ticket #' . ($ticket['ticket_number'] ?? '');
    $headers = "From: no-reply@fixdevice.com\r\n";
    $headers .= "Reply-To: support@fixdevice.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/related; boundary=\"{$relatedBoundary}\"\r\n";

    return mail($to, $subject, $body, $headers);
}
?>
