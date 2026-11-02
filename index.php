<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div class="welcome-header">
    <p class="subtitle">Welcome to Fix Device</p>
    <h2>How can we assist you today?</h2>
    <p class="desc">Select the service you need. The system will generate a queue number for your visit.</p>
</div>

<div class="card-grid">
    <!-- New Service Request -->
    <a href="register.php?step=1" class="option-card">
        <div>
            <div class="card-icon blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="7" y="2.75" width="10" height="18.5" rx="1.8" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M11 18.25h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <p class="badge">New Service Request</p>
            <h3>New Client</h3>
            <p>Register a device, describe the concern, and receive a queue and tracking reference.</p>
        </div>
        <span class="card-link">Start admission &rarr;</span>
    </a>

    <!-- For Release -->
    <a href="register.php?service_type=for_release&step=1" class="option-card">
        <div>
            <div class="card-icon green" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m4.5 7.7 7.5 4.2 7.5-4.2M12 12v8.5M8 5.3l8 4.5M15.5 14.5l1.5 1.5 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="badge">Device Collection</p>
            <h3>For Release</h3>
            <p>Your device is ready for pickup. Verify your ticket and join the release queue.</p>
        </div>
        <span class="card-link">Join release queue &rarr;</span>
    </a>
</div>

<p class="home-track-link">
    Already have a ticket? <a href="track.php">Track repair status</a>
</p>

<?php include 'includes/footer.php'; ?>
