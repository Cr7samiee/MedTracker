<?php
// fix.php - A script to fix hardcoded names, symbols, and empty hrefs across all HTML files in frontend/

$frontendDir = __DIR__ . '/frontend/';
$files = glob($frontendDir . '*.html');

foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        $originalContent = $content;

        // 1. Fix logos to be MedTracker and 💊 symbol
        // Doctor sidebar brand
        $content = preg_replace(
            '/<a href="#" class="sidebar-brand" style="text-decoration: none;">\s*<div style="display:flex; align-items:center; gap:0.5rem;">.*?<\/div>\s*<\/a>/s',
            '<a href="#" class="sidebar-brand" style="text-decoration: none;">
                    <span class="title">MedTracker</span>
                    <span class="subtitle">Healthcare Professional</span>
                </a>',
            $content
        );
        // Admin sidebar brand
        $content = preg_replace(
            '/<a href="#" class="sidebar-brand" style="text-decoration: none;">\s*<div style="display:flex; align-items:center; gap:0.5rem;">\s*<span[^>]*>🛡️<\/span>.*?<span class="title"[^>]*>System Administrator<\/span>.*?<\/div>\s*<\/a>/s',
            '<a href="#" class="sidebar-brand" style="text-decoration: none;">
                    <span class="title">MedTracker</span>
                    <span class="subtitle">System Administrator</span>
                </a>',
            $content
        );

        // 2. Remove hardcoded names (Hello David, Dr. Sarah Chen, etc.)
        // Patient dashboard
        $content = str_replace('<h2>Hello, David</h2>', '<h2 id="userNameDisplay">Hello, User</h2>', $content);
        // Doctor dashboard
        $content = str_replace('<p style="color:var(--text-muted); margin-bottom:0.25rem;">Welcome back, Dr. Sarah Chen</p>', '<p style="color:var(--text-muted); margin-bottom:0.25rem;" id="userNameDisplay">Welcome back, Doctor</p>', $content);
        
        // Remove patient profiles that use David/Test
        $content = preg_replace('/David \(Test Patient\)/i', 'User Account', $content);

        // 3. Fix href="#" links
        // We will map based on the keyword in the span
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Schedule<\/span>/si', 'href="schedule.html"$1>$2<span>Schedule</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Medications<\/span>/si', 'href="medications.html"$1>$2<span>Medications</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Reports<\/span>/si', 'href="reports.html"$1>$2<span>Reports</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Settings<\/span>/si', 'href="settings.html"$1>$2<span>Settings</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Patients<\/span>/si', 'href="doctor_dashboard.html"$1>$2<span>Patients</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Analytics<\/span>/si', 'href="doctor_dashboard.html"$1>$2<span>Analytics</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>System<\/span>/si', 'href="settings.html"$1>$2<span>System</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>User Accounts<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>User Accounts</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>System Analytics<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>System Analytics</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>System Params<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>System Params</span>', $content);
        $content = preg_replace('/href="#"([^>]*)>(.*?)<span>Schedule Configs<\/span>/si', 'href="admin_dashboard.html"$1>$2<span>Schedule Configs</span>', $content);

        // 4. Update image avatars if any hardcoded names
        $content = preg_replace('/name=Sarah\+Chen/i', 'name=Doctor', $content);

        // Write changes
        if ($originalContent !== $content) {
            file_put_contents($file, $content);
            echo "Updated: " . basename($file) . "\n";
        }
    }
}

echo "Fix complete.\n";
?>
