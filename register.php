<?php
session_start();
require_once 'db_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    
    // ตรวจสอบข้อมูล
    if (empty($username) || empty($password) || empty($name) || empty($phone)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } else {
        // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว";
        } else {
            // เข้ารหัสผ่าน
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // สร้างอีเมลที่ไม่ซ้ำกัน
            $unique_email = 'user_' . time() . '_' . rand(1000, 9999) . '@nano.com';
            
            // เพิ่มผู้ใช้ใหม่
            $stmt = $conn->prepare("INSERT INTO users (username, password, name, phone, email, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->bind_param("sssss", $username, $hashed_password, $name, $phone, $unique_email);
            
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - NANO</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .auth-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.8s ease-out;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .logo i {
            font-size: 2rem;
            color: white;
        }

        .form-title {
            color: #2d3748;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-subtitle {
            color: #718096;
            font-size: 1rem;
            font-weight: 300;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: rgba(254, 178, 178, 0.9);
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }

        .alert-success {
            background: rgba(154, 230, 180, 0.9);
            color: #22543d;
            border-left: 4px solid #48bb78;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            margin-bottom: 20px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .form-footer {
            text-align: center;
        }

        .form-footer p {
            color: #718096;
            font-size: 0.9rem;
        }

        .form-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-link:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <form class="auth-form" method="POST" action="register.php">
            <div class="form-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2 class="form-title">สมัครสมาชิก</h2>
                <p class="form-subtitle">สร้างบัญชีใหม่เพื่อใช้งานระบบ</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user"></i> ชื่อผู้ใช้
                </label>
                <input type="text" class="form-input" name="username" required 
                       placeholder="กรุณาใส่ชื่อผู้ใช้" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-id-card"></i> ชื่อ-นามสกุล
                </label>
                <input type="text" class="form-input" name="name" required 
                       placeholder="กรุณาใส่ชื่อ-นามสกุล" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-phone"></i> เบอร์โทรศัพท์
                </label>
                <input type="tel" class="form-input" name="phone" required 
                       placeholder="กรุณาใส่เบอร์โทรศัพท์" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
                <input type="password" class="form-input" name="password" required 
                       placeholder="กรุณาใส่รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-lock"></i> ยืนยันรหัสผ่าน
                </label>
                <input type="password" class="form-input" name="confirm_password" required 
                       placeholder="กรุณาใส่รหัสผ่านอีกครั้ง">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i> สมัครสมาชิก
            </button>

            <div class="form-footer">
                <p>มีบัญชีอยู่แล้ว? <a href="login.php" class="form-link">เข้าสู่ระบบ</a></p>
            </div>
        </form>
    </div>
</body>
</html>