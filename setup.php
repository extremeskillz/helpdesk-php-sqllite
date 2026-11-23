<?php
declare(strict_types=1);
$error=''; $done=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    $app=trim($_POST['app_name']??'HelpDesk'); $name=trim($_POST['admin_name']??''); $email=trim($_POST['admin_email']??''); $pass=$_POST['admin_password']??'';
    if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<12) $error='Enter an administrator name, valid email, and password of at least 12 characters.';
    elseif(file_exists(__DIR__.'/config.php')) $error='HelpDesk is already installed. Remove config.php only if you intend to reinstall.';
    else {
        try {
            $cfg=require __DIR__.'/config.example.php'; $cfg['app_name']=$app?:'HelpDesk'; file_put_contents(__DIR__.'/config.php',"<?php\nreturn ".var_export($cfg,true).";\n");
            $pdo=new PDO('sqlite:'.$cfg['database']); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA journal_mode=WAL');
            $schema=file_get_contents(__DIR__.'/schema.sql'); $pdo->exec($schema);
            $pdo->prepare('INSERT INTO users(name,email,password_hash,role_id,active,created_at) VALUES(?,?,?,?,1,?)')->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),1,date('c')]);
            $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?)')->execute(['app_name',$cfg['app_name']]);
            foreach(['mail_from_name'=>$cfg['app_name'],'mail_from'=>$email,'notify_ticket_created'=>'1','notify_ticket_assigned'=>'1','notify_ticket_updated'=>'1','notify_ticket_resolved'=>'1','theme'=>'light','allow_guest'=>'0'] as $k=>$v) $pdo->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute([$k,$v]);
            @chmod(__DIR__.'/storage',0750); @chmod(__DIR__.'/uploads',0750); $done=true;
        } catch(Throwable $e) { $error='Installation failed: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install HelpDesk</title><link rel="stylesheet" href="public/style.css"></head><body class="install"><main class="install-card"><div class="brand-mark">H</div><h1>Set up HelpDesk</h1><?php if($done): ?><div class="notice success">Installation complete. <a href="index.php">Sign in to HelpDesk</a>. For security, remove or rename setup.php after installation.</div><?php else: ?><?php if($error): ?><div class="notice error"><?=$error?></div><?php endif; ?><p class="muted">Create your first administrator account. The SQLite database will be created automatically.</p><form method="post"><label>Application name<input name="app_name" value="<?=htmlspecialchars($_POST['app_name']??'HelpDesk')?>" required></label><label>Administrator name<input name="admin_name" value="<?=htmlspecialchars($_POST['admin_name']??'')?>" required></label><label>Administrator email<input type="email" name="admin_email" value="<?=htmlspecialchars($_POST['admin_email']??'')?>" required></label><label>Password <small>(12 characters minimum)</small><input type="password" name="admin_password" minlength="12" required></label><button class="btn primary">Install HelpDesk</button></form><?php endif; ?></main></body></html>
