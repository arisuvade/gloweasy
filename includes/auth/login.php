<?php
session_start();
include '../db.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../../user/dashboard.php");
    exit();
}

if (isset($_SESSION['admin_id'])) {
    // Check if admin is superadmin (branch = 'Owner')
    if (isset($_SESSION['admin_branch']) && $_SESSION['admin_branch'] === 'Owner') {
        header("Location: ../../superadmin/dashboard.php");
    } else {
        header("Location: ../../admin/dashboard.php");
    }
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check database admin accounts
        $admin_stmt = $conn->prepare("SELECT id, password, branch, name FROM admins WHERE email = ?");
        $admin_stmt->bind_param("s", $email);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        if ($admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            
            if ($password === $admin['password']) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_branch'] = $admin['branch'];
                $_SESSION['admin_name'] = $admin['name'];
                $admin_stmt->close();
                
                // Redirect superadmin (Owner) to superadmin dashboard
                if ($admin['branch'] === 'Owner') {
                    header("Location: ../../superadmin/dashboard.php");
                } else {
                    header("Location: ../../admin/dashboard.php");
                }
                exit();
            }
        }
        $admin_stmt->close();

        // Check user accounts
        $user_stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM users WHERE email = ?");
        $user_stmt->bind_param("s", $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                if (!$user['is_verified']) {
                    $_SESSION['email'] = $email;
                    $user_stmt->close();
                    header("Location: verify.php");
                    exit();
                }

                // Generate OTP
                $otp = rand(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
                $otp_expiry = date('Y-m-d H:i:s', time() + 300);

                $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
                $update->bind_param("ssi", $otp_hash, $otp_expiry, $user['id']);
                
                if ($update->execute()) {
                    // Send OTP via Email
                    require '../../includes/send_email.php';
                    $subject = "Login Verification Code";
                    $message = "Your verification code is: $otp";
                    
                    if (sendEmail($email, $subject, $message)) {
                        $_SESSION['otp_verification'] = [
                            'id' => $user['id'], 
                            'email' => $email, 
                            'type' => 'login'
                        ];
                        $update->close();
                        $user_stmt->close();
                        header("Location: verify.php"); 
                        exit();
                    } else {
                        $error = "Failed to send verification email. Please try again.";
                    }
                } else {
                    $error = "Error generating verification code. Please try again.";
                }
                $update->close();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
        $user_stmt->close();
    }
}

$pageTitle = 'Login - Bali Ayurveda Spa';
$isAuthPage = true;
$customCSS = "styles.css";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="<?= $customCSS ?>" rel="stylesheet">
    <style>
        :root {
            --primary-white: #ffffff;
            --secondary-green: #2e8b57;
            --accent-green: #4caf93;
            --light-green: #e8f5e9;
            --dark-text: #2a6049;
            --medium-gray: #e0e0e0;
            --light-gray: #f5f5f5;
            --oblong-green: #2e8b57;
            --oblong-hover: #247a4a;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            min-height: 100vh;
        }

        .auth-container {
            background-color: var(--primary-white);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 2.5rem;
            max-width: 450px;
            margin: 2rem auto;
        }

        .auth-container img {
            height: 80px;
            margin-bottom: 1.5rem;
        }

        .auth-title {
            color: var(--dark-text);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: var(--accent-green);
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .form-control {
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 0 0.25rem rgba(76, 175, 147, 0.15);
        }

        .btn-oblong {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: var(--oblong-green);
            color: white;
            border: none;
            width: 100%;
        }

        .btn-oblong:hover {
            background-color: var(--oblong-hover);
            color: white;
            transform: translateY(-2px);
        }

        /* Password toggle button */
        #togglePassword {
            background-color: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-left: none;
            color: var(--dark-text);
        }

        /* Alert styling */
        .alert {
            border-radius: 6px;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: #721c24;
        }

        /* Links */
        .auth-link {
            color: var(--accent-green);
            text-decoration: none;
            transition: color 0.2s ease;
            font-size: 0.9rem;
        }

        .auth-link:hover {
            color: var(--secondary-green);
            text-decoration: underline;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--medium-gray);
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--medium-gray);
        }

        .divider::before {
            margin-right: 1rem;
        }

        .divider::after {
            margin-left: 1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 1rem;
                box-shadow: none;
                border: 1px solid var(--medium-gray);
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="d-flex align-items-center" style="min-height: calc(100vh - 120px);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="auth-container">
                        <div class="text-center mb-4">
                            <h2 class="auth-title">Login</h2>
                            <p class="auth-subtitle">Experience the healing touch of Bali Ayurveda Spa</p>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" class="mb-3">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="your@email.com" autocomplete="username">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required 
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-oblong mb-3">
                                Login <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                            <div class="text-center">
                                <a href="forgot_password.php" class="auth-link">
                                    Forgot your password?
                                </a>
                            </div>
                        </form>
                        
                        <div class="divider">or</div>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="register.php" class="auth-link">Sign up</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>