<?php
// nfc_simulate.php — Revised (Approach A)
// เพิ่มฟังก์ชันคำนวณยอดคงเหลือ + เช็กเงินก่อนซื้อ + ล็อกกันแข่งกัน แล้วค่อยบันทึกธุรกรรมและตัดสต็อก

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db_config.php';

$userId   = intval($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';

if (method_exists($conn, 'set_charset')) $conn->set_charset('utf8mb4');

// ========= Helpers =========
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function isValidUid(string $uid): bool {
    $len = strlen($uid);
    return $len >= 4 && $len <= 32 && preg_match('/^[A-Fa-f0-9]+$/', $uid);
}
function getDisplayName(mysqli $conn, int $uid, string $usernameFallback): string {
    $name = '';
    $st = $conn->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
    $st->bind_param("i", $uid); $st->execute(); $st->bind_result($name); $st->fetch(); $st->close();
    $name = trim((string)$name);
    return $name !== '' ? $name : $usernameFallback;
}
function generateTxnId(): string {
    return 'TXN' . strtoupper(base_convert(time(), 10, 36)) . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/**
 * คำนวณยอดคงเหลือแบบสดจากตาราง transactions
 * balance = SUM(topup) - SUM(buy) ของรายการที่ is_confirmed = 1
 */
function getUserBalance(mysqli $conn, int $userId): float {
    $sql = "
        SELECT COALESCE(SUM(
            CASE
                WHEN type='topup' THEN amount
                WHEN type='buy'   THEN -amount
                ELSE 0
            END
        ), 0) AS balance
        FROM transactions
        WHERE user_id = ? AND is_confirmed = 1
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $userId);
    $st->execute();
    $st->bind_result($balance);
    $st->fetch();
    $st->close();
    return (float)($balance ?? 0.0);
}

$flash = ['ok'=>[], 'err'=>[]];

// ========= POST actions (เฉพาะ user ของตัวเอง) =========
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf_or_die($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'register_card') {
        $uid = strtoupper(trim($_POST['card_uid'] ?? ''));
        $nickname = trim($_POST['nickname'] ?? '');

        if (!isValidUid($uid)) {
            $flash['err'][] = "รูปแบบ UID ไม่ถูกต้อง (0-9A-F, 4–32 ตัวอักษร)";
        } else {
            // A) uid ต้องไม่ซ้ำ
            $s = $conn->prepare("SELECT user_id FROM nfc_cards WHERE card_uid=? LIMIT 1");
            $s->bind_param("s", $uid); $s->execute(); $s->store_result();
            if ($s->num_rows > 0) { $flash['err'][] = "UID นี้ถูกใช้แล้ว"; $s->close(); }
            else {
                $s->close();
                // B) หนึ่งบัญชีมีได้ใบเดียว
                $s = $conn->prepare("SELECT COUNT(*) FROM nfc_cards WHERE user_id=?");
                $s->bind_param("i", $userId); $s->execute(); $s->bind_result($cnt); $s->fetch(); $s->close();
                if ($cnt > 0) $flash['err'][] = "บัญชีนี้ผูกบัตรแล้ว (อนุญาต 1 บัตรต่อ 1 บัญชี)";
                else {
                    $s = $conn->prepare("INSERT INTO nfc_cards (user_id, card_uid, nickname) VALUES (?, ?, ?)");
                    $s->bind_param("iss", $userId, $uid, $nickname);
                    if ($s->execute()) $flash['ok'][] = "ลงทะเบียนบัตรสำเร็จ";
                    else $flash['err'][] = "ลงทะเบียนล้มเหลว: ".h($s->error);
                    $s->close();
                }
            }
        }
    }
    elseif ($action === 'simulate_topup') {
        $uid = strtoupper(trim($_POST['card_uid'] ?? ''));
        $amount = floatval($_POST['amount'] ?? 0);
        if (!isValidUid($uid)) $flash['err'][] = "UID ไม่ถูกต้อง";
        elseif ($amount <= 0) $flash['err'][] = "จำนวนเงินต้องมากกว่า 0";
        else {
            $s = $conn->prepare("SELECT id FROM nfc_cards WHERE card_uid=? AND user_id=? AND is_active=1 LIMIT 1");
            $s->bind_param("si", $uid, $userId); $s->execute(); $s->store_result();
            if ($s->num_rows===0) { $flash['err'][] = "ไม่พบบัตรนี้ของคุณ หรือบัตรถูกปิดใช้งาน"; $s->close(); }
            else {
                $s->close();
                $txnId = generateTxnId();
                $customerName = getDisplayName($conn, $userId, $username);
                $st = $conn->prepare("INSERT INTO transactions (transaction_id, amount, status, customer_name, type, is_paid, is_confirmed, user_id)
                                      VALUES (?, ?, 'completed', ?, 'topup', 1, 1, ?)");
                $st->bind_param("sdsi", $txnId, $amount, $customerName, $userId);
                if ($st->execute()) {
                    $newBalance = getUserBalance($conn, $userId);
                    $flash['ok'][] = "เติมเงินแล้ว: $txnId +".number_format($amount,2)." บาท | คงเหลือ ".number_format($newBalance,2)." บาท";
                } else {
                    $flash['err'][] = "บันทึกธุรกรรมล้มเหลว: ".h($st->error);
                }
                $st->close();
            }
        }
    }
    elseif ($action === 'simulate_buy') {
        $uid = strtoupper(trim($_POST['card_uid'] ?? ''));
        $productId = intval($_POST['product_id'] ?? 0);
        $qty = max(1, intval($_POST['quantity'] ?? 1));
        if (!isValidUid($uid)) $flash['err'][] = "UID ไม่ถูกต้อง";
        elseif ($productId<=0 || $qty<=0) $flash['err'][] = "ข้อมูลสินค้า/จำนวนไม่ถูกต้อง";
        else {
            // ตรวจบัตรเป็นของ user
            $s = $conn->prepare("SELECT id FROM nfc_cards WHERE card_uid=? AND user_id=? AND is_active=1 LIMIT 1");
            $s->bind_param("si", $uid, $userId); $s->execute(); $s->store_result();
            if ($s->num_rows===0) { $flash['err'][]="ไม่พบบัตรนี้ของคุณ หรือบัตรถูกปิดใช้งาน"; $s->close(); }
            else {
                $s->close();
                // อ่านสินค้า (ไม่ล็อกที่นี่ เดี๋ยวล็อกในทรานแซ็กชัน)
                $pname=''; $price=0.0; $stock=0;
                $p = $conn->prepare("SELECT product_name, price, stock FROM products WHERE id=? LIMIT 1");
                $p->bind_param("i", $productId); $p->execute(); $p->bind_result($pname,$price,$stock);
                if (!$p->fetch()) { $flash['err'][]="ไม่พบสินค้า"; $p->close(); }
                else { $p->close();
                    if ($stock < $qty) $flash['err'][] = "สต็อกไม่พอ (คงเหลือ $stock)";
                    else {
                        // ===== เริ่มทรานแซ็กชันเพื่อกันแข่งกันและรักษาความสอดคล้อง =====
                        $conn->begin_transaction();
                        try {
                            // 1) ล็อกแถวสินค้า
                            $lp = $conn->prepare("SELECT price, stock FROM products WHERE id = ? FOR UPDATE");
                            $lp->bind_param("i", $productId);
                            $lp->execute();
                            $lp->bind_result($lockedPrice, $lockedStock);
                            if (!$lp->fetch()) { $lp->close(); throw new Exception('ไม่พบสินค้า (ขณะล็อก)'); }
                            $lp->close();

                            if ($lockedStock < $qty) {
                                throw new Exception('สต็อกไม่พอ (มีการสั่งซื้อพร้อมกัน)');
                            }

                            // 2) ล็อกบันทึกธุรกรรมของผู้ใช้เพื่อกันคำนวณยอดชนกัน
                            // ไม่ต้องอ่านค่าจริง แค่ให้ DB สร้าง lock
                            $lt = $conn->prepare("SELECT id FROM transactions WHERE user_id = ? FOR UPDATE");
                            $lt->bind_param("i", $userId);
                            $lt->execute();
                            $lt->close();

                            // 3) คำนวณยอดคงเหลือหลังล็อก
                            $balance = getUserBalance($conn, $userId);
                            $amount  = (float)$lockedPrice * (int)$qty;

                            if ($balance < $amount) {
                                throw new Exception('ยอดเงินไม่พอ (คงเหลือ '.number_format($balance,2).' บาท)');
                            }

                            // 4) บันทึกธุรกรรม buy
                            $txnId = generateTxnId();
                            $customerName = getDisplayName($conn, $userId, $username);
                            $st = $conn->prepare("INSERT INTO transactions (transaction_id, amount, status, customer_name, type, is_paid, is_confirmed, user_id)
                                                  VALUES (?, ?, 'completed', ?, 'buy', 1, 1, ?)");
                            $st->bind_param("sdsi", $txnId, $amount, $customerName, $userId);
                            if (!$st->execute()) throw new Exception($st->error);
                            $st->close();

                            // 5) บันทึกรายการสินค้า
                            $oi = $conn->prepare("INSERT INTO order_items (transaction_id, product_id, quantity, price_per_unit, total_price)
                                                  VALUES (?, ?, ?, ?, ?)");
                            $oi->bind_param("siidd", $txnId, $productId, $qty, $lockedPrice, $amount);
                            if (!$oi->execute()) throw new Exception($oi->error);
                            $oi->close();

                            // 6) ตัดสต็อก
                            $up = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");
                            $up->bind_param("iii", $qty, $productId, $qty);
                            if (!$up->execute() || $up->affected_rows===0) throw new Exception("ตัดสต็อกไม่สำเร็จ");
                            $up->close();

                            $conn->commit();

                            $newBalance = $balance - $amount;
                            $flash['ok'][] = "ซื้อสำเร็จ: $pname × $qty = ".number_format($amount,2)." บาท (TXN $txnId) | คงเหลือ ".number_format($newBalance,2)." บาท";
                        } catch (Exception $e) {
                            $conn->rollback();
                            $flash['err'][] = "ซื้อไม่สำเร็จ: ".h($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

// ========= ดึงข้อมูลแสดงผล =========
// ดึงบัตรของ user
$cards=[]; $st=$conn->prepare("SELECT card_uid,nickname,is_active,created_at FROM nfc_cards WHERE user_id=? ORDER BY created_at DESC");
$st->bind_param("i",$userId); $st->execute(); $rs=$st->get_result(); while($r=$rs->fetch_assoc()) $cards[]=$r; $st->close();

// ดึงสินค้า
$products=[]; if($q=$conn->query("SELECT id,product_name,price,stock FROM products ORDER BY created_at DESC")) {
  while($r=$q->fetch_assoc()) $products[]=$r;
}

// ประวัติของฉัน
$displayName = getDisplayName($conn, $userId, $username);
$txn=[]; $st=$conn->prepare("SELECT transaction_id,amount,type,status,transaction_date FROM transactions WHERE user_id=? ORDER BY transaction_date DESC LIMIT 12");
$st->bind_param("i",$userId); $st->execute(); $rs=$st->get_result(); while($r=$rs->fetch_assoc()) $txn[]=$r; $st->close();

$csrf = csrf_token();
$currentBalance = getUserBalance($conn, $userId);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>จำลอง NFC (ผู้ใช้)</title>
<link rel="stylesheet" href="style.css">
<style>
  .card{border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .grid{display:grid;gap:12px}.grid-2{grid-template-columns:1fr}.grid-3{grid-template-columns:1fr}
  @media(min-width:900px){.grid-2{grid-template-columns:1fr 1fr}.grid-3{grid-template-columns:1fr 1fr 1fr}}
  .table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px 12px;border-bottom:1px solid #eee}
  .muted{color:#666}.ok{color:#2e7d32}.err{color:#c62828}
  input,select,button{border-radius:10px}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.85rem}
  .badge.buy{background:#ffe9e9;color:#c62828}.badge.topup{background:#e9ffef;color:#2e7d32}
  .btn-primary{background:#3b82f6;color:#fff;border:0;padding:10px 16px;cursor:pointer}
  .balance{font-weight:600}
</style>
</head>
<body>
<div class="container" style="max-width:1100px;margin:24px auto">
  <h2>จำลอง NFC (ผู้ใช้): <?=h($displayName)?></h2>
  
  <!-- ปุ่มกลับหน้าแรก -->
  <div style="margin:8px 0 16px 0">
    <a href="index.php" class="btn-secondary">← กลับหน้าแรก</a>
  </div>

  <div class="muted">ยอดคงเหลือปัจจุบัน: <span class="balance"><?=number_format($currentBalance,2)?></span> บาท</div>

  <?php if($flash['ok'] || $flash['err']): ?>
    <div class="card">
      <?php foreach($flash['ok'] as $m): ?><div class="ok">• <?=h($m)?></div><?php endforeach; ?>
      <?php foreach($flash['err'] as $m): ?><div class="err">• <?=h($m)?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-2">
    <div class="card">
      <h3>① ลงทะเบียนบัตร (Card UID)</h3>
      <?php if(count($cards)===0): ?>
      <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?=$csrf?>">
        <input type="hidden" name="action" value="register_card">
        <label>Card UID (hex)</label>
        <input type="text" name="card_uid" placeholder="เช่น 04AABBCCDD" required>
        <label>ชื่อเล่นบัตร (ไม่บังคับ)</label>
        <input type="text" name="nickname" placeholder="เช่น โทรศัพท์, บัตรนักเรียน">
        <button class="btn-primary" type="submit">เพิ่มบัตร</button>
        <div class="muted">* 0-9,A-F ความยาว 4–32 ตัวอักษร (1 บัญชีต่อ 1 บัตร)</div>
      </form>
      <?php else: ?>
        <div class="muted">บัญชีนี้ผูกบัตรแล้ว (1 บัญชีต่อ 1 บัตร)</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>บัตรของฉัน</h3>
      <?php if(!$cards): ?><div class="muted">ยังไม่มีบัตร</div>
      <?php else: ?>
      <table class="table"><thead><tr><th>UID</th><th>ชื่อเล่น</th><th>สถานะ</th><th>ลงทะเบียนเมื่อ</th></tr></thead>
      <tbody><?php foreach($cards as $c): ?>
        <tr><td><code><?=h($c['card_uid'])?></code></td><td><?=h($c['nickname']??'')?></td><td><?=$c['is_active']?'Active':'Inactive'?></td><td><?=h($c['created_at'])?></td></tr>
      <?php endforeach; ?></tbody></table>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-2">
    <div class="card">
      <h3>② แตะเพื่อ “เติมเงิน” (Top-up)</h3>
      <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?=$csrf?>">
        <input type="hidden" name="action" value="simulate_topup">
        <label>เลือกบัตร</label>
        <select name="card_uid" required>
          <option value="">— เลือก —</option>
          <?php foreach($cards as $c): if($c['is_active']): ?>
            <option value="<?=h($c['card_uid'])?>"><?=h($c['nickname']?($c['nickname'].' - '.$c['card_uid']):$c['card_uid'])?></option>
          <?php endif; endforeach; ?>
        </select>
        <label>จำนวนเงิน (บาท)</label>
        <input type="number" name="amount" min="1" step="0.01" placeholder="เช่น 50.00" required>
        <button class="btn-primary" type="submit">จำลองแตะเติมเงิน</button>
      </form>
    </div>

    <div class="card">
      <h3>③ แตะเพื่อ “ซื้อสินค้า” (Buy)</h3>
      <?php if(!$products): ?><div class="muted">ยังไม่มีสินค้า</div>
      <?php else: ?>
      <form method="post" class="grid">
        <input type="hidden" name="csrf_token" value="<?=$csrf?>">
        <input type="hidden" name="action" value="simulate_buy">
        <label>เลือกบัตร</label>
        <select name="card_uid" required>
          <option value="">— เลือก —</option>
          <?php foreach($cards as $c): if($c['is_active']): ?>
            <option value="<?=h($c['card_uid'])?>"><?=h($c['nickname']?($c['nickname'].' - '.$c['card_uid']):$c['card_uid'])?></option>
          <?php endif; endforeach; ?>
        </select>
        <label>สินค้า</label>
        <select name="product_id" required>
          <?php foreach($products as $p): ?>
            <option value="<?=intval($p['id'])?>"><?=h($p['product_name'])?> — <?=number_format($p['price'],2)?> (สต็อก: <?=intval($p['stock'])?>)</option>
          <?php endforeach; ?>
        </select>
        <label>จำนวน</label>
        <input type="number" name="quantity" min="1" value="1" required>
        <button class="btn-primary" type="submit" <?=($currentBalance<=0?'disabled':'')?>>
          จำลองแตะซื้อสินค้า
        </button>
        <?php if($currentBalance<=0): ?>
          <div class="muted">ยอดคงเหลือเป็น 0 โปรดเติมเงินก่อน</div>
        <?php endif; ?>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>ประวัติธุรกรรมล่าสุดของฉัน</h3>
    <?php if(!$txn): ?><div class="muted">ยังไม่พบธุรกรรม</div>
    <?php else: ?>
    <table class="table"><thead><tr><th>TXN</th><th>ประเภท</th><th>จำนวนเงิน</th><th>สถานะ</th><th>เวลา</th></tr></thead>
    <tbody><?php foreach($txn as $t): ?>
      <tr>
        <td><?=h($t['transaction_id'])?></td>
        <td><span class="badge <?=h($t['type'])?>"><?=$t['type']==='buy'?'ซื้อสินค้า':'เติมเงิน'?></span></td>
        <td><?=number_format($t['amount'],2)?></td>
        <td><?=h($t['status'])?></td>
        <td><?=h($t['transaction_date'])?></td>
      </tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
