<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('HelpDesk is not installed. Run /setup.php first.'); }
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'UTC');
session_name($config['session_name'] ?? 'helpdesk_session');
session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),'samesite'=>'Lax']);
session_start();

function migrate_v12(PDO $pdo): void {
    $cols=$pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC); $names=array_column($cols,'name');
    foreach(['guest_name'=>'TEXT','guest_email'=>'TEXT','guest_phone'=>'TEXT','guest_company'=>'TEXT','guest_department'=>'TEXT'] as $col=>$type) if(!in_array($col,$names,true)) $pdo->exec("ALTER TABLE tickets ADD COLUMN $col $type");
    $statusCols=$pdo->query("PRAGMA table_info(statuses)")->fetchAll(PDO::FETCH_ASSOC); $statusNames=array_column($statusCols,'name');
    if(!in_array('active',$statusNames,true)) $pdo->exec("ALTER TABLE statuses ADD COLUMN active INTEGER NOT NULL DEFAULT 1");
    $userCols=$pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC); $userNames=array_column($userCols,'name');
    foreach(['email_verified_at'=>'TEXT','otp_verified_at'=>'TEXT','last_activity_at'=>'TEXT','phone'=>'TEXT','company'=>'TEXT','department'=>'TEXT'] as $col=>$type) if(!in_array($col,$userNames,true)) $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE, PRIMARY KEY(user_id,group_id))");
    // Rebuild legacy tickets table once so guest tickets can have a NULL user_id.
    $ticketInfo=$pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC); $userIdInfo=null; foreach($ticketInfo as $c) if($c['name']==='user_id') $userIdInfo=$c;
    if($userIdInfo && (int)$userIdInfo['notnull']===1) {
        $pdo->exec('PRAGMA foreign_keys=OFF');
        try {
            $pdo->beginTransaction();
            $pdo->exec("CREATE TABLE tickets_new(id INTEGER PRIMARY KEY AUTOINCREMENT,ticket_no TEXT UNIQUE NOT NULL,user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,subject TEXT NOT NULL,description TEXT NOT NULL,category_id INTEGER REFERENCES categories(id),priority_id INTEGER NOT NULL REFERENCES priorities(id),status_id INTEGER NOT NULL REFERENCES statuses(id),group_id INTEGER REFERENCES groups(id) ON DELETE SET NULL,assigned_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,created_at TEXT NOT NULL,updated_at TEXT NOT NULL,resolved_at TEXT,guest_name TEXT,guest_email TEXT,guest_phone TEXT,guest_company TEXT,guest_department TEXT)");
            $pdo->exec('INSERT INTO tickets_new(id,ticket_no,user_id,subject,description,category_id,priority_id,status_id,group_id,assigned_user_id,created_at,updated_at,resolved_at,guest_name,guest_email,guest_phone,guest_company,guest_department) SELECT id,ticket_no,user_id,subject,description,category_id,priority_id,status_id,group_id,assigned_user_id,created_at,updated_at,resolved_at,guest_name,guest_email,guest_phone,guest_company,guest_department FROM tickets');
            $pdo->exec('DROP TABLE tickets'); $pdo->exec('ALTER TABLE tickets_new RENAME TO tickets');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status_id)'); $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_user ON tickets(user_id)'); $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_updated ON tickets(updated_at)');
            // Convert the old placeholder account to true guest ownership and remove it.
            $sys=$pdo->query("SELECT id FROM users WHERE email='guest-system@invalid.local'")->fetchColumn();
            if($sys!==false) {
                $pdo->prepare('UPDATE tickets SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('UPDATE tickets SET assigned_user_id=NULL WHERE assigned_user_id=?')->execute([(int)$sys]);
                $pdo->prepare('UPDATE ticket_comments SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]);
                $pdo->prepare('UPDATE ticket_attachments SET uploaded_by=NULL WHERE uploaded_by=?')->execute([(int)$sys]);
                $pdo->prepare('UPDATE audit_logs SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]);
                if($pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='ticket_history'")->fetchColumn()) $pdo->prepare('UPDATE ticket_history SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]);
                $pdo->prepare('DELETE FROM user_groups WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('DELETE FROM group_members WHERE user_id=?')->execute([(int)$sys]);
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$sys]);
            }
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        finally { $pdo->exec('PRAGMA foreign_keys=ON'); }
    } else {
        $sys=$pdo->query("SELECT id FROM users WHERE email='guest-system@invalid.local'")->fetchColumn();
        if($sys!==false) { $pdo->prepare('UPDATE tickets SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('UPDATE tickets SET assigned_user_id=NULL WHERE assigned_user_id=?')->execute([(int)$sys]); $pdo->prepare('UPDATE ticket_comments SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('UPDATE ticket_attachments SET uploaded_by=NULL WHERE uploaded_by=?')->execute([(int)$sys]); $pdo->prepare('UPDATE audit_logs SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]); if($pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='ticket_history'")->fetchColumn()) $pdo->prepare('UPDATE ticket_history SET user_id=NULL WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('DELETE FROM user_groups WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('DELETE FROM group_members WHERE user_id=?')->execute([(int)$sys]); $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$sys]); }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_history (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, action TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_challenges (id TEXT PRIMARY KEY, email TEXT NOT NULL, purpose TEXT NOT NULL, code_hash TEXT NOT NULL, payload TEXT, session_hash TEXT NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_otp_email_purpose ON otp_challenges(email,purpose,created_at)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, permission_key TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE, PRIMARY KEY(role_id,permission_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_roles (group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, PRIMARY KEY(group_id,role_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE, PRIMARY KEY(user_id,group_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, PRIMARY KEY(user_id,role_id))");
    $permissionCatalog = [
      'admin.access'=>'Access protected system administration',
      'tickets.create'=>'Create support tickets','tickets.view_own'=>'View own tickets','tickets.view_group'=>'View tickets in assigned groups or assigned to the user','tickets.view_all'=>'View all tickets',
      'tickets.edit'=>'Edit permitted tickets','tickets.reply'=>'Post public ticket replies','tickets.internal_note'=>'Add internal ticket notes','tickets.assign'=>'Assign tickets and groups','tickets.change_status'=>'Change ticket status','tickets.change_priority'=>'Change ticket priority','tickets.manage'=>'Legacy ticket management permission',
      'users.manage'=>'Create and edit user accounts','users.disable'=>'Disable user accounts','users.delete'=>'Delete user accounts',
      'groups.manage'=>'Create and edit support groups','groups.manage_members'=>'Manage group membership',
      'roles.view'=>'View roles','roles.manage'=>'Create and edit roles','permissions.manage'=>'Assign permissions to roles',
      'settings.manage'=>'Manage system settings','categories.manage'=>'Manage ticket categories','statuses.manage'=>'Manage ticket statuses','reports.view_group'=>'View reports for assigned groups','reports.view_all'=>'View all reports',
    ];
    $ins=$pdo->prepare('INSERT OR IGNORE INTO permissions(permission_key,description) VALUES(?,?)'); foreach($permissionCatalog as $key=>$description)$ins->execute([$key,$description]);
    // Preserve legacy primary roles and make direct role assignments additive.
    $pdo->exec('INSERT OR IGNORE INTO user_roles(user_id,role_id) SELECT id,role_id FROM users');
    // If a legacy group granted the protected admin role, preserve that access as a direct role before removing the group grant.
    $pdo->exec("UPDATE users SET role_id=(SELECT id FROM roles WHERE name='admin') WHERE id IN (SELECT ug.user_id FROM user_groups ug JOIN group_roles gr ON gr.group_id=ug.group_id JOIN roles r ON r.id=gr.role_id WHERE r.name='admin')");
    $pdo->exec("INSERT OR IGNORE INTO user_roles(user_id,role_id) SELECT DISTINCT ug.user_id,gr.role_id FROM user_groups ug JOIN group_roles gr ON gr.group_id=ug.group_id JOIN roles r ON r.id=gr.role_id WHERE r.name='admin'");
    $pdo->exec("DELETE FROM group_roles WHERE role_id=(SELECT id FROM roles WHERE name='admin')");
    // The reserved admin.access flag belongs only to the protected System Administrator role.
    $pdo->exec("DELETE FROM role_permissions WHERE permission_id=(SELECT id FROM permissions WHERE permission_key='admin.access') AND role_id<>(SELECT id FROM roles WHERE name='admin')");
    $legacyMaps = [
      'admin'=>array_keys($permissionCatalog),
      'tech'=>['tickets.view_own','tickets.view_group','tickets.edit','tickets.reply','tickets.internal_note','tickets.assign','tickets.change_status','tickets.change_priority','tickets.manage','reports.view_group'],
      'supervisor'=>['tickets.view_own','tickets.view_group','tickets.edit','tickets.reply','tickets.internal_note','tickets.assign','tickets.change_status','tickets.change_priority','tickets.manage','reports.view_group'],
      'user'=>['tickets.create','tickets.view_own','tickets.reply']
    ];
    $count=(int)$pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn();
    if($count===0){$roles=$pdo->query('SELECT id,name FROM roles')->fetchAll(PDO::FETCH_ASSOC);foreach($roles as $role){foreach(($legacyMaps[$role['name']]??[]) as $key){$q=$pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE permission_key=?');$q->execute([$role['id'],$key]);}}}
    else { // Add new granular equivalents to legacy roles without removing any custom grants.
      foreach($pdo->query('SELECT id,name FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $role){foreach(($legacyMaps[$role['name']]??[]) as $key){$q=$pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE permission_key=?');$q->execute([$role['id'],$key]);}}
    }
    $pdo->exec('INSERT OR IGNORE INTO user_groups(user_id,group_id) SELECT gm.user_id,gm.group_id FROM group_members gm');
}
function db(): PDO {
    static $pdo = null; global $config;
    if ($pdo instanceof PDO) return $pdo;
    $path = $config['database'];
    if (!is_file($path)) throw new RuntimeException('Database missing. Run setup.php.');
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON'); $pdo->exec('PRAGMA journal_mode = WAL'); $pdo->exec('PRAGMA busy_timeout = 5000');
    // Apply repeat-safe v1.2 guest-ticket schema additions on existing installations.
    migrate_v12($pdo);
    return $pdo;
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page='dashboard', array $params=[]): string { return 'index.php?' . http_build_query(['page'=>$page] + $params); }
function redirect(string $to): never { header('Location: '.$to); exit; }
function setting(string $key, string $default=''): string { $q=db()->prepare('SELECT value FROM settings WHERE key=?'); $q->execute([$key]); $v=$q->fetchColumn(); return $v===false?$default:(string)$v; }
function app_version(): string { $v=trim((string)@file_get_contents(dirname(__DIR__).'/VERSION')); return $v!==''?$v:'Unknown'; }
function otp_enabled(): bool { return setting('otp_enabled','0')==='1'; }
function send_otp(string $email,string $purpose,array $payload=[]): bool {
    $pdo=db(); $now=time(); $q=$pdo->prepare('SELECT created_at FROM otp_challenges WHERE email=? AND purpose=? AND session_hash=? ORDER BY created_at DESC LIMIT 1'); $q->execute([$email,$purpose,hash('sha256',session_id())]); $last=$q->fetchColumn();
    if($last!==false && $now-(int)$last<60) return false;
    $pdo->prepare('DELETE FROM otp_challenges WHERE expires_at<? OR (email=? AND purpose=? AND session_hash=?)')->execute([$now,$email,$purpose,hash('sha256',session_id())]);
    $code=(string)random_int(100000,999999); $id=bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO otp_challenges(id,email,purpose,code_hash,payload,session_hash,expires_at,attempts,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$id,$email,$purpose,password_hash($code,PASSWORD_DEFAULT),$payload?json_encode($payload,JSON_THROW_ON_ERROR):null,hash('sha256',session_id()),$now+600,0,$now]);
    $_SESSION['otp_challenge']=$id; $_SESSION['otp_purpose']=$purpose;
    $brand=h(setting('app_name','HelpDesk')); $purposeLabel=['login'=>'Sign-in verification','register'=>'Account email verification','guest'=>'Guest ticket email verification','profile_email'=>'Profile email change verification'][$purpose]??'Email verification'; $logo=setting('brand_logo',''); if($logo!=='' && !preg_match('#^https?://#i',$logo)) $logo=app_url($logo); $brandColor=setting('brand_color','#12377b'); if(!preg_match('/^#[0-9a-fA-F]{6}$/',$brandColor))$brandColor='#12377b'; $html='<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#1f2937"><div style="border-bottom:4px solid '.$brandColor.';padding:12px 0">'.($logo!==''?'<img src="'.h($logo).'" alt="'.$brand.'" style="max-width:180px;max-height:60px">':'<h2>'.$brand.'</h2>').'</div><h3>'.h($purposeLabel).'</h3><p>Your verification code is:</p><p style="font-size:30px;font-weight:bold;letter-spacing:6px">'.h($code).'</p><p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>'.(setting('email_footer','')!==''?'<hr><p>'.nl2br(h(setting('email_footer'))).'</p>':'').(setting('contact_email','')!==''?'<p>'.h(setting('contact_email')).'</p>':'').'</div>';
    if(!mail_send($email,'['.setting('app_name','HelpDesk').'] '.$purposeLabel,$html)) { $pdo->prepare('DELETE FROM otp_challenges WHERE id=?')->execute([$id]); unset($_SESSION['otp_challenge'],$_SESSION['otp_purpose']); return false; }
    return true;
}
function verify_otp(string $code,string $purpose): ?array {
    $id=$_SESSION['otp_challenge']??''; if(!$id || ($_SESSION['otp_purpose']??'')!==$purpose) return null;
    $q=db()->prepare('SELECT * FROM otp_challenges WHERE id=? AND purpose=? AND session_hash=?'); $q->execute([$id,$purpose,hash('sha256',session_id())]); $row=$q->fetch();
    if(!$row || (int)$row['expires_at']<time() || (int)$row['attempts']>=5) return null;
    db()->prepare('UPDATE otp_challenges SET attempts=attempts+1 WHERE id=?')->execute([$id]);
    if(!password_verify($code,$row['code_hash'])) return null;
    db()->prepare('DELETE FROM otp_challenges WHERE id=?')->execute([$id]); unset($_SESSION['otp_challenge'],$_SESSION['otp_purpose']);
    return $row;
}
function user_role_ids(array $u): array {
    $ids=[]; if(!empty($u['id'])) { $q=db()->prepare('SELECT role_id FROM user_roles WHERE user_id=?'); $q->execute([(int)$u['id']]); $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)); }
    if(!empty($u['role_id'])) $ids[]=(int)$u['role_id'];
    return array_values(array_unique(array_filter($ids)));
}
function has_permission(string $key,?array $u=null): bool {
    $u=$u??current_user(); if(!$u) return false;
    $ids=user_role_ids($u); if(!$ids) return false;
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $q=db()->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.permission_key=? AND rp.role_id IN ($marks)"); $q->execute(array_merge([$key],$ids)); if((int)$q->fetchColumn()>0) return true;
    $q=db()->prepare('SELECT COUNT(*) FROM user_groups ug JOIN group_roles gr ON gr.group_id=ug.group_id JOIN role_permissions rp ON rp.role_id=gr.role_id JOIN permissions p ON p.id=rp.permission_id WHERE ug.user_id=? AND p.permission_key=?'); $q->execute([(int)$u['id'],$key]); return (int)$q->fetchColumn()>0;
}
function users_with_permission(string $key): array { $rows=db()->query('SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.active=1 ORDER BY u.name')->fetchAll(); return array_values(array_filter($rows,fn($row)=>has_permission($key,$row))); }
function user_has_group(array $u,int $groupId): bool { if($groupId<=0)return false; $q=db()->prepare('SELECT 1 FROM user_groups WHERE user_id=? AND group_id=?');$q->execute([(int)$u['id'],$groupId]);return (bool)$q->fetchColumn(); }
function can_view_ticket(array $u,array $t): bool {
    if(has_permission('tickets.view_all',$u)) return true;
    if((int)($t['user_id']??0)===(int)$u['id'] && has_permission('tickets.view_own',$u)) return true;
    if(has_permission('tickets.view_group',$u) && ((int)($t['assigned_user_id']??0)===(int)$u['id'] || user_has_group($u,(int)($t['group_id']??0)))) return true;
    // Legacy manage grants are team-scoped, never a blanket all-ticket bypass.
    return has_permission('tickets.manage',$u) && ((int)($t['assigned_user_id']??0)===(int)$u['id'] || user_has_group($u,(int)($t['group_id']??0)));
}

function current_user(): ?array { if (empty($_SESSION['uid'])) return null; $q=db()->prepare('SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1'); $q->execute([$_SESSION['uid']]); $u=$q->fetch() ?: null; if($u){$last=strtotime((string)($u['last_activity_at']??''))?:0; if(time()-$last>=60){db()->prepare('UPDATE users SET last_activity_at=? WHERE id=?')->execute([date('c'),$u['id']]);$u['last_activity_at']=date('c');}} return $u; }
function require_login(): array { $u=current_user(); if(!$u) redirect(url('login')); return $u; }
function is_admin(?array $u=null): bool { return has_permission('admin.access',$u); }
function require_admin(): array { $u=require_login(); if(!is_admin($u)){ http_response_code(403); exit('Forbidden'); } return $u; }
function require_permission(string $key): array { $u=require_login(); if(!has_permission($key,$u)){http_response_code(403);exit('Forbidden');} return $u; }
function csrf_field(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function verify_csrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')){ http_response_code(419); exit('Your session expired. Go back and try again.'); } }
function flash(string $msg, string $type='success'): void { $_SESSION['flash']=['message'=>$msg,'type'=>$type]; }
function get_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function mail_send(string $to, string $subject, string $html, ?string $replyTo=null): bool {
    $from=setting('mail_from', 'helpdesk@'.($_SERVER['HTTP_HOST']??'localhost'));
    $name=setting('mail_from_name', setting('app_name','HelpDesk'));
    if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !filter_var($from,FILTER_VALIDATE_EMAIL)) return false;
    $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';
    $headers=[]; $headers[]='MIME-Version: 1.0'; $headers[]='Content-Type: text/html; charset=UTF-8'; $encodedName=function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($name):'=?UTF-8?B?'.base64_encode($name).'?='; $headers[]='From: '.$encodedName.' <'.$from.'>';
    if(!$replyTo) $replyTo=setting('reply_to',''); if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $headers[]='Reply-To: '.$replyTo;
    $ok=@mail($to,$encodedSubject,$html,implode("\r\n",$headers));
    try { $q=db()->prepare('INSERT INTO email_log(recipient,subject,success,created_at) VALUES(?,?,?,?)'); $q->execute([$to,$subject,$ok?1:0,date('c')]); } catch(Throwable $e) {}
    return $ok;
}
function sanitize_email_html(string $html): string {
    $html=preg_replace('#<(script|style|iframe|object|embed|form|input|button|video|audio)[^>]*>.*?</\\1>#is','',$html)??'';
    $allowed='<p><br><div><span><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><a><img><table><thead><tbody><tr><th><td><hr><pre><code>';
    $html=strip_tags($html,$allowed);
    $html=preg_replace('/\\s+on[a-z]+\\s*=\\s*("[^"]*"|\\\'[^\\\']*\\\'|[^\\s>]+)/i','',$html)??$html;
    $html=preg_replace_callback('/\\s(href|src)\\s*=\\s*(["\\\'])(.*?)\\2/i',function($m){$v=trim(html_entity_decode($m[3],ENT_QUOTES|ENT_HTML5,'UTF-8'));if(preg_match('/^(javascript|data|vbscript):/i',$v))return '';return ' '.$m[1].'='.$m[2].htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').$m[2];},$html)??$html;
    $html=preg_replace_callback('/\\sstyle\\s*=\\s*(["\\\'])(.*?)\\1/i',function($m){$style=preg_replace('/expression\\s*\\(|url\\s*\\(\\s*["\\\']?\\s*(javascript|data):/i','',$m[2])??'';return ' style="'.htmlspecialchars($style,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';},$html)??$html;
    return $html;
}
function render_email_template(string $event,array $ticket,string $message=''): array {
    $defaults=[
      'ticket_created'=>['subject'=>'[{app_name}] We received ticket #{ticket_no}','body'=>'<p>Hello {customer_name},</p><p>We received your support request.</p><p><strong>Ticket:</strong> #{ticket_no}<br><strong>Subject:</strong> {subject}<br><strong>Status:</strong> {status}</p><p>{message}</p><p>{{ticket.url}}</p>'],
      'ticket_assigned'=>['subject'=>'[{app_name}] Ticket #{ticket_no} assigned','body'=>'<p>Hello {customer_name},</p><p>A support technician has been assigned to your request.</p><p><strong>Ticket:</strong> #{ticket_no}<br><strong>Subject:</strong> {subject}<br><strong>Status:</strong> {status}</p><p>{message}</p><p>{{ticket.url}}</p>'],
      'ticket_updated'=>['subject'=>'[{app_name}] Ticket #{ticket_no} updated','body'=>'<p>Hello {customer_name},</p><p>There is an update to your support request.</p><p><strong>Ticket:</strong> #{ticket_no}<br><strong>Subject:</strong> {subject}<br><strong>Status:</strong> {status}</p><p>{message}</p><p>{{ticket.url}}</p>'],
      'ticket_resolved'=>['subject'=>'[{app_name}] Ticket #{ticket_no} resolved','body'=>'<p>Hello {customer_name},</p><p>Your support request has been marked resolved.</p><p><strong>Ticket:</strong> #{ticket_no}<br><strong>Subject:</strong> {subject}<br><strong>Status:</strong> {status}</p><p>{message}</p><p>{{ticket.url}}</p>']
    ];
    $app=setting('app_name','HelpDesk'); $ticketUrl=app_url(url('ticket',['id'=>$ticket['id']??0]));
    $values=['app_name'=>$app,'ticket_no'=>(string)($ticket['ticket_no']??''),'subject'=>(string)($ticket['subject']??''),'status'=>(string)($ticket['status_name']??''),'message'=>$message,'customer_name'=>(string)($ticket['user_name']??'Customer')];
    $subject=setting('template_'.$event.'_subject',$defaults[$event]['subject']??'[{app_name}] Support ticket update');
    $body=setting('template_'.$event.'_body',$defaults[$event]['body']??'<p>{message}</p>');
    if(trim($subject)==='') $subject=$defaults[$event]['subject']??'[{app_name}] Support ticket update';
    if(trim($body)==='') $body=$defaults[$event]['body']??'<p>{message}</p>';
    // Plain-text templates saved by older releases remain readable after upgrade.
    if(strip_tags($body)===$body) $body=nl2br(h($body));
    foreach($values as $key=>$value){$safe=$key==='message'?nl2br(h($value)):h($value);$subject=str_replace('{'.$key.'}',$value,$subject);$body=str_replace('{'.$key.'}',$safe,$body);}
    $link='<a href="'.h($ticketUrl).'" target="_blank" rel="noopener noreferrer">View your support ticket</a>';
    foreach(['{{ticket.url}}','{ticket.url}','{ticket_url}'] as $token){$body=str_replace($token,$link,$body);$subject=str_replace($token,$ticketUrl,$subject);}
    $color=setting('brand_color','#12377b');if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color))$color='#12377b';$logo=setting('brand_logo',''); if($logo!=='' && !preg_match('#^https?://#i',$logo)) $logo=app_url($logo);
    $safeBody=sanitize_email_html($body);
    $html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#1f2937;border-top:5px solid '.$color.';padding:22px">'.($logo!==''?'<img src="'.h($logo).'" alt="'.h($app).'" style="max-width:180px;max-height:60px">':'<h2>'.h($app).'</h2>').'<div style="line-height:1.6">'.$safeBody.'</div>'.(setting('email_footer','')!==''?'<hr><p style="color:#6b7280;font-size:12px">'.nl2br(h(setting('email_footer'))).'</p>':'').(setting('contact_email','')!==''?'<p style="color:#6b7280;font-size:12px">'.h(setting('contact_email')).'</p>':'').'</div>';
    return [$subject,$html];
}
function notify_event(string $event, array $ticket, string $message=''): void {
    if(setting('notify_'.$event,'1')!=='1') return;
    [$subject,$body]=render_email_template($event,$ticket,$message);$pdo=db();
    if(in_array($event,['ticket_created','ticket_assigned','ticket_updated','ticket_resolved'],true) && !empty($ticket['user_email'])) mail_send($ticket['user_email'],$subject,$body);
    if(in_array($event,['ticket_created','ticket_assigned'],true)) {
        $emails=[];
        if(!empty($ticket['assigned_user_id'])){$q=$pdo->prepare('SELECT email FROM users WHERE id=? AND active=1');$q->execute([$ticket['assigned_user_id']]);$e=$q->fetchColumn();if($e)$emails[]=$e;}
        if(!empty($ticket['group_id'])){$q=$pdo->prepare('SELECT u.email FROM users u JOIN group_members gm ON gm.user_id=u.id WHERE gm.group_id=? AND u.active=1');$q->execute([$ticket['group_id']]);$emails=array_merge($emails,$q->fetchAll(PDO::FETCH_COLUMN));}
        foreach(array_unique($emails) as $email) mail_send($email,$subject,$body);
    }
}
function app_url(string $path): string { global $config; if(!empty($config['base_url'])) return rtrim($config['base_url'],'/').'/'.ltrim($path,'/'); $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http'; return $scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/'),'/').'/'.$path; }
function audit(string $action, string $details=''): void { $u=current_user(); $q=db()->prepare('INSERT INTO audit_logs(user_id,action,details,ip,created_at) VALUES(?,?,?,?,?)'); $q->execute([$u['id']??null,$action,$details,$_SERVER['REMOTE_ADDR']??'',date('c')]); }
function ticket_by_id(int $id): ?array { $q=db()->prepare('SELECT t.*,COALESCE(t.guest_name,u.name) user_name,COALESCE(t.guest_email,u.email) user_email,COALESCE(t.guest_phone,u.phone) user_phone,COALESCE(t.guest_company,u.company) user_company,COALESCE(t.guest_department,u.department) user_department,g.name group_name,au.name assigned_name,st.name status_name,p.name priority_name FROM tickets t LEFT JOIN users u ON u.id=t.user_id LEFT JOIN groups g ON g.id=t.group_id LEFT JOIN users au ON au.id=t.assigned_user_id JOIN statuses st ON st.id=t.status_id JOIN priorities p ON p.id=t.priority_id WHERE t.id=?'); $q->execute([$id]); return $q->fetch()?:null; }

