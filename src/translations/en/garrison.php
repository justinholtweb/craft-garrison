<?php

return [
    // Plugin
    'Garrison' => 'Garrison',

    // Navigation
    'Dashboard' => 'Dashboard',
    'Scanner' => 'Scanner',
    'Shield' => 'Shield',
    'Sentinel' => 'Sentinel',
    'Settings' => 'Settings',

    // Dashboard
    'Garrison Dashboard' => 'Garrison Dashboard',
    'Risk Score' => 'Risk Score',
    'Last Scan' => 'Last Scan',
    'Passed' => 'Passed',
    'Warnings' => 'Warnings',
    'Failed' => 'Failed',
    'Critical' => 'Critical',
    'Scanned {date}' => 'Scanned {date}',
    'No scans have been run yet.' => 'No scans have been run yet.',
    'Run Your First Scan' => 'Run Your First Scan',
    'View Scanner' => 'View Scanner',
    'View Results' => 'View Results',

    // Scanner
    'Security Scanner' => 'Security Scanner',
    'Run Scan' => 'Run Scan',
    'Scanning...' => 'Scanning...',
    'History' => 'History',
    'Scan Results' => 'Scan Results',
    'Scan History' => 'Scan History',
    'Last Scan Results' => 'Last Scan Results',
    'Check' => 'Check',
    'Category' => 'Category',
    'Severity' => 'Severity',
    'Status' => 'Status',
    'Date' => 'Date',
    'Triggered By' => 'Triggered By',
    'Duration' => 'Duration',
    'Checks' => 'Checks',
    'Check Results' => 'Check Results',
    'Back to Scanner' => 'Back to Scanner',
    'View' => 'View',
    'Security scan complete. Risk score: {score}' => 'Security scan complete. Risk score: {score}',
    'No scans have been run yet. Run your first security scan to check your site.' => 'No scans have been run yet. Run your first security scan to check your site.',
    'No scan history yet.' => 'No scan history yet.',

    // Shield
    'Login Protection' => 'Login Protection',
    'Brute-force protection and login lockout settings.' => 'Brute-force protection and login lockout settings.',
    'IP Management' => 'IP Management',
    'Allow/block IP addresses and CIDR ranges.' => 'Allow/block IP addresses and CIDR ranges.',
    'Rate Limiting' => 'Rate Limiting',
    'Limit request rates to prevent abuse.' => 'Limit request rates to prevent abuse.',
    'WAF' => 'WAF',
    'Web Application Firewall' => 'Web Application Firewall',
    'Web Application Firewall request filtering.' => 'Web Application Firewall request filtering.',
    'Back to Shield' => 'Back to Shield',

    // Sentinel
    'Audit Log' => 'Audit Log',
    'Track all user and system actions.' => 'Track all user and system actions.',
    'File Integrity' => 'File Integrity',
    'Monitor critical files for unauthorized changes.' => 'Monitor critical files for unauthorized changes.',
    'Back to Sentinel' => 'Back to Sentinel',

    // Settings
    'Garrison Settings' => 'Garrison Settings',
    'Max Login Attempts' => 'Max Login Attempts',
    'Number of failed login attempts before lockout.' => 'Number of failed login attempts before lockout.',
    'Lockout Duration' => 'Lockout Duration',
    'How long to lock out an IP after exceeding max attempts (seconds).' => 'How long to lock out an IP after exceeding max attempts (seconds).',
    'Attempt Window' => 'Attempt Window',
    'Time window for counting login attempts (seconds).' => 'Time window for counting login attempts (seconds).',
    'Enable Audit Log' => 'Enable Audit Log',
    'Track user and system actions.' => 'Track user and system actions.',
    'Scan Schedule' => 'Scan Schedule',
    'How often to automatically run security scans. Requires Plus edition.' => 'How often to automatically run security scans. Requires Plus edition.',
    'Scan Schedule Hour' => 'Scan Schedule Hour',
    'Hour of day (0-23) for daily/weekly/monthly scans.' => 'Hour of day (0-23) for daily/weekly/monthly scans.',
    'Prune Blocked Requests' => 'Prune Blocked Requests',
    'Delete blocked request logs older than this many days.' => 'Delete blocked request logs older than this many days.',
    'Prune Login Attempts' => 'Prune Login Attempts',
    'Delete login attempt logs older than this many days.' => 'Delete login attempt logs older than this many days.',
    'Settings saved.' => 'Settings saved.',
    'Couldn\'t save settings.' => 'Couldn\'t save settings.',

    // Notifications
    'Notification Settings' => 'Notification Settings',
    'Enable Notifications' => 'Enable Notifications',
    'Send notifications when security events occur. Requires Plus edition.' => 'Send notifications when security events occur. Requires Plus edition.',
    'Notify on Scan Failure' => 'Notify on Scan Failure',
    'Notify on Threat Detected' => 'Notify on Threat Detected',
    'Notify on Login Lockout' => 'Notify on Login Lockout',
    'Channels' => 'Channels',
    'Slack Webhook URL' => 'Slack Webhook URL',
    'Discord Webhook URL' => 'Discord Webhook URL',
    'Custom Webhook URL' => 'Custom Webhook URL',
    'JSON payload will be POSTed to this URL.' => 'JSON payload will be POSTed to this URL.',

    // Scanner Settings
    'Scanner Settings' => 'Scanner Settings',
    'Available Security Checks' => 'Available Security Checks',
    'Handle' => 'Handle',
    'Name' => 'Name',
    'To disable specific checks, set the `enabledChecks` config setting in your garrison.php config file.' => 'To disable specific checks, set the `enabledChecks` config setting in your garrison.php config file.',

    // Advanced Settings
    'Advanced Settings' => 'Advanced Settings',
    'Advanced Configuration' => 'Advanced Configuration',
    'Advanced settings are configured via the config/garrison.php config file for multi-environment support.' => 'Advanced settings are configured via the config/garrison.php config file for multi-environment support.',
    'Config File' => 'Config File',
    'Copy src/config.php to config/garrison.php in your Craft project and adjust as needed.' => 'Copy src/config.php to config/garrison.php in your Craft project and adjust as needed.',
    'Current Edition' => 'Current Edition',

    // Permissions
    'Access Garrison' => 'Access Garrison',
    'Run security scans' => 'Run security scans',
    'View audit log' => 'View audit log',
    'Manage shield rules' => 'Manage shield rules',
    'Manage Garrison settings' => 'Manage Garrison settings',
];
