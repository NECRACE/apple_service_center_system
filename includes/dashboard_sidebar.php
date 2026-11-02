<aside class="dashboard-sidebar">
    <a href="dashboard.php" class="dashboard-brand">
        <span class="dashboard-brand-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3.5 17.5a2.1 2.1 0 0 0 3 3l4.8-4.8a5 5 0 0 0 6.4-6.4l-3.1 3.1-3.1-3.1 3.2-3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <span><strong>Fix Device</strong><small>Service Center</small></span>
    </a>

    <nav class="dashboard-nav" aria-label="Staff navigation">
        <a href="dashboard.php" class="<?php echo ($active_dashboard_nav ?? '') === 'dashboard' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="3" width="8" height="5" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="10" width="8" height="11" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>
            Dashboard
        </a>
        <a href="register.php?step=1">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Next Admission
        </a>
        <a href="staff_counter.php" class="<?php echo ($active_dashboard_nav ?? '') === 'counter' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Staff Counter
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="users.php" class="<?php echo ($active_dashboard_nav ?? '') === 'users' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 20v-1a6.5 6.5 0 0 1 13 0v1M16 5.2a3.5 3.5 0 0 1 0 6.6M18 14a5 5 0 0 1 3.5 4.8v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                User Access
            </a>
        <?php endif; ?>
        <a href="tv_display.php" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            TV Display
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="reports.php" class="<?php echo ($active_dashboard_nav ?? '') === 'reports' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5M4 19h17M8 15v-3M13 15V8M18 15V5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Report
            </a>
        <?php endif; ?>
    </nav>

    <a href="logout.php" class="dashboard-signout">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Sign out
    </a>
</aside>
