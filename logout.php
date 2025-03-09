<?php
session_start();

// Set response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Destroy the session
session_unset();
session_destroy();

// Clear authentication cookies if used
if (isset($_COOKIE['auth_token'])) {
    setcookie("auth_token", "", time() - 3600, "/"); // Expire the cookie
}

// Send response
echo json_encode(["status" => "success", "message" => "Logout successful"]);
exit;
?>

