<?php
include('constant.php');
session_start();
header("Content-Type: application/json");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

// Check if all required fields are provided
if (!isset($data['full_name'], $data['email'], $data['phone_number'], $data['password'])) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Sanitize input
$full_name = mysqli_real_escape_string($conn, $data['full_name']);
$email = mysqli_real_escape_string($conn, $data['email']);
$phone_number = mysqli_real_escape_string($conn, $data['phone_number']);
$password = password_hash($data['password'], PASSWORD_DEFAULT);

//$password = md5($data['password'], PASSWORD_DEFAULT); // Secure password hashing

// Check if email already exists
$check_email = "SELECT id FROM sign_up WHERE email = '$email'";
$result = mysqli_query($conn, $check_email);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit;
}

// SQL Query
$sql = "INSERT INTO sign_up (full_name, email, phone_number, password) 
        VALUES ('$full_name', '$email', '$phone_number', '$password')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["status" => "success", "message" => "Sign Up Successful"]);
} else {
    echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => mysqli_error($conn)]);
}

exit;
?>
