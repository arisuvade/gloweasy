<?php
session_start();
include '../db.php';
require '../../includes/send_email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    switch ($action) {
        case 'register':
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address']);
                exit;
            }

            // Check if user exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'This email is already registered']);
                exit;
            }

            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, otp_hash, otp_expiry) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $otp_hash, $otp_expiry);

            if ($stmt->execute()) {
                // Send OTP via email
                $subject = "Your Bali Ayurveda Spa Verification Code";
                $message = "Your verification code is: $otp";
                
                if (sendEmail($email, $subject, $message) !== false) {
                    $_SESSION['otp_verification'] = [
                        'email' => $email,
                        'type' => 'registration'
                    ];
                    echo json_encode(['status' => 'success', 'redirect' => 'verify.php']);
                } else {
                    $conn->query("DELETE FROM users WHERE email = '$email'");
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send verification email']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
            }
            break;

        case 'login':
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            $stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($id, $hashed_password, $is_verified);
            $stmt->fetch();
            $stmt->close();

            if ($hashed_password && password_verify($password, $hashed_password)) {
                if (!$is_verified) {
                    $_SESSION['email'] = $email;
                    echo json_encode(['status' => 'error', 'message' => 'Account not verified', 'redirect' => 'verify.php']);
                    exit;
                }

                // Generate login OTP
                $otp = rand(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
                $otp_expiry = date('Y-m-d H:i:s', time() + 300);

                $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
                $update->bind_param("ssi", $otp_hash, $otp_expiry, $id);

                // Send OTP via email
                $subject = "Your Bali Ayurveda Spa Login Code";
                $message = "Your login verification code is: $otp";
                
                if ($update->execute() && sendEmail($email, $subject, $message) !== false) {
                    $_SESSION['otp_verification'] = [
                        'id' => $id,
                        'email' => $email,
                        'type' => 'login'
                    ];
                    echo json_encode(['status' => 'success', 'redirect' => 'verify.php']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send verification email']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }
            break;

        case 'verify_otp':
            $otp = implode('', $_POST['otp']);
            
            if (!isset($_SESSION['otp_verification'])) {
                echo json_encode(['status' => 'error', 'message' => 'Session expired', 'redirect' => 'login.php']);
                exit;
            }

            $verification = $_SESSION['otp_verification'];
            $email = $verification['email'];
            $type = $verification['type'];

            if ($type === 'registration') {
                $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
                $stmt->bind_param("s", $email);
            } else { // login
                $id = $verification['id'];
                $stmt = $conn->prepare("SELECT otp_hash, otp_expiry FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($otp, $user['otp_hash']) && strtotime($user['otp_expiry']) > time()) {
                if ($type === 'registration') {
                    $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();
                    $update->close();
                }

                $_SESSION['user_id'] = $type === 'registration' ? $user['id'] : $verification['id'];
                unset($_SESSION['otp_verification']);
                echo json_encode(['status' => 'success', 'redirect' => '../../dashboard/index.php']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid or expired verification code']);
            }
            break;

        case 'resend_otp':
            if (!isset($_SESSION['otp_verification'])) {
                echo json_encode(['status' => 'error', 'message' => 'Session expired']);
                exit;
            }

            $verification = $_SESSION['otp_verification'];
            $email = $verification['email'];
            $type = $verification['type'];
            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);

            if ($type === 'registration') {
                $stmt = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE email = ? AND is_verified = 0");
                $stmt->bind_param("sss", $otp_hash, $otp_expiry, $email);
            } else {
                $id = $verification['id'];
                $stmt = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE id = ?");
                $stmt->bind_param("ssi", $otp_hash, $otp_expiry, $id);
            }

            // Send OTP via email
            $subject = "Your Bali Ayurveda Spa Verification Code";
            $message = "Your verification code is: $otp";
            
            if ($stmt->execute() && sendEmail($email, $subject, $message) !== false) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to resend verification code']);
            }
            $stmt->close();
            break;

        case 'forgot_password':
            $email = trim($_POST['email']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address']);
                exit;
            }

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Email not registered']);
                exit;
            }

            $otp = rand(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);
            $otp_expiry = date('Y-m-d H:i:s', time() + 300);

            $update = $conn->prepare("UPDATE users SET otp_hash = ?, otp_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $otp_hash, $otp_expiry, $email);
            
            if ($update->execute()) {
                $subject = "Your Bali Ayurveda Spa Password Reset Code";
                $message = "Your password reset code is: $otp";
                
                if (sendEmail($email, $subject, $message) !== false) {
                    $_SESSION['otp_verification'] = [
                        'email' => $email,
                        'type' => 'password_reset'
                    ];
                    echo json_encode(['status' => 'success', 'redirect' => 'verify.php']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send reset code']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error generating reset code']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

$conn->close();
?>