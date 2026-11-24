<?php
return [
    'app_name' => 'HelpDesk',
    // Public address of this installation, e.g. https://support.example.com/helpdesk
    // Strongly recommended: links in emails are built from this value. When blank, the
    // (validated) request host name is used instead.
    'base_url' => '',
    'timezone' => 'America/New_York',
    // Keep this inside storage/ (protected by storage/.htaccess) or, better, outside the web root.
    'database' => __DIR__ . '/storage/helpdesk.sqlite',
    'upload_dir' => __DIR__ . '/uploads',
    'session_name' => 'helpdesk_session',
    // Only when HelpDesk runs behind a reverse proxy / CDN that OVERWRITES this header,
    // e.g. 'HTTP_X_FORWARDED_FOR' or 'HTTP_CF_CONNECTING_IP'. Used for sign-in throttling and audit IPs.
    'trusted_proxy_header' => '',
];
