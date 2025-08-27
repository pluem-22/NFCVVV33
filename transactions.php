<?php
// transactions.php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db_config.php';
if (method_exists($conn, 'set_charset')) $conn->set_charset('utf8mb4');

$me_id   = intval($_SESSION['user_id']);
$me_role = $_SESSION['role'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ─────────────────────────────────────────────────────────────
// Actions: add / update / delete → admin only
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add_transaction','update_transaction'], true)) {
        require_admin();
        require_csrf_or_die($_POST['csrf_token'] ?? '');

        if ($action === 'add_transaction') {
            $user_id = intval($_POST['user_id'] ?? 0);
            $amount  = floatval($_POST['amount'] ?? 0);
            $type    = $_POST['type'] === 'topup' ? 'topup' : 'buy';

            if ($user_id <= 0 || $amount <= 0) {
                $msg_err = "ข้อมูลไม่ครบ";
            } else {
                // ดึงชื่อผู้ใช้
                $name=''; $s=$conn->prepare("SELECT name,username FROM users WHERE id=? LIMIT 1");
                $s->bind_param("i",$user_id); $s->execute(); $s->bind_result($name,$uname); $s->fetch(); $s->close();
                $customer_name = trim($name) !== '' ? $name : $uname;

                // สร้าง TXN
                $txn = 'TXN'.strtoupper(base_convert(time(),10,36)).strtoupper(substr(bin2hex(random_bytes(4)),0,6));
                $st=$conn->prepare("INSERT INTO transactions (transaction_id, amount, status, customer_name, type, is_paid, is_confirmed, user_id)
                                    VALUES (?, ?, 'completed', ?, ?, 1, 1, ?)");
                $st->bind_param("sdssi",$txn,$amount,$customer_name,$type,$user_id);
                if ($st->execute()) $msg_ok = "เพิ่มธุรกรรมสำเร็จ ($txn)";
                else $msg_err = "เพิ่มธุรกรรมล้มเหลว: ".h($st->error);
                $st->close();
            }
        }

        if ($action === 'update_transaction') {
            $id     = intval($_POST['id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $type   = $_POST['type'] === 'topup' ? 'topup' : 'buy';
            if ($id<=0 || $amount<=0) { $msg_err="ข้อมูลไม่ครบ"; }
            else {
                $st=$conn->prepare("UPDATE transactions SET amount=?, type=? WHERE id=?");
                $st->bind_param("dsi",$amount,$type,$id);
                if ($st->execute()) $msg_ok="อัปเดตธุรกรรมแล้ว (#$id)";
                else $msg_err="อัปเดตล้มเหลว: ".h($st->error);
                $st->close();
            }
        }
    }
}

if (isset($_GET['delete_id'])) {
    require_admin();
    $id = intval($_GET['delete_id']);
    if ($id>0) {
        $st=$conn->prepare("DELETE FROM transactions WHERE id=?");
        $st->bind_param("i",$id);
        if ($st->execute()) $msg_ok="ลบธุรกรรมแล้ว (#$id)";
        else $msg_err="ลบล้มเหลว: ".h($st->error);
        $st->close();
        header('Location: transactions.php'); // PRG
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// Query list
// ─────────────────────────────────────────────────────────────
$where = ''; $bind = null; $bindTypes='';

if (is_admin()) {
    // admin: เห็นทั้งหมด หรือกรองตาม user_id ที่เลือก
    $filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if ($filter_user_id > 0) {
        $where = "WHERE t.user_id = ?";
        $bind = [$filter_user_id];
        $bindTypes = "i";
    }
} else {
    // user: เห็นเฉพาะของตัวเอง (รายการเก่าที่ user_id IS NULL จะไม่แสดง)
    $where = "WHERE t.user_id = ?";
    $bind = [$me_id];
    $bindTypes = "i";
}

$sql = "SELECT t.id, t.transaction_id, t.amount, t.customer_name, t.type, t.status, t.transaction_date,
               t.user_id, u.phone
        FROM transactions t
        LEFT JOIN users u ON u.id = t.user_id
        $where
        ORDER BY t.transaction_date DESC";

$rows = [];
if ($where === '') {
    $q = $conn->query($sql);
    while ($r = $q->fetch_assoc()) $rows[] = $r;
} else {
    $st = $conn->prepare($sql);
    $st->bind_param($bindTypes, ...$bind);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $rows[] = $r;
    $st->close();
}

// users list สำหรับฟอร์ม admin
$all_users = [];
if (is_admin()) {
    if ($q = $conn->query("SELECT id, name, username FROM users ORDER BY name ASC")) {
        while ($u = $q->fetch_assoc()) $all_users[] = $u;
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>รายการธุรกรรม</title>
<link rel="stylesheet" href="style.css">
<style>
  .card{border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px 12px;border-bottom:1px solid #eee}
  .muted{color:#666}.ok{color:#2e7d32}.err{color:#c62828}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.85rem}
  .badge.buy{background:#ffe9e9;color:#c62828}.badge.topup{background:#e9ffef;color:#2e7d32}
  .btn{border:0;border-radius:10px;padding:8px 12px;cursor:pointer}
  .btn-primary{background:#3b82f6;color:#fff}.btn-warning{background:#f59e0b;color:#222}.btn-danger{background:#ef4444;color:#fff}.btn-outline{background:#fff;border:1px solid #ddd}
  form.inline{display:inline}
  .grid{display:grid;gap:12px}
  @media(min-width:900px){.grid-3{grid-template-columns:240px 160px 120px}}
</style>
</head>
<body>
<div class="container" style="max-width:1100px;margin:24px auto">
  <h2>รายการธุรกรรม</h2>

  <?php if(!empty($msg_ok)): ?><div class="card ok">• <?=h($msg_ok)?></div><?php endif; ?>
  <?php if(!empty($msg_err)): ?><div class="card err">• <?=h($msg_err)?></div><?php endif; ?>

  <?php if (is_admin()): ?>
    <div class="card">
      <h3>ฟิลเตอร์ (แอดมิน)</h3>
      <form class="grid grid-3" method="get">
        <div>
          <label>ผู้ใช้</label>
          <select name="user_id">
            <option value="">— ทั้งหมด —</option>
            <?php foreach($all_users as $u): $sel = (isset($_GET['user_id']) && intval($_GET['user_id'])===intval($u['id']))?'selected':''; ?>
              <option value="<?=intval($u['id'])?>" <?=$sel?>><?=h($u['name']?:$u['username'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="align-self:end">
          <button class="btn btn-primary" type="submit">กรอง</button>
        </div>
        <div style="align-self:end">
          <a class="btn btn-outline" href="admin_users.php">จัดการผู้ใช้</a>
        </div>
      </form>
    </div>

    <div class="card">
      <h3>เพิ่มธุรกรรม (แอดมิน)</h3>
      <form class="grid" method="post">
        <input type="hidden" name="csrf_token" value="<?=$csrf?>">
        <input type="hidden" name="action" value="add_transaction">
        <label>ผู้ใช้</label>
        <select name="user_id" required>
          <option value="">— เลือกผู้ใช้ —</option>
          <?php foreach($all_users as $u): ?>
            <option value="<?=intval($u['id'])?>"><?=h($u['name']?:$u['username'])?></option>
          <?php endforeach; ?>
        </select>
        <label>จำนวนเงิน</label>
        <input type="number" name="amount" min="0.01" step="0.01" required>
        <label>ประเภท</label>
        <select name="type">
          <option value="buy">ซื้อสินค้า</option>
          <option value="topup">เติมเงิน</option>
        </select>
        <div><button class="btn btn-primary" type="submit">เพิ่ม</button></div>
      </form>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3><?= is_admin() ? 'ธุรกรรมทั้งหมด' : 'ธุรกรรมของฉัน' ?></h3>
    <?php if(!$rows): ?>
      <div class="muted">ยังไม่มีข้อมูลการทำธุรกรรม</div>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>#</th><th>Transaction ID</th><th>จำนวนเงิน</th><th>ผู้ทำรายการ</th><th>เบอร์โทร</th><th>ประเภท</th><th>วันที่/เวลา</th><th>สถานะ</th>
          <?php if (is_admin()): ?><th>จัดการ</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $i=>$r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= h($r['transaction_id']) ?></td>
            <td class="<?= $r['type']==='topup' ? 'ok' : 'err' ?>"><?= number_format($r['amount'],2) ?></td>
            <td><?= h($r['customer_name']) ?></td>
            <td><?= h($r['phone'] ?? '') ?></td>
            <td><span class="badge <?=h($r['type'])?>"><?= $r['type']==='buy'?'ซื้อสินค้า':'เติมเงิน' ?></span></td>
            <td><?= h($r['transaction_date']) ?></td>
            <td><?= h($r['status']) ?></td>
            <?php if (is_admin()): ?>
              <td>
                <!-- Edit -->
                <details>
                  <summary class="btn btn-outline">แก้ไข</summary>
                  <form method="post" style="margin-top:8px">
                    <input type="hidden" name="csrf_token" value="<?=$csrf?>">
                    <input type="hidden" name="action" value="update_transaction">
                    <input type="hidden" name="id" value="<?=intval($r['id'])?>">
                    <label>จำนวนเงิน</label>
                    <input type="number" name="amount" min="0.01" step="0.01" value="<?=h($r['amount'])?>" required>
                    <label>ประเภท</label>
                    <select name="type">
                      <option value="buy"   <?= $r['type']==='buy'?'selected':''?>>ซื้อสินค้า</option>
                      <option value="topup" <?= $r['type']==='topup'?'selected':''?>>เติมเงิน</option>
                    </select>
                    <div style="margin-top:8px">
                      <button class="btn btn-warning" type="submit">บันทึก</button>
                    </div>
                  </form>
                </details>
                <!-- Delete -->
                <form class="inline" method="get" onsubmit="return confirm('ยืนยันลบธุรกรรมนี้?');">
                  <input type="hidden" name="delete_id" value="<?=intval($r['id'])?>">
                  <button class="btn btn-danger" type="submit">ลบ</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php
// แถบแจ้งเตือนเมื่อสวมสิทธิ์
if (isset($_SESSION['impersonator_admin_id'])): ?>
  <div style="position:fixed;left:16px;bottom:16px;background:#111;color:#fff;padding:10px 14px;border-radius:12px">
    กำลังสวมสิทธิ์เป็นผู้ใช้อื่น
    <a href="stop_impersonate.php" style="color:#fff;text-decoration:underline;margin-left:8px">เลิกสวมสิทธิ์</a>
  </div>
<?php endif; ?>
</body>
</html>
