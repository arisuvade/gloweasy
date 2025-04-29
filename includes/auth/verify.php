<?php
session_start();
include '../db.php';

if (!isset($_SESSION['otp_verification'])) {
    header("Location: login.php");
    exit();
}

$verification = $_SESSION['otp_verification'];
$email = $verification['email'];
$type = $verification['type']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = implode('', $_POST['otp']);

    if ($type === 'password_reset') {
        // handle password reset verification
        $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($id, $otp_hash, $otp_expiry);
        $stmt->fetch();
        $stmt->close();

        if ($otp_hash && password_verify($otp, $otp_hash) && strtotime($otp_expiry) > time()) {
            // otp verified, redirect to password reset page
            $_SESSION['password_reset_user'] = $id;
            unset($_SESSION['otp_verification']);
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid or expired verification code. Please try again.";
        }
    } else {
        if ($type === 'login') {
            $id = $verification['id'];
            $stmt = $conn->prepare("SELECT otp_hash, otp_expiry FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
            $stmt->bind_param("s", $email);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($otp, $user['otp_hash']) && strtotime($user['otp_expiry']) > time()) {
            if ($type === 'registration') {
                // mark account as verified
                $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $update->bind_param("i", $user['id']);
                $update->execute();
                $update->close();
            }

            $_SESSION['user_id'] = $type === 'login' ? $verification['id'] : $user['id'];
            unset($_SESSION['otp_verification']);
            header("Location: ../../user/dashboard.php");
            exit();
        } else {
            $error = "Invalid or expired verification code. Please try again.";
        }
    }
}

$pageTitle = 'Verify Code - Bali Ayurveda Spa';
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

        .otp-input {
            width: 3rem;
            height: 3.5rem;
            font-size: 1.5rem;
            border: 1px solid var(--accent-green);
            text-align: center;
            border-radius: 6px;
        }

        .otp-input:focus {
            border-color: var(--secondary-green);
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

        .countdown {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary-green);
        }

        .countdown.expired {
            color: #dc3545;
        }

        .alert {
            border-radius: 6px;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: #721c24;
        }

        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: fadeInOut 3s ease-in-out;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0; transform: translateY(-20px); }
            10%, 90% { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 1rem;
                box-shadow: none;
                border: 1px solid var(--medium-gray);
            }
            
            .otp-input {
                width: 2.5rem;
                height: 3rem;
                font-size: 1.2rem;
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
                            <h2 class="auth-title">Verification Code</h2>
                            <p class="auth-subtitle">
                                <?php 
                                if ($type === 'registration') {
                                    echo 'Registration verification for ';
                                } elseif ($type === 'login') {
                                    echo 'Login verification for ';
                                } else {
                                    echo 'Password reset verification for ';
                                }
                                ?>
                                <strong><?= $email ?></strong>
                            </p>
                            <div id="countdown" class="countdown mb-3">05:00</div>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="verifyForm" class="mb-3">
                            <div class="mb-4 d-flex justify-content-center gap-2">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" name="otp[]" class="form-control otp-input" 
                                           maxlength="1" required autocomplete="off"
                                           <?= $i === 0 ? 'autofocus' : '' ?>>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-oblong">
                                Verify <i class="bi bi-check-circle ms-2"></i>
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Didn't receive a code? <a href="#" id="resend-otp" class="auth-link">Resend Code</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let timeLeft = 300;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                countdownElement.textContent = "Code Expired";
                countdownElement.classList.add("expired");
            }
        }, 1000);

        // OTP input auto focus
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                if (input.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Auto submit if last digit entered
                if (index === otpInputs.length - 1 && input.value.length === 1) {
                    document.getElementById('verifyForm').submit();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && index > 0 && input.value.length === 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });

        // Resend OTP functionality
        document.getElementById('resend-otp').addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=resend_otp'
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success floating-alert';
                    alert.textContent = 'New verification code sent!';
                    document.body.appendChild(alert);
                    
                    setTimeout(() => {
                        alert.remove();
                    }, 3000);
                    
                    // Reset countdown
                    timeLeft = 300;
                    countdownElement.textContent = "05:00";
                    countdownElement.classList.remove("expired");
                } else {
                    alert('Failed to resend code: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    });
    </script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>