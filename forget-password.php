<?php
require_once 'constant.php';
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendOTPEmail($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2; 
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['EMAIL_USERNAME'];
        $mail->Password = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port = 587; 

        $mail->setFrom($_ENV['EMAIL_USERNAME'], 'TechTalentAcademy');
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP code is: $otp";

        if ($mail->send()) {
            return true;
        } else {
            throw new Exception($mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage()); // Log error
        return false;
    }
}


function getRequestParam($key) {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    return $_GET[$key] ?? $jsonData[$key] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = getRequestParam('email');
    $otp = getRequestParam('otp');
    $new_password = getRequestParam('new_password');

    if ($email && !$otp && !$new_password) {
        $stmt = $conn->prepare("SELECT id FROM sign_up WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($sign_up_id);
            $stmt->fetch();
            $stmt->close();

            $otp = rand(100000, 999999);
            $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);

            $otpStmt = $conn->prepare("
                INSERT INTO forget_password (sign_up_id, otp, otp_created_at, failed_attempts) 
                SELECT ?, ?, NOW(), 0 FROM DUAL 
                WHERE NOT EXISTS (
                    SELECT 1 FROM forget_password WHERE sign_up_id = ? 
                    AND TIMESTAMPDIFF(MINUTE, otp_created_at, NOW()) < 1
                ) 
                ON DUPLICATE KEY UPDATE otp = VALUES(otp), otp_created_at = NOW(), failed_attempts = 0
            ");
            $otpStmt->bind_param("isi", $sign_up_id, $hashed_otp, $sign_up_id);

            if ($otpStmt->execute() && sendOTPEmail($email, $otp)) {
                $response = ["status" => "success", "message" => "OTP sent successfully. Check your email."];
            } else {
                $response['message'] = "Failed to send OTP email.";
            }
            $otpStmt->close();
        } else {
            $response['message'] = "Email not found.";
        }
    } elseif ($email && $otp && $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Fetch stored OTP and failed attempts
        $verifyStmt = $conn->prepare("
            SELECT pr.sign_up_id, pr.otp, pr.failed_attempts 
            FROM forget_password pr 
            JOIN sign_up u ON pr.sign_up_id = u.id 
            WHERE u.email = ? AND TIMESTAMPDIFF(MINUTE, pr.otp_created_at, NOW()) <= 10
        ");
        $verifyStmt->bind_param("s", $email);
        $verifyStmt->execute();
        $verifyStmt->store_result();

        if ($verifyStmt->num_rows > 0) {
            $verifyStmt->bind_result($sign_up_id, $stored_otp, $failed_attempts);
            $verifyStmt->fetch();
            $verifyStmt->close();

            if ($failed_attempts >= 5) {
                $response['message'] = "Too many failed attempts. Request a new OTP.";
            } elseif (password_verify($otp, $stored_otp)) {
                // Reset failed attempts
                $conn->query("UPDATE forget_password SET failed_attempts = 0 WHERE sign_up_id = $sign_up_id");

                // Update password
                $updateStmt = $conn->prepare("UPDATE sign_up SET password = ? WHERE id = ?");
                $updateStmt->bind_param("si", $hashed_password, $sign_up_id);

                if ($updateStmt->execute()) {
                    // Delete OTP after successful reset
                    $deleteStmt = $conn->prepare("DELETE FROM forget_password WHERE sign_up_id = ?");
                    $deleteStmt->bind_param("i", $sign_up_id);
                    $deleteStmt->execute();
                    $deleteStmt->close();

                    $response = ["status" => "success", "message" => "Password reset successfully."];
                } else {
                    $response['message'] = "Failed to reset password.";
                }
                $updateStmt->close();
            } else {
                // Increase failed attempts
                $conn->query("UPDATE forget_password SET failed_attempts = failed_attempts + 1 WHERE sign_up_id = $sign_up_id");
                $response['message'] = "Invalid OTP.";
            }
        } else {
            $response['message'] = "Invalid or expired OTP.";
        }
    } else {
        $response['message'] = "Missing required fields.";
    }
} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
$conn->close();

?>