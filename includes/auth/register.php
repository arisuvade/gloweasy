<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../../user/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($name)) {
        $error = "Please enter your name";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $otp = rand(100000, 999999);
        $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
        $otp_expiry = date('Y-m-d H:i:s', time() + 300);

        // check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This email is already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, otp_hash, otp_expiry) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password_hash, $otp_hash, $otp_expiry);
            
            if ($stmt->execute()) {
                // send otp via email
                require '../../includes/send_email.php';
                $subject = "Registration Verification Code";
                $message = "Your verification code is: $otp";
                
                $emailResponse = sendEmail($email, $subject, $message);
                
                if ($emailResponse !== false) {
                    $_SESSION['otp_verification'] = [
                        'email' => $email,
                        'type' => 'registration'
                    ];
                    header("Location: verify.php");
                    exit();
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

$pageTitle = 'Register - Bali Ayurveda Spa';
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
        .password-toggle {
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

        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background-color: var(--medium-gray);
            margin-top: 0.5rem;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
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
                            <h2 class="auth-title">Create Account</h2>
                            <p class="auth-subtitle">Join Bali Ayurveda Spa today</p>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" class="mb-3" id="registerForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="your@email.com"
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    <button class="btn password-toggle" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2">
                                    <div class="strength-meter" id="strengthMeter"></div>
                                </div>
                                <small class="text-muted d-block mt-1">Use at least 8 characters</small>
                            </div>
                            
                            <button type="submit" class="btn btn-oblong mb-3">
                                Register <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </form>
                        
                        <div class="divider">or</div>
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" class="auth-link">Sign in</a></p>
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

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('strengthMeter');
            let strength = 0;
            
            // Check length
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Check for mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
            
            // Check for numbers
            if (/\d/.test(password)) strength += 1;
            
            // Check for special chars
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            // Update strength meter
            const width = (strength / 5) * 100;
            strengthMeter.style.width = width + '%';
            
            // Update color based on strength
            if (strength <= 1) {
                strengthMeter.style.backgroundColor = '#dc3545'; // Red
            } else if (strength <= 3) {
                strengthMeter.style.backgroundColor = '#fd7e14'; // Orange
            } else {
                strengthMeter.style.backgroundColor = '#28a745'; // Green
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>