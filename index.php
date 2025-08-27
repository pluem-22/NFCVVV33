<?php
session_start();
require_once 'db_config.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบสิทธิ์แอดมินจาก role ใน session
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// --- สรุปข้อมูล Dashboard --- (All queries are kept, even if not displayed)
$total_topup_amount = 0;
$total_buy_amount = 0;
$unpaid_transactions_count = 0;
$unconfirmed_transactions_count = 0;
$total_product_stock = 0;
$paid_transactions_count = 0;
$confirmed_transactions_count = 0;

// Get total top-up amount (This will be "เงินทั้งหมดที่ยังไม่ได้หัก")
if ($is_admin) {
    $stmt = $conn->prepare("SELECT SUM(amount) AS total_topup FROM transactions WHERE type = 'topup'");
} else {
    $stmt = $conn->prepare("SELECT SUM(amount) AS total_topup FROM transactions WHERE type = 'topup' AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_topup_amount = $row['total_topup'] ?? 0;
    }
    $stmt->close();
}

// Get total buy amount
if ($is_admin) {
    $stmt = $conn->prepare("SELECT SUM(amount) AS total_buy FROM transactions WHERE type = 'buy'");
} else {
    $stmt = $conn->prepare("SELECT SUM(amount) AS total_buy FROM transactions WHERE type = 'buy' AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_buy_amount = $row['total_buy'] ?? 0;
    }
    $stmt->close();
}

// Get unpaid transactions count
if ($is_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS unpaid_count FROM transactions WHERE is_paid = 0");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS unpaid_count FROM transactions WHERE is_paid = 0 AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $unpaid_transactions_count = $row['unpaid_count'] ?? 0;
    }
    $stmt->close();
}

// Get unconfirmed transactions count
if ($is_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS unconfirmed_count FROM transactions WHERE is_confirmed = 0");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS unconfirmed_count FROM transactions WHERE is_confirmed = 0 AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $unconfirmed_transactions_count = $row['unconfirmed_count'] ?? 0;
    }
    $stmt->close();
}

// Get NEW: Paid transactions count
if ($is_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS paid_count FROM transactions WHERE is_paid = 1");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS paid_count FROM transactions WHERE is_paid = 1 AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $paid_transactions_count = $row['paid_count'] ?? 0;
    }
    $stmt->close();
}

// Get NEW: Confirmed transactions count
if ($is_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS confirmed_count FROM transactions WHERE is_confirmed = 1");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS confirmed_count FROM transactions WHERE is_confirmed = 1 AND customer_name = ?");
}
if ($stmt) {
    if (!$is_admin) { $stmt->bind_param("s", $_SESSION['name']); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $confirmed_transactions_count = $row['confirmed_count'] ?? 0;
    }
    $stmt->close();
}

// Get total product stock (ไม่ขึ้นกับผู้ใช้)
$stmt = $conn->prepare("SELECT SUM(stock) AS total_stock FROM products");
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_product_stock = $row['total_stock'] ?? 0;
    $stmt->close();
}

// --- ดึงธุรกรรมล่าสุด 5 รายการ (เฉพาะของผู้ใช้ถ้าไม่ใช่แอดมิน) ---
$latest_transactions = [];
$sql_latest_transactions = "
    SELECT t.*, u.phone,
           GROUP_CONCAT(CONCAT(p.product_name, ' (x', oi.quantity, ' @฿', oi.price_per_unit, ')') SEPARATOR '<br>') AS product_details
    FROM transactions t
    LEFT JOIN users u ON t.customer_name = u.name
    LEFT JOIN order_items oi ON t.transaction_id = oi.transaction_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE 1=1";
if (!$is_admin) { $sql_latest_transactions .= " AND t.customer_name = ?"; }
$sql_latest_transactions .= " GROUP BY t.id ORDER BY t.transaction_date DESC LIMIT 5";

$stmt_latest = $conn->prepare($sql_latest_transactions);
if ($stmt_latest === false) {
    die("Prepare failed for latest transactions: " . $conn->error);
}
if (!$is_admin) { $stmt_latest->bind_param("s", $_SESSION['name']); }
$stmt_latest->execute();
$result_latest = $stmt_latest->get_result();

