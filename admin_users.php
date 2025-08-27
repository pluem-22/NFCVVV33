<?php
// admin_users.php
require_once __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/db_config.php';
if (method_exists($conn, 'set_charset')) $conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$msg_ok=''; $msg_err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf_or_die($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_password') {
        $uid = intval($_POST['user_id'] ?? 0);
        $new = trim($_POST['new_password'] ?? '');
        if ($uid>0 && strlen($new)>=6) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $st=$conn->prepare("UPDATE users SET password=? WHERE id=?");
            $st->bind_param("si",$hash,$uid);
            if ($st->execute()) $msg_ok="ตั้งรหัสใหม่ให้ผู้ใช้ #$uid แล้ว";
            else $msg_err="ล้มเหลว: ".h($st->error);
            $st->close();
        } else $msg_err="ข้อมูลไม่ถูกต้อง (รหัส ≥ 6 ตัวอักษร)";
    }
    if ($action === 'impersonate') {
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid>0) {
            // log
            $st=$conn->prepare("INSERT INTO admin_impersonation_logs (admin_id, target_user_id) VALUES (?,?)");
            $st->bind_param("ii", $_SESSION['user_id'], $uid); $st->execute();
            $_SESSION['impersonation_log_id']  = $st->insert_id;
            $_SESSION['impersonator_admin_id'] = $_SESSION['user_id'];
            $st->close();

            // load target
            $s=$conn->prepare("SELECT id,username,role,name FROM users WHERE id=? LIMIT 1");
            $s->bind_param("i",$uid); $s->execute(); $s->bind_result($id,$uname,$role,$name);
            if ($s->fetch()) {
                $_SESSION['user_id']=$id; $_SESSION['username']=$uname; $_SESSION['role']=$role; $_SESSION['name']=$name;
            }
            $s->close();
            header('Location: index.php'); exit;
        } else $msg_err="user_id ไม่ถูกต้อง";
    }
}

$csrf = csrf_token();
$users = $conn->query("SELECT id, username, name, role, created_at FROM users ORDER BY created_at DESC");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>จัดการผู้ใช้ (แอดมิน)</title>
<link rel="stylesheet" href="style.css">
<style>
  .table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px 12px;border-bottom:1px solid #eee}
  .ok{color:#2e7d32}.err{color:#c62828}.card{border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .btn{border:0;border-radius:10px;padding:8px 12px;cursor:pointer}
  .btn-warning{background:#f59e0b;color:#222}.btn-outline{background:#fff;border:1px solid #ddd}
</style>
</head>
<body>
<div class="container" style="max-width:1100px;margin:24px auto">
  <h2>จัดการผู้ใช้ (แอดมิน)</h2>
  <?php if($msg_ok): ?><div class="card ok">• <?=h($msg_ok)?></div><?php endif; ?>
  <?php if($msg_err): ?><div class="card err">• <?=h($msg_err)?></div><?php endif; ?>

  <table class="table">
    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>สร้างเมื่อ</th><th>รีเซ็ตรหัส</th><th>สวมสิทธิ์</th></tr></thead>
    <tbody>
    <?php while($u=$users->fetch_assoc()): ?>
      <tr>
        <td><?=intval($u['id'])?></td>
        <td><?=h($u['username'])?></td>
        <td><?=h($u['name'])?></td>
        <td><?=h($u['role'])?></td>
        <td><?=h($u['created_at'])?></td>
        <td>
          <form method="post" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="csrf_token" value="<?=$csrf?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?=intval($u['id'])?>">
            <input type="password" name="new_password" placeholder="รหัสใหม่ (≥6)" required>
            <button class="btn btn-warning" type="submit">ตั้งรหัสใหม่</button>
          </form>
        </td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?=$csrf?>">
            <input type="hidden" name="action" value="impersonate">
            <input type="hidden" name="user_id" value="<?=intval($u['id'])?>">
            <button class="btn btn-outline" type="submit">สวมสิทธิ์</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
