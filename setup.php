<?php
declare(strict_types=1);
$error = ''; $done = false;
$storageDir = __DIR__ . '/storage'; $uploadDir = __DIR__ . '/uploads'; $dbFile = $storageDir . '/helpdesk.sqlite';

function setup_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function setup_detect_url(): string {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^(?:[A-Za-z0-9.\-]+|\[[0-9A-Fa-f:.]+\])(?::\d{1,5})?$/', $host)) $host = 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scheme . '://' . $host . $dir;
}
function setup_remove_db(string $file): void { foreach ([$file, $file . '-wal', $file . '-shm', $file . '.migrate.lock'] as $f) if (is_file($f)) @unlink($f); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app = trim((string)($_POST['app_name'] ?? 'HelpDesk')); $name = trim((string)($_POST['admin_name'] ?? ''));
    $email = trim((string)($_POST['admin_email'] ?? '')); $pass = (string)($_POST['admin_password'] ?? '');
    $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 12) $error = 'Enter an administrator name, valid email, and password of at least 12 characters.';
    elseif ($baseUrl !== '' && (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $baseUrl))) $error = 'The public URL must start with http:// or https://.';
    elseif (file_exists(__DIR__ . '/config.php')) $error = 'HelpDesk is already installed. Remove config.php only if you intend to reinstall.';
    elseif (PHP_VERSION_ID < 80100) $error = 'HelpDesk requires PHP 8.1 or newer (this server runs ' . PHP_VERSION . ').';
    elseif (!extension_loaded('pdo_sqlite')) $error = 'The pdo_sqlite PHP extension is required but is not enabled.';
    else {
        $createdDb = false;
        try {
            foreach ([$storageDir, $uploadDir] as $dir) {
                if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Could not create the ' . basename($dir) . '/ folder. Create it and make it writable by the web server.');
                if (!is_writable($dir)) throw new RuntimeException('The ' . basename($dir) . '/ folder is not writable by the web server.');
                if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nOptions -Indexes\n");
            }
            if (is_file($dbFile) && filesize($dbFile) > 0) {
                // A leftover database from an earlier failed install may be replaced; one that holds users may not.
                $users = 0;
                try { $old = new PDO('sqlite:' . $dbFile); $users = (int)$old->query('SELECT COUNT(*) FROM users')->fetchColumn(); $old = null; } catch (Throwable $e) { $users = 0; }
                if ($users > 0) throw new RuntimeException('A database with user accounts already exists at storage/helpdesk.sqlite. Move it away first if you really want to reinstall.');
                setup_remove_db($dbFile);
            }
            $createdDb = true;
            $example = require __DIR__ . '/config.example.php'; $tz = (string)($example['timezone'] ?? 'UTC'); $app = $app !== '' ? $app : 'HelpDesk';
            $pdo = new PDO('sqlite:' . $dbFile); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec((string)file_get_contents(__DIR__ . '/schema.sql'));
            $pdo->prepare('INSERT INTO users(name,email,password_hash,role_id,active,created_at) VALUES(?,?,?,?,1,?)')->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 1, date('c')]);
            $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?)')->execute(['app_name', $app]);
            foreach (['mail_from_name' => $app, 'mail_from' => $email, 'notify_ticket_created' => '1', 'notify_ticket_assigned' => '1', 'notify_ticket_updated' => '1', 'notify_ticket_resolved' => '1', 'theme' => 'light', 'allow_guest' => '0'] as $k => $v)
                $pdo->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute([$k, $v]);
            $pdo = null;
            // config.php is written LAST so a failed install never leaves HelpDesk looking "already installed".
            $configText = "<?php\nreturn [\n"
                . "    'app_name' => " . var_export($app, true) . ",\n"
                . "    'base_url' => " . var_export($baseUrl, true) . ",\n"
                . "    'timezone' => " . var_export($tz, true) . ",\n"
                . "    'database' => __DIR__ . '/storage/helpdesk.sqlite',\n"
                . "    'upload_dir' => __DIR__ . '/uploads',\n"
                . "    'session_name' => 'helpdesk_session',\n"
                . "    'trusted_proxy_header' => '',\n"
                . "];\n";
            if (@file_put_contents(__DIR__ . '/config.php', $configText) === false) throw new RuntimeException('Could not write config.php. Make the HelpDesk folder writable during setup, or copy config.example.php to config.php by hand.');
            @chmod($storageDir, 0750); @chmod($uploadDir, 0750); @chmod(__DIR__ . '/config.php', 0640);
            $done = true;
        } catch (Throwable $e) {
            if ($createdDb) setup_remove_db($dbFile);
            $error = 'Installation failed: ' . setup_h($e->getMessage());
        }
    }
}
$suggestedUrl = (string)($_POST['base_url'] ?? setup_detect_url());
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install HelpDesk</title><link rel="stylesheet" href="public/style.css"></head><body class="install"><main class="install-card"><div class="brand-mark">H</div><h1>Set up HelpDesk</h1><?php if ($done): ?><div class="notice success">Installation complete. <a href="index.php">Sign in to HelpDesk</a>. For security, delete or rename setup.php now.</div><?php else: ?><?php if ($error): ?><div class="notice error"><?= $error ?></div><?php endif; ?><p class="muted">Create your first administrator account. The SQLite database and its protected folders are created automatically.</p><form method="post"><label>Application name<input name="app_name" value="<?= setup_h((string)($_POST['app_name'] ?? 'HelpDesk')) ?>" required></label><label>Public URL <small>(used in email links &mdash; check it is correct)</small><input name="base_url" value="<?= setup_h($suggestedUrl) ?>" placeholder="https://support.example.com"></label><label>Administrator name<input name="admin_name" value="<?= setup_h((string)($_POST['admin_name'] ?? '')) ?>" required></label><label>Administrator email<input type="email" name="admin_email" value="<?= setup_h((string)($_POST['admin_email'] ?? '')) ?>" required></label><label>Password <small>(12 characters minimum)</small><input type="password" name="admin_password" minlength="12" required></label><button class="btn primary">Install HelpDesk</button></form><?php endif; ?></main></body></html>
