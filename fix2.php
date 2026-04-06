<?php
// fix2.php

$frontendDir = __DIR__ . '/frontend/';
$files = glob($frontendDir . '*.html');

foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        $originalContent = $content;

        // 1. Replace empty links or void(0)
        $content = str_replace('href="#"', 'href="javascript:void(0)"', $content); // temp
        
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Schedule<\/span>/si', 'href="schedule.html"$1>$2<span>Schedule</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Medications<\/span>/si', 'href="medications.html"$1>$2<span>Medications</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Reports<\/span>/si', 'href="reports.html"$1>$2<span>Reports</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Settings<\/span>/si', 'href="settings.html"$1>$2<span>Settings</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Patients<\/span>/si', 'href="doctor_dashboard.html"$1>$2<span>Patients</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Analytics<\/span>/si', 'href="doctor_dashboard.html"$1>$2<span>Analytics</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>System<\/span>/si', 'href="settings.html"$1>$2<span>System</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>User Accounts<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>User Accounts</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>System Analytics<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>System Analytics</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>System Params<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>System Params</span>', $content);
        $content = preg_replace('/href="javascript:void\(0\)"([^>]*)>(.*?)<span>Schedule Configs<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>Schedule Configs</span>', $content);

        // 2. Fix logos
        // Doctor / Admin
        $content = preg_replace(
            '/<a href="(javascript:void\(0\)|#)" class="sidebar-brand" style="text-decoration: none;">\s*<div style="display:flex; align-items:center; gap:0.5rem;">.*?<\/div>\s*<\/a>/s',
            '<a href="javascript:void(0)" class="sidebar-brand" style="text-decoration: none;">
                    <span class="title" style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                        <span class="logo-icon">💊</span> MedTracker
                    </span>
                    <span class="subtitle">Management Portal</span>
                </a>',
            $content
        );

        // 3. Usernames
        $content = preg_replace('/Hello, David/i', 'Hello, <span class="user-name-display">User</span>', $content);
        $content = preg_replace('/Welcome back, Dr\. Sarah Chen/i', 'Welcome back, <span class="user-name-display">Doctor</span>', $content);
        $content = preg_replace('/David \(Test Patient\)/i', '<span class="user-name-display">Patient</span>', $content);
        $content = preg_replace('/Sarah J\. Miller/i', 'Logged user', $content);
        $content = preg_replace('/Dr\. Sarah Chen/i', '<span class="user-name-display">Doctor</span>', $content);
        $content = preg_replace('/Attending Physician \| Cardiology Specialist/i', 'Assigned Healthcare Professional', $content);

        if ($originalContent !== $content) {
            file_put_contents($file, $content);
            echo "Updated: " . basename($file) . "\n";
        }
    }
}
echo "Fix complete.\n";
?>
