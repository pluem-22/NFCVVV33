<?php
session_start();
require_once 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // ตรวจสอบ Admin_pluem โดยตรง
    if ($username === 'Admin_pluem' && $password === '48261412') {
        $_SESSION['user_id'] = 999; // ID พิเศษสำหรับ Admin_pluem
        $_SESSION['username'] = 'Admin_pluem';
        $_SESSION['name'] = 'Admin Pluem';
        $_SESSION['role'] = 'admin';
        
        header("Location: index.php");
        exit();
    }
    
    // ตรวจสอบผู้ใช้อื่นๆ จากฐานข้อมูล
    $stmt = $conn->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect ทุกบทบาทไปหน้าแดชบอร์ด (หน้าเดียวกัน แต่ข้อมูลถูกกรองตามสิทธิ์)
            header("Location: index.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Modern Login</title>
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

        /* Sweater Pattern Background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(255,255,255,0.1) 2px, transparent 2px),
                radial-gradient(circle at 75px 75px, rgba(255,255,255,0.1) 2px, transparent 2px);
            background-size: 50px 50px;
            animation: sweaterPattern 20s linear infinite;
            z-index: 1;
        }

        @keyframes sweaterPattern {
            0% { background-position: 0 0, 25px 25px; }
            100% { background-position: 50px 50px, 75px 75px; }
        }

        /* Floating Particles */
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
            z-index: 1;
        }

        .particle:nth-child(1) { width: 10px; height: 10px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 15px; height: 15px; left: 20%; animation-delay: 1s; }
        .particle:nth-child(3) { width: 8px; height: 8px; left: 80%; animation-delay: 2s; }
        .particle:nth-child(4) { width: 12px; height: 12px; left: 70%; animation-delay: 3s; }
        .particle:nth-child(5) { width: 6px; height: 6px; left: 90%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
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
            position: relative;
            overflow: hidden;
        }

        .auth-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shine 3s ease-in-out infinite;
        }

        @keyframes shine {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
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

        .form-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
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
            animation: pulse 2s ease-in-out infinite;
        }

        .logo i {
            font-size: 2rem;
            color: white;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
            animation: shake 0.5s ease-in-out;
        }

        .alert-danger {
            background: rgba(254, 178, 178, 0.9);
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-container {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 50px 16px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1.2rem;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: #a0aec0;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #667eea;
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
            position: relative;
            overflow: hidden;
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

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
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

        /* Loading Animation */
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .auth-form {
                padding: 30px 25px;
                border-radius: 15px;
            }
            
            .form-title {
                font-size: 1.8rem;
            }
        }

        /* Success Animation */
        .success-checkmark {
            display: none;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #48bb78;
            margin: 20px auto;
            position: relative;
        }

        .success-checkmark::after {
            content: '✓';
            color: white;
            font-size: 2rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <div class="container">
        <form id="loginForm" class="auth-form" method="POST" action="login.php">
            <div class="form-header">
                <div class="logo">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2 class="form-title">เข้าสู่ระบบ</h2>
                <p class="form-subtitle">ยินดีต้อนรับกลับมา</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="success-checkmark" id="successCheck"></div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user"></i> ชื่อผู้ใช้
                </label>
                <div class="input-container">
                    <input type="text" class="form-input" id="username" name="username" required 
                           placeholder="กรุณาใส่ชื่อผู้ใช้">
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
                <div class="input-container">
                    <input type="password" class="form-input" id="password" name="password" required 
                           placeholder="กรุณาใส่รหัสผ่าน">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="loginBtn">
                <span class="btn-text">เข้าสู่ระบบ</span>
                <div class="loading" id="loginLoading">
                    <div class="spinner"></div>
                </div>
            </button>

            <div class="form-footer">
                <p>ยังไม่มีบัญชี? <a href="register.php" class="form-link">สมัครสมาชิก</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Custom SweetAlert2 Theme
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Password Toggle Function
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.getAttribute('type') === 'password') {
                input.setAttribute('type', 'text');
                icon.classList.remove('far', 'fa-eye');
                icon.classList.add('far', 'fa-eye-slash');
                
                // Animation effect
                button.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    button.style.transform = 'scale(1)';
                }, 150);
            } else {
                input.setAttribute('type', 'password');
                icon.classList.remove('far', 'fa-eye-slash');
                icon.classList.add('far', 'fa-eye');
                
                // Animation effect
                button.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    button.style.transform = 'scale(1)';
                }, 150);
            }
        }

        // Form Validation and Animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginBtn = document.getElementById('loginBtn');
            const btnText = loginBtn.querySelector('.btn-text');
            const loading = document.getElementById('loginLoading');
            
            // Validation
            if (!username) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาใส่ชื่อผู้ใช้',
                    text: 'ชื่อผู้ใช้เป็นข้อมูลที่จำเป็น',
                    confirmButtonColor: '#667eea',
                    background: 'linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%)',
                    customClass: {
                        popup: 'animated bounceIn'
                    }
                });
                document.getElementById('username').focus();
                return;
            }
            
            if (!password) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาใส่รหัสผ่าน',
                    text: 'รหัสผ่านเป็นข้อมูลที่จำเป็น',
                    confirmButtonColor: '#667eea',
                    background: 'linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%)',
                    customClass: {
                        popup: 'animated bounceIn'
                    }
                });
                document.getElementById('password').focus();
                return;
            }
            
            // Loading Animation
            btnText.style.display = 'none';
            loading.style.display = 'block';
            loginBtn.disabled = true;
            loginBtn.style.background = 'linear-gradient(135deg, #a0aec0, #718096)';
            
            // Simulate login process (remove this in production)
            setTimeout(() => {
                // Submit the actual form
                this.submit();
            }, 1000);
        });

        // Input Focus Animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
                this.parentElement.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.15)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
                this.parentElement.style.boxShadow = 'none';
            });
            
            // Real-time validation
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#48bb78';
                    this.style.background = 'rgba(72, 187, 120, 0.05)';
                } else {
                    this.style.borderColor = '#e2e8f0';
                    this.style.background = 'rgba(255, 255, 255, 0.9)';
                }
            });
        });

        // Check for PHP errors and show SweetAlert
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'เข้าสู่ระบบไม่สำเร็จ',
            text: '<?php echo $error; ?>',
            confirmButtonColor: '#667eea',
            background: 'linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%)',
            customClass: {
                popup: 'animated shake'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
        <?php endif; ?>

        // Welcome Animation on Page Load
        window.addEventListener('load', function() {
            // Show registration success if redirected from register
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('registered') === '1') {
                Swal.fire({
                    icon: 'success',
                    title: 'สมัครสมาชิกสำเร็จ!',
                    text: 'กรุณาเข้าสู่ระบบเพื่อใช้งาน',
                    confirmButtonColor: '#667eea',
                    background: 'linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%)',
                    showClass: { popup: 'animate__animated animate__fadeInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' }
                }).then(() => {
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, 'login.php');
                    }
                });
            }
            // Show welcome toast
            Toast.fire({
                icon: 'info',
                title: 'ยินดีต้อนรับสู่ระบบ',
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: 'white'
            });
            
            // Add entrance animation to form elements
            const elements = document.querySelectorAll('.form-group, .btn, .form-footer');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100 + 500);
            });
        });

        // Add floating animation to logo
        const logo = document.querySelector('.logo');
        let floatDirection = 1;
        
        setInterval(() => {
            const currentTransform = logo.style.transform;
            const currentY = currentTransform.match(/translateY\(([^)]+)\)/) || ['', '0px'];
            let yValue = parseFloat(currentY[1]) || 0;
            
            yValue += floatDirection * 0.5;
            
            if (yValue > 5) floatDirection = -1;
            if (yValue < -5) floatDirection = 1;
            
            logo.style.transform = `translateY(${yValue}px)`;
        }, 50);

        // Add typing effect to subtitle
        function typeWriter(element, text, speed = 100) {
            element.innerHTML = '';
            let i = 0;
            
            function typing() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(typing, speed);
                }
            }
            
            typing();
        }

        // Start typing effect after page load
        setTimeout(() => {
            const subtitle = document.querySelector('.form-subtitle');
            const originalText = subtitle.textContent;
            typeWriter(subtitle, originalText, 80);
        }, 1000);

        // Add particle interaction
        document.querySelectorAll('.particle').forEach(particle => {
            particle.addEventListener('click', function() {
                this.style.transform = 'scale(2)';
                this.style.background = 'rgba(102, 126, 234, 0.5)';
                
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                    this.style.background = 'rgba(255, 255, 255, 0.1)';
                }, 300);
            });
        });

        // Add success animation (for future use)
        function showSuccess() {
            const successCheck = document.getElementById('successCheck');
            successCheck.style.display = 'block';
            successCheck.style.animation = 'bounceIn 0.6s ease';
            
            Swal.fire({
                icon: 'success',
                title: 'เข้าสู่ระบบสำเร็จ!',
                text: 'กำลังพาคุณเข้าสู่ระบบ...',
                timer: 2000,
                showConfirmButton: false,
                background: 'linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%)',
                customClass: {
                    popup: 'animated bounceIn'
                }
            });
        }

        // Mouse movement parallax effect
        document.addEventListener('mousemove', function(e) {
            const particles = document.querySelectorAll('.particle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            particles.forEach((particle, index) => {
                const speed = (index + 1) * 0.5;
                const xPos = (x - 0.5) * speed * 20;
                const yPos = (y - 0.5) * speed * 20;
                
                particle.style.transform = `translate(${xPos}px, ${yPos}px)`;
            });
        });

        // Add CSS animations keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bounceIn {
                0% {
                    opacity: 0;
                    transform: scale3d(0.3, 0.3, 0.3);
                }
                20% {
                    transform: scale3d(1.1, 1.1, 1.1);
                }
                40% {
                    transform: scale3d(0.9, 0.9, 0.9);
                }
                60% {
                    opacity: 1;
                    transform: scale3d(1.03, 1.03, 1.03);
                }
                80% {
                    transform: scale3d(0.97, 0.97, 0.97);
                }
                100% {
                    opacity: 1;
                    transform: scale3d(1, 1, 1);
                }
            }
            
            .animated {
                animation-duration: 1s;
                animation-fill-mode: both;
            }
            
            .shake {
                animation-name: shake;
                animation-duration: 0.82s;
            }
            
            .bounceIn {
                animation-name: bounceIn;
            }
        `;
        document.head.appendChild(style);