if ($result_latest->num_rows > 0) {
    while($row = $result_latest->fetch_assoc()) {
        $latest_transactions[] = $row;
    }
}
$stmt_latest->close();

// --- ดึงข้อมูลรายวันสำหรับกราฟเส้น (เฉพาะของผู้ใช้ถ้าไม่ใช่แอดมิน) ---
$days_to_fetch = 30; // ดึงข้อมูลย้อนหลัง 30 วัน
$chart_data_sql = "
    SELECT
        DATE(transaction_date) as transaction_day,
        SUM(CASE WHEN type = 'topup' THEN amount ELSE 0 END) as daily_topup,
        SUM(CASE WHEN type = 'buy' THEN amount ELSE 0 END) as daily_buy
    FROM transactions
    WHERE transaction_date >= CURDATE() - INTERVAL ? DAY";
if (!$is_admin) { $chart_data_sql .= " AND customer_name = ?"; }
$chart_data_sql .= " GROUP BY transaction_day ORDER BY transaction_day ASC";

$daily_chart_data = [];
$labels = [];
$topup_data = [];
$buy_data = [];
$net_balance_data = [];

$stmt_chart_data = $conn->prepare($chart_data_sql);
if ($stmt_chart_data === false) {
    die("Prepare failed for chart data: " . $conn->error);
}
if ($is_admin) {
    $stmt_chart_data->bind_param("i", $days_to_fetch);
} else {
    $stmt_chart_data->bind_param("is", $days_to_fetch, $_SESSION['name']);
}
$stmt_chart_data->execute();
$result_chart_data = $stmt_chart_data->get_result();

$raw_daily_data = [];
while ($row = $result_chart_data->fetch_assoc()) {
    $raw_daily_data[$row['transaction_day']] = [
        'daily_topup' => $row['daily_topup'],
        'daily_buy' => $row['daily_buy']
    ];
}
$stmt_chart_data->close();

