
<?php
// transaction_history.php
require_once 'db_config.php';

// ตรวจสอบการเข้าสู่ระบบ
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    header('Content-Type: text/html; charset=UTF-8');

    exit();
}

// ตรวจสอบสิทธิ์แอดมินจาก role ใน session
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$can_edit = $is_admin; // แอดมินเท่านั้นที่แก้ไข/ลบได้

$message = '';
$search_term = '';
$filter_type = '';
$filter_date = '';
$filter_is_paid = ''; // New filter
$filter_is_confirmed = ''; // New filter

// Handle Update Transaction - ตรวจสอบสิทธิ์ก่อน
if (isset($_POST['update_transaction'])) {
    if (!$can_edit) {
        $message = "คุณไม่มีสิทธิ์ในการแก้ไขข้อมูล";
    } else {
        $id = $_POST['edit_id'];
        $amount = $_POST['edit_amount'];
        $customer_name = $_POST['edit_customer_name'];
        $phone = $_POST['edit_phone'];
        $type = $_POST['edit_type'];
        $is_paid = isset($_POST['edit_is_paid']) ? 1 : 0; // Checkbox value: 1 if checked, 0 if not
        $is_confirmed = isset($_POST['edit_is_confirmed']) ? 1 : 0; // Checkbox value: 1 if checked, 0 if not

        // อัปเดตเบอร์โทรศัพท์ในตาราง users
        $stmt_update_user = $conn->prepare("UPDATE users SET phone = ? WHERE name = ?");
        if ($stmt_update_user) {
            $stmt_update_user->bind_param("ss", $phone, $customer_name);
            $stmt_update_user->execute();
            $stmt_update_user->close();
        }

        // Corrected bind_param type definition: 'd' for double, 's' for string, 'i' for integer (is_paid, is_confirmed, id)
        $stmt = $conn->prepare("UPDATE transactions SET amount = ?, customer_name = ?, type = ?, is_paid = ?, is_confirmed = ? WHERE id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        // Corrected bind_param signature: d (amount), s (customer_name), s (type), i (is_paid), i (is_confirmed), i (id)
        $stmt->bind_param("dssiii", $amount, $customer_name, $type, $is_paid, $is_confirmed, $id);

        if ($stmt->execute()) {
            $message = "อัปเดตข้อมูลเรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Delete Transaction - ตรวจสอบสิทธิ์ก่อน
if (isset($_GET['delete_id'])) {
    if (!$can_edit) {
        $message = "คุณไม่มีสิทธิ์ในการลบข้อมูล";
    } else {
        $id = $_GET['delete_id'];

        $stmt_get_transaction_id = $conn->prepare("SELECT transaction_id FROM transactions WHERE id = ?");
        if ($stmt_get_transaction_id === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt_get_transaction_id->bind_param("i", $id);
        $stmt_get_transaction_id->execute();
        $result_get_transaction_id = $stmt_get_transaction_id->get_result();
        $transaction_row = $result_get_transaction_id->fetch_assoc();
        $transaction_id_to_delete = $transaction_row['transaction_id'] ?? null;
        $stmt_get_transaction_id->close();

        if ($transaction_id_to_delete) {
            $stmt_delete_order_items = $conn->prepare("DELETE FROM order_items WHERE transaction_id = ?");
            if ($stmt_delete_order_items === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_delete_order_items->bind_param("s", $transaction_id_to_delete);
            $stmt_delete_order_items->execute();
            $stmt_delete_order_items->close();
        }

        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "ลบข้อมูลเรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
        }
        $stmt->close();
        header("Location: transaction_history.php");
        exit();
    }
}

// Fetch all transactions for display with search and filter (Code remains the same as previous version)
$sql = "SELECT t.*, u.phone,
               GROUP_CONCAT(CONCAT(p.product_name, ' (x', oi.quantity, ' @฿', oi.price_per_unit, ')') SEPARATOR '<br>') AS product_details
        FROM transactions t
        LEFT JOIN users u ON t.customer_name = u.name
        LEFT JOIN order_items oi ON t.transaction_id = oi.transaction_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE 1=1";

$params = [];
$types = '';

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = '%' . $_GET['search'] . '%';
    $sql .= " AND (t.transaction_id LIKE ? OR t.customer_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// จำกัดข้อมูลเฉพาะของผู้ใช้เมื่อไม่ใช่แอดมิน
if (!$is_admin) {
    $sql .= " AND t.customer_name = ?";
    $params[] = $_SESSION['name'];
    $types .= 's';
}

if (isset($_GET['type_filter']) && $_GET['type_filter'] !== '') {
    $filter_type = $_GET['type_filter'];
    $sql .= " AND t.type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if (isset($_GET['date_filter']) && $_GET['date_filter'] !== '') {
    $filter_date = $_GET['date_filter'];
    $sql .= " AND DATE(t.transaction_date) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if (isset($_GET['is_paid_filter']) && $_GET['is_paid_filter'] !== '') {
    $filter_is_paid = $_GET['is_paid_filter'];
    $sql .= " AND t.is_paid = ?";
    $params[] = (int)$filter_is_paid;
    $types .= 'i';
}

if (isset($_GET['is_confirmed_filter']) && $_GET['is_confirmed_filter'] !== '') {
    $filter_is_confirmed = $_GET['is_confirmed_filter'];
    $sql .= " AND t.is_confirmed = ?";
    $params[] = (int)$filter_is_confirmed;
    $types .= 'i';
}

$sql .= " GROUP BY t.id ORDER BY t.transaction_date DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
}

$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NANO - ประวัติและแก้ไขธุรกรรม</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="sidebar custom-sidebar" id="sidebar-wrapper">
            <div class="sidebar-heading text-center">
                <i class="fas fa-wallet me-2"></i> ระบบชำระไร้เงินสด
            </div>
            <div class="list-group list-group-flush custom-list-group">
                <a href="index.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-tachometer-alt me-2"></i> แดชบอร์ด
                </a>
                <a href="transaction_history.php" class="list-group-item list-group-item-action custom-list-item active">
                    <i class="fas fa-history me-2"></i> ประวัติและแก้ไขธุรกรรม
                </a>
           
                <a href="order_summary.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-clipboard-list me-2"></i> สรุปรายการสั่งซื้อ
                </a>
                <a href="products.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-shopping-cart me-2"></i> จัดการสินค้า
                </a>
                <a href="nfc_simulate.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-mobile-alt me-2"></i> จำลอง NFC
                </a>    
                 <a href="logout.php" class="list-group-item list-group-item-action custom-list-item text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                </a>
            </div>
        </div>
        <div id="page-content-wrapper" class="flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-light custom-topbar">
                <div class="container-fluid">
                    <button class="btn btn-primary custom-toggle-btn" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a class="navbar-brand ms-3 d-none d-md-block" href="#">
                        <i class="fas fa-history me-2"></i> ประวัติและแก้ไขธุรกรรม
                    </a>
                    <div class="ms-auto me-3 d-flex align-items-center">
                        <span class="badge bg-info me-2">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                        <span class="badge bg-success custom-badge-esp32">
                            <i class="fas fa-wifi me-1"></i> เชื่อมต่อ ESP32
                        </span>
                    </div>
                </div>
            </nav>
            <div class="container-fluid py-4">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show custom-alert" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <h3 class="mb-4 dashboard-title">ประวัติและแก้ไขธุรกรรม</h3>

                <div class="card custom-card mb-4">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">ค้นหาและกรองรายการธุรกรรม</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="search" class="form-label">ค้นหา (ID/ชื่อลูกค้า)</label>
                                <input type="text" class="form-control custom-form-control" id="search" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="ค้นหา Transaction ID หรือชื่อลูกค้า">
                            </div>
                            <div class="col-md-3">
                                <label for="type_filter" class="form-label">ประเภทธุรกรรม</label>
                                <select class="form-select custom-form-control" id="type_filter" name="type_filter">
                                    <option value="">ทั้งหมด</option>
                                    <option value="buy" <?php echo (($_GET['type_filter'] ?? '') == 'buy' ? 'selected' : ''); ?>>ซื้อสินค้า</option>
                                    <option value="topup" <?php echo (($_GET['type_filter'] ?? '') == 'topup' ? 'selected' : ''); ?>>เติมเงิน</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_filter" class="form-label">วันที่</label>
                                <input type="date" class="form-control custom-form-control" id="date_filter" name="date_filter" value="<?php echo htmlspecialchars($_GET['date_filter'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="is_paid_filter" class="form-label">สถานะจ่ายเงิน</label>
                                <select class="form-select custom-form-control" id="is_paid_filter" name="is_paid_filter">
                                    <option value="">ทั้งหมด</option>
                                    <option value="1" <?php echo (($_GET['is_paid_filter'] ?? '') == '1' ? 'selected' : ''); ?>>จ่ายแล้ว</option>
                                    <option value="0" <?php echo (($_GET['is_paid_filter'] ?? '') == '0' ? 'selected' : ''); ?>>ยังไม่จ่าย</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="is_confirmed_filter" class="form-label">สถานะยืนยัน</label>
                                <select class="form-select custom-form-control" id="is_confirmed_filter" name="is_confirmed_filter">
                                    <option value="">ทั้งหมด</option>
                                    <option value="1" <?php echo (($_GET['is_confirmed_filter'] ?? '') == '1' ? 'selected' : ''); ?>>ยืนยันแล้ว</option>
                                    <option value="0" <?php echo (($_GET['is_confirmed_filter'] ?? '') == '0' ? 'selected' : ''); ?>>ยังไม่ยืนยัน</option>
                                </select>
                            </div>
                            <div class="col-md-auto mt-auto">
                                <button type="submit" class="btn btn-primary custom-btn-primary w-100">
                                    <i class="fas fa-search me-2"></i> ค้นหา
                                </button>
                            </div>
                            <?php if (!empty($_GET['search']) || !empty($_GET['type_filter']) || !empty($_GET['date_filter']) || !empty($_GET['is_paid_filter']) || !empty($_GET['is_confirmed_filter'])): ?>
                            <div class="col-md-auto mt-auto">
                                <a href="transaction_history.php" class="btn btn-outline-secondary custom-btn-clear w-100">
                                    <i class="fas fa-times-circle me-2"></i> ล้างการค้นหา/กรอง
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card custom-card mb-4">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">รายการธุรกรรมทั้งหมด</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover custom-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Transaction ID</th>
                                        <th>จำนวนเงิน</th>
                                        <th>ผู้ทำรายการ</th>
                                        <th>เบอร์โทรศัพท์</th>
                                        <th>ประเภท</th>
                                        <th>รายละเอียดสินค้า</th>
                                        <th>วันที่/เวลา</th>
                                        <th>สถานะจ่ายเงิน</th>
                                        <th>สถานะยืนยัน</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $index => $transaction): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                                <td class="<?php echo ($transaction['type'] == 'topup' ? 'text-success' : 'text-danger'); ?>">
                                                    ฿<?php echo number_format($transaction['amount'], 2); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['phone'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                        if ($transaction['type'] == 'buy') {
                                                            echo '<span class="badge custom-badge-danger">ซื้อสินค้า</span>';
                                                        } elseif ($transaction['type'] == 'topup') {
                                                            echo '<span class="badge custom-badge-success">เติมเงิน</span>';
                                                        } else {
                                                            echo '<span class="badge bg-secondary">' . htmlspecialchars($transaction['type']) . '</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        if ($transaction['type'] == 'buy' && !empty($transaction['product_details'])) {
                                                            echo $transaction['product_details'];
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                                <td>
                                                    <?php if ($transaction['is_paid'] == 1): ?>
                                                        <span class="badge bg-success">จ่ายแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">ยังไม่จ่าย</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['is_confirmed'] == 1): ?>
                                                        <span class="badge bg-info text-dark">ยืนยันแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">ยังไม่ยืนยัน</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($can_edit): ?>
                                                        <button class="btn btn-sm custom-btn-warning edit-btn"
                                                                data-id="<?php echo $transaction['id']; ?>"
                                                                data-amount="<?php echo $transaction['amount']; ?>"
                                                                data-customer_name="<?php echo htmlspecialchars($transaction['customer_name']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($transaction['phone'] ?? ''); ?>"
                                                                data-type="<?php echo htmlspecialchars($transaction['type']); ?>"
                                                                data-is_paid="<?php echo $transaction['is_paid']; ?>"
                                                                data-is_confirmed="<?php echo $transaction['is_confirmed']; ?>">
                                                            แก้ไข
                                                        </button>
                                                        <a href="transaction_history.php?delete_id=<?php echo $transaction['id']; ?>" class="btn btn-sm custom-btn-danger delete-btn">ลบ</a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">ไม่มีสิทธิ์แก้ไข/ลบ</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">ไม่พบข้อมูลธุรกรรม</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        </div>
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content custom-card">
                <div class="modal-header custom-card-header-warning">
                    <h5 class="modal-title" id="editTransactionModalLabel">แก้ไขธุรกรรม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="POST" action="transaction_history.php"> <input type="hidden" id="edit_id" name="edit_id">
                        <div class="mb-3">
                            <label for="edit_amount" class="form-label">จำนวนเงิน (บาท)</label>
                            <input type="number" step="0.01" class="form-control custom-form-control" id="edit_amount" name="edit_amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_customer_name" class="form-label">ชื่อผู้ทำรายการ</label>
                            <input type="text" class="form-control custom-form-control" id="edit_customer_name" name="edit_customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control custom-form-control" id="edit_phone" name="edit_phone" placeholder="กรุณาใส่เบอร์โทรศัพท์">
                        </div>
                        <div class="mb-3">
                            <label for="edit_type" class="form-label">ประเภทธุรกรรม</label>
                            <select class="form-select custom-form-control" id="edit_type" name="edit_type" required>
                                <option value="buy">ซื้อสินค้า</option>
                                <option value="topup">เติมเงิน</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_paid" name="edit_is_paid">
                            <label class="form-check-label" for="edit_is_paid">สถานะ: จ่ายแล้ว</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_confirmed" name="edit_is_confirmed">
                            <label class="form-check-label" for="edit_is_confirmed">สถานะ: ยืนยันแล้ว</label>
                        </div>
                        <button type="submit" name="update_transaction" class="btn custom-btn-warning">บันทึกการแก้ไข</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer mt-5 py-4 text-center">
        <div class="container">
            <p class="mb-0 text-muted">&copy; 2025 NANO Cashless Institute. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="script.js"></script>
    <script>
        // JavaScript เฉพาะสำหรับหน้า transaction_history.php
        document.addEventListener('DOMContentLoaded', function() {
            // SweetAlert for Delete confirmation (unchanged)
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const url = this.href;

                    Swal.fire({
                        title: 'คุณแน่ใจหรือไม่?',
                        text: "คุณต้องการลบรายการนี้ใช่หรือไม่? รายการสินค้าที่เกี่ยวข้อง (ถ้ามี) จะถูกลบด้วย",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'ใช่, ลบเลย!',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                });
            });

            // Handle Edit button click to populate modal (Corrected)
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const amount = this.dataset.amount;
                    const customerName = this.dataset.customer_name;
                    const phone = this.dataset.phone;
                    const type = this.dataset.type;
                    const isPaid = parseInt(this.dataset.is_paid);
                    const isConfirmed = parseInt(this.dataset.is_confirmed);

                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_amount').value = amount; // Assign value
                    document.getElementById('edit_customer_name').value = customerName; // Assign value
                    document.getElementById('edit_phone').value = phone; // Assign phone value
                    document.getElementById('edit_type').value = type; // Assign selected option
                    document.getElementById('edit_is_paid').checked = (isPaid === 1); // Set checkbox state
                    document.getElementById('edit_is_confirmed').checked = (isConfirmed === 1); // Set checkbox state

                    const editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
                    editModal.show();
                });
            });

            // Sidebar Toggle and other general functions are handled by global script.js
        });
    </script>
</body>
</html>