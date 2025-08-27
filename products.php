<?php
// products.php
require_once 'db_config.php';

// ตรวจสอบการเข้าสู่ระบบ
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบสิทธิ์การแก้ไขด้วย role = admin
$can_edit = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$message = '';

// Handle Add Product - ตรวจสอบสิทธิ์ก่อน
if (isset($_POST['add_product'])) {
    if (!$can_edit) {
        $message = "คุณไม่มีสิทธิ์ในการเพิ่มสินค้า";
    } else {
        $product_name = $_POST['product_name'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];

        $stmt = $conn->prepare("INSERT INTO products (product_name, price, stock) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $product_name, $price, $stock);

        if ($stmt->execute()) {
            $message = "เพิ่มสินค้าเรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการเพิ่มสินค้า: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Update Product - ตรวจสอบสิทธิ์ก่อน
if (isset($_POST['update_product'])) {
    if (!$can_edit) {
        $message = "คุณไม่มีสิทธิ์ในการแก้ไขสินค้า";
    } else {
        $id = $_POST['edit_id'];
        $product_name = $_POST['edit_product_name'];
        $price = $_POST['edit_price'];
        $stock = $_POST['edit_stock'];

        $stmt = $conn->prepare("UPDATE products SET product_name = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("sdii", $product_name, $price, $stock, $id);

        if ($stmt->execute()) {
            $message = "อัปเดตข้อมูลสินค้าเรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการอัปเดตสินค้า: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Delete Product - ตรวจสอบสิทธิ์ก่อน
if (isset($_GET['delete_id'])) {
    if (!$can_edit) {
        $message = "คุณไม่มีสิทธิ์ในการลบสินค้า";
    } else {
        $id = $_GET['delete_id'];

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "ลบสินค้าเรียบร้อยแล้ว!";
        } else {
            $message = "เกิดข้อผิดพลาดในการลบสินค้า: " . $stmt->error;
        }
        $stmt->close();
        header("Location: products.php"); // Redirect back to products page
        exit();
    }
}

// Fetch all products for display
$sql = "SELECT * FROM products ORDER BY product_name ASC";
$result = $conn->query($sql);

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NANO - จัดการสินค้า</title>
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
              
                <a href="order_summary.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-clipboard-list me-2"></i> สรุปรายการสั่งซื้อ
                </a>
                <a href="products.php" class="list-group-item list-group-item-action custom-list-item active">
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
                        <i class="fas fa-shopping-cart me-2"></i> จัดการสินค้า
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

                <h3 class="mb-4 dashboard-title">จัดการสินค้า</h3>

                <?php if ($can_edit): ?>
                <div class="card custom-card mb-4">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">เพิ่มสินค้าใหม่</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="product_name" class="form-label">ชื่อสินค้า</label>
                                <input type="text" class="form-control custom-form-control" id="product_name" name="product_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">ราคา (บาท)</label>
                                <input type="number" step="0.01" class="form-control custom-form-control" id="price" name="price" required>
                            </div>
                            <div class="mb-3">
                                <label for="stock" class="form-label">จำนวนในสต็อก</label>
                                <input type="number" class="form-control custom-form-control" id="stock" name="stock" required>
                            </div>
                            <button type="submit" name="add_product" class="btn btn-success custom-btn-success">
                                <i class="fas fa-plus-circle me-2"></i> เพิ่มสินค้า
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>ข้อมูล:</strong> คุณสามารถดูรายการสินค้าได้ แต่ไม่สามารถเพิ่ม แก้ไข หรือลบสินค้าได้ เนื่องจากไม่มีสิทธิ์
                </div>
                <?php endif; ?>

                <div class="card custom-card">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">รายการสินค้าทั้งหมด</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover custom-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>ราคา</th>
                                        <th>สต็อก</th>
                                        <th>วันที่เพิ่ม</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($products)): ?>
                                        <?php foreach ($products as $index => $product): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td>฿<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($product['stock']); ?></td>
                                                <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                                <td>
                                                    <?php if ($can_edit): ?>
                                                        <button class="btn btn-sm custom-btn-warning edit-product-btn"
                                                                data-id="<?php echo $product['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                                data-price="<?php echo $product['price']; ?>"
                                                                data-stock="<?php echo $product['stock']; ?>">
                                                            แก้ไข
                                                        </button>
                                                        <a href="products.php?delete_id=<?php echo $product['id']; ?>" class="btn btn-sm custom-btn-danger delete-product-btn">ลบ</a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">ไม่มีสิทธิ์แก้ไข/ลบ</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">ยังไม่มีสินค้าในระบบ</td>
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
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content custom-card">
                <div class="modal-header custom-card-header-warning">
                    <h5 class="modal-title" id="editProductModalLabel">แก้ไขสินค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editProductForm" method="POST">
                        <input type="hidden" id="edit_product_id" name="edit_id">
                        <div class="mb-3">
                            <label for="edit_product_name" class="form-label">ชื่อสินค้า</label>
                            <input type="text" class="form-control custom-form-control" id="edit_product_name" name="edit_product_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">ราคา (บาท)</label>
                            <input type="number" step="0.01" class="form-control custom-form-control" id="edit_price" name="edit_price" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_stock" class="form-label">จำนวนในสต็อก</label>
                            <input type="number" class="form-control custom-form-control" id="edit_stock" name="edit_stock" required>
                        </div>
                        <button type="submit" name="update_product" class="btn custom-btn-warning">บันทึกการแก้ไข</button>
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
        // JavaScript เฉพาะสำหรับหน้า products.php
        document.addEventListener('DOMContentLoaded', function() {
            // SweetAlert for Delete Product confirmation
            document.querySelectorAll('.delete-product-btn').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const url = this.href;

                    Swal.fire({
                        title: 'คุณแน่ใจหรือไม่?',
                        text: "คุณต้องการลบสินค้าชิ้นนี้ใช่หรือไม่?",
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

            // Handle Edit Product button click to populate modal
            document.querySelectorAll('.edit-product-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const price = this.dataset.price;
                    const stock = this.dataset.stock;

                    document.getElementById('edit_product_id').value = id;
                    document.getElementById('edit_product_name').value = name;
                    document.getElementById('edit_price').value = price;
                    document.getElementById('edit_stock').value = stock;

                    const editProductModal = new bootstrap.Modal(document.getElementById('editProductModal'));
                    editProductModal.show();
                });
            });
            // Sidebar Toggle and Close on click on small screens are handled by global script.js
        });
    </script>
</body>
</html>