$current_net_balance = 0;
for ($i = $days_to_fetch - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($date));

    $topup = $raw_daily_data[$date]['daily_topup'] ?? 0;
    $buy = $raw_daily_data[$date]['daily_buy'] ?? 0;

    $topup_data[] = $topup;
    $buy_data[] = $buy;

    $current_net_balance += ($topup - $buy);
    $net_balance_data[] = $current_net_balance;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NANO - แดชบอร์ด</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Custom CSS to control chart heights */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Container for the line chart, slightly taller than others */
        .chart-container-lg {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Adjust height for smaller screens if needed */
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            .chart-container-lg {
                height: 300px;
            }
        }
        /* Make canvas responsive within its container */
        canvas {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="sidebar custom-sidebar" id="sidebar-wrapper">
            <div class="sidebar-heading text-center">
                <i class="fas fa-wallet me-2"></i> ระบบชำระไร้เงินสด
            </div>
            <div class="list-group list-group-flush custom-list-group">
                <a href="index.php" class="list-group-item list-group-item-action custom-list-item active">
                    <i class="fas fa-tachometer-alt me-2"></i> แดชบอร์ด
                </a>
                <a href="products.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-shopping-cart me-2"></i> จัดการสินค้า
                </a>
                
                <a href="order_summary.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-clipboard-list me-2"></i> สรุปรายการสั่งซื้อ
                </a>
                
                <a href="nfc_simulate.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-mobile-alt me-2"></i> จำลอง NFC
                </a>                <a href="transaction_history.php" class="list-group-item list-group-item-action custom-list-item">
                    <i class="fas fa-history me-2"></i> ประวัติและแก้ไขธุรกรรม
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
                        <i class="fas fa-tachometer-alt me-2"></i> แดชบอร์ด
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
                <h3 class="mb-4 dashboard-title">ภาพรวมระบบ</h3>

                <div class="row g-4 mb-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="card custom-card h-100 shadow-sm border-start border-success border-5">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="icon-circle bg-success-subtle text-success">
                                            <i class="fas fa-money-bill-wave fa-2x"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="card-title text-success mb-1">เงินทั้งหมดที่ยังไม่หัก</h5>
                                        <p class="card-text fs-4 fw-bold">฿<?php echo number_format($total_topup_amount, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="card custom-card h-100 shadow-sm border-start border-primary border-5">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="icon-circle bg-primary-subtle text-primary">
                                            <i class="fas fa-balance-scale fa-2x"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="card-title text-primary mb-1">ยอดเงินคงเหลือสุทธิ</h5>
                                        <p class="card-text fs-4 fw-bold">฿<?php echo number_format($total_topup_amount - $total_buy_amount, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="card custom-card h-100 shadow-sm border-start border-danger border-5">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="icon-circle bg-danger-subtle text-danger">
                                            <i class="fas fa-minus-circle fa-2x"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="card-title text-danger mb-1">ยอดรวมเงินออก</h5>
                                        <p class="card-text fs-4 fw-bold">฿<?php echo number_format($total_buy_amount, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                <div class="row g-4 mb-4 justify-content-center">
                    <div class="col-lg-4 col-md-6">
                        <div class="card custom-card h-100 shadow-sm">
                            <div class="card-header custom-card-header">
                                <h5 class="mb-0">สรุปยอดเงินเข้า-ออก (ภาพรวม)</h5>
                            </div>
                            <div class="card-body d-flex justify-content-center align-items-center">
                                <div class="chart-container">
                                    <canvas id="revenuePieChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <div class="card custom-card h-100 shadow-sm">
                            <div class="card-header custom-card-header">
                                <h5 class="mb-0">ยอดเงินคงเหลือสุทธิ</h5>
                            </div>
                            <div class="card-body d-flex justify-content-center align-items-center">
                                <div class="chart-container">
                                    <canvas id="netBalanceGaugeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mt-4">
                        <div class="card custom-card h-100 shadow-sm">
                            <div class="card-header custom-card-header">
                                <h5 class="mb-0">แนวโน้มรายได้ รายจ่าย และยอดคงเหลือสุทธิ (30 วันล่าสุด)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container-lg">
                                    <canvas id="dailyRevenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card custom-card mb-4">
                    <div class="card-header custom-card-header">
                        <h4 class="mb-0">ธุรกรรมล่าสุด 5 รายการ</h4>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($latest_transactions)): ?>
                                        <?php foreach ($latest_transactions as $index => $transaction): ?>
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
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">ไม่พบข้อมูลธุรกรรมล่าสุด</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="transaction_history.php" class="btn btn-outline-primary custom-btn-primary-outline">
                                ดูธุรกรรมทั้งหมด <i class="fas fa-arrow-circle-right ms-2"></i>
                            </a>
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
    <script>
        // Data from PHP for Chart.js (Summary Cards)
        const totalTopup = <?php echo json_encode($total_topup_amount); ?>;
        const totalBuy = <?php echo json_encode($total_buy_amount); ?>;
        const unpaidCount = <?php echo json_encode($unpaid_transactions_count); ?>;
        const unconfirmedCount = <?php echo json_encode($unconfirmed_transactions_count); ?>;
        const netBalance = <?php echo json_encode($total_topup_amount - $total_buy_amount); ?>;

        // Data from PHP for Line Chart (Daily Trends)
        const chartLabels = <?php echo json_encode($labels); ?>;
        const topupData = <?php echo json_encode(array_map('floatval', $topup_data)); ?>;
        const buyData = <?php echo json_encode(array_map('floatval', $buy_data)); ?>;
        const netBalanceData = <?php echo json_encode(array_map('floatval', $net_balance_data)); ?>;

        // Pie Chart: สรุปยอดเงินเข้า-ออก (ภาพรวม)
        const revenueCtx = document.getElementById('revenuePieChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'pie',
            data: {
                labels: ['ยอดเงินเข้า', 'ยอดเงินออก'],
                datasets: [{
                    data: [totalTopup, totalBuy],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)', // Success green
                        'rgba(220, 53, 69, 0.7)'  // Danger red
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allows chart to resize freely
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#343a40' // Dark text for legends
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += '฿' + context.parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // COMMENTED OUT: Bar Chart: สถานะรายการค้าง
        /*
        const statusCtx = document.getElementById('statusBarChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['รายการยังไม่จ่าย', 'รายการยังไม่ยืนยัน'],
                datasets: [{
                    label: 'จำนวนรายการ',
                    data: [unpaidCount, unconfirmedCount],
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.7)',  // Warning yellow
                        'rgba(23, 162, 184, 0.7)' // Info cyan
                    ],
                    borderColor: [
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'จำนวน',
                            color: '#343a40'
                        },
                        ticks: {
                            callback: function(value) {
                                if (value % 1 === 0) { return value; }
                            },
                            color: '#343a40'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#343a40'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' รายการ';
                            }
                        }
                    }
                }
            }
        });
        */

        // Gauge Chart (Doughnut Chart ที่ปรับแต่ง) สำหรับ ยอดเงินคงเหลือสุทธิ
        const gaugeCtx = document.getElementById('netBalanceGaugeChart').getContext('2d');

        // Determine color based on netBalance
        let gaugeColor;
        if (netBalance > 0) {
            gaugeColor = 'rgba(40, 167, 69, 0.7)'; // Green for positive balance
        } else if (netBalance < 0) {
            gaugeColor = 'rgba(220, 53, 69, 0.7)'; // Red for negative balance
        } else {
            gaugeColor = 'rgba(108, 117, 125, 0.7)'; // Grey for zero balance
        }

        new Chart(gaugeCtx, {
            type: 'doughnut',
            data: {
                labels: ['ยอดคงเหลือสุทธิ'],
                datasets: [{
                    data: [Math.abs(netBalance), 0.001], // Use Math.abs for positive value in gauge
                    backgroundColor: [gaugeColor, 'rgba(0,0,0,0)'], // Last color is transparent to make it a half circle
                    borderColor: [gaugeColor, 'rgba(0,0,0,0)'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%', // Thickness of the doughnut
                rotation: -90, // Start from the top
                circumference: 180, // Half circle
                plugins: {
                    legend: {
                        display: false // No legend needed for gauge
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'ยอดคงเหลือสุทธิ: ฿' + netBalance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            }
                        }
                    }
                }
            },
            plugins: [{
                // Plugin to display text in the middle of the doughnut
                id: 'centerText',
                beforeDraw: function(chart) {
                    const {ctx, width, height} = chart;
                    ctx.restore();
                    const fontSize = (height / 114).toFixed(2);
                    ctx.font = fontSize + "em Sarabun";
                    ctx.textBaseline = "middle";

                    const text = "฿" + netBalance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    const textX = Math.round((width - ctx.measureText(text).width) / 2);
                    const textY = height / 1.2; // Adjust vertical position for half circle

                    ctx.fillStyle = '#343a40'; // Text color
                    ctx.fillText(text, textX, textY);
                    ctx.save();
                }
            }]
        });


        // Line Chart: แนวโน้มรายได้ รายจ่าย และยอดคงเหลือสุทธิ
        const dailyRevenueCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        new Chart(dailyRevenueCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'ยอดเงินเข้า (รายได้)',
                        data: topupData,
                        borderColor: 'rgba(40, 167, 69, 1)', // Success green
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        fill: false,
                        tension: 0.2 // Smooth curves
                    },
                    {
                        label: 'ยอดเงินออก (รายจ่าย)',
                        data: buyData,
                        borderColor: 'rgba(220, 53, 69, 1)', // Danger red
                        backgroundColor: 'rgba(220, 53, 69, 0.2)',
                        fill: false,
                        tension: 0.2
                    },
                    {
                        label: 'ยอดคงเหลือสุทธิ',
                        data: netBalanceData,
                        borderColor: 'rgba(0, 123, 255, 1)', // Primary blue
                        backgroundColor: 'rgba(0, 123, 255, 0.2)',
                        fill: false,
                        tension: 0.2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'วันที่',
                            color: '#343a40'
                        },
                        ticks: {
                            color: '#343a40'
                        }
                    },
                    y: {
                        beginAtZero: true, // Start y-axis from 0
                        title: {
                            display: true,
                            text: 'จำนวนเงิน (บาท)',
                            color: '#343a40'
                        },
                        ticks: {
                            callback: function(value) {
                                return '฿' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            },
                            color: '#343a40'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#343a40'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '฿' + context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>