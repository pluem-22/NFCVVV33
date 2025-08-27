<?php
// order_summary.php
require_once 'db_config.php';

// ตรวจสอบการเข้าสู่ระบบ
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch product sales summary
$sql_summary = "SELECT p.product_name, SUM(oi.quantity) AS total_quantity_sold, SUM(oi.total_price) AS total_revenue
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                GROUP BY p.product_name
                ORDER BY total_revenue DESC";
$result_summary = $conn->query($sql_summary);

$product_sales_summary = [];
if ($result_summary->num_rows > 0) {
    while($row = $result_summary->fetch_assoc()) {
        $product_sales_summary[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NANO - สรุปรายการสั่งซื้อ</title>
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
              
                <a href="order_summary.php" class="list-group-item list-group-item-action custom-list-item active">
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
                        <i class="fas fa-clipboard-list me-2"></i> สรุปรายการสั่งซื้อ
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
                <h3 class="mb-4 dashboard-title">สรุปรายการสั่งซื้อสินค้า</h3>

                <div class="card custom-card mb-4">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">สรุปยอดขายสินค้าแต่ละชิ้น</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover custom-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>จำนวนที่ขายได้</th>
                                        <th>ยอดรวมทั้งหมด (฿)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($product_sales_summary)): ?>
                                        <?php foreach ($product_sales_summary as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td><?php echo number_format($item['total_quantity_sold']); ?> ชิ้น</td>
                                                <td>฿<?php echo number_format($item['total_revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">ยังไม่มีข้อมูลการขายสินค้า</td>
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
    <footer class="footer mt-5 py-4 text-center">
        <div class="container">
            <p class="mb-0 text-muted">&copy; 2025 NANO Cashless Institute. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="script.js"></script>
</body>
</html>