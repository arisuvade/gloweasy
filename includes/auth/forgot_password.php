<?php
session_start();
include '../db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../../user/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // check user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // generate otp
            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

            // update user with otp
            $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $otp_hash, $otp_expiry, $email);
            
            if ($update->execute()) {
                // send otp via email
                require '../../includes/send_email.php';
                $subject = "Your Bali Ayurveda Spa Password Reset Code";
                $message = "Your password reset code is: $otp";
                
                $emailResponse = sendEmail($email, $subject, $message);
                
                if ($emailResponse !== false) {
                    $_SESSION['otp_verification'] = [
                        'email' => $email,
                        'type' => 'password_reset'
                    ];
                    header("Location: verify.php");
                    exit();
                } else {
                    $error = "Failed to send reset code. Please try again.";
                }
            } else {
                $error = "Error generating reset code. Please try again.";
            }
            $update->close();
        } else {
            $error = "If this email is registered, you'll receive a reset code.";
        }
        $stmt->close();
    }
}

$pageTitle = 'Forgot Password - Bali Ayurveda Spa';
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
                            <h2 class="auth-title">Reset Password</h2>
                            <p class="auth-subtitle">Enter your email to receive a reset code</p>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" class="mb-3">
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="your@email.com"
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-oblong mb-3">
                                Send Reset Code <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </form>
                        
                        <div class="divider">or</div>
                        
                        <div class="text-center">
                            <p class="mb-0">Remember your password? <a href="login.php" class="auth-link">Sign in</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>