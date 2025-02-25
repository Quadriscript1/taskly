<?php
include('constant.php');
session_start();

// Set response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle POST request (User Registration)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    // Validate required fields
    if (!isset($data['full_name'], $data['email'], $data['phone_number'], $data['password'])) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Sanitize input
    $full_name = mysqli_real_escape_string($conn, $data['full_name']);
    $email = mysqli_real_escape_string($conn, $data['email']);
    $phone_number = mysqli_real_escape_string($conn, $data['phone_number']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT); // Secure password hashing

    // Check if email already exists
    $check_email = "SELECT id FROM sign_up WHERE email = ?";
    $stmt = $conn->prepare($check_email);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email already exists"]);
        exit;
    }
    $stmt->close();

    // Insert new user
    $sql = "INSERT INTO sign_up (full_name, email, phone_number, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $full_name, $email, $phone_number, $password);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Sign Up Successful", "user_id" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Handle GET request (Fetch All Users)
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $sql = "SELECT id, full_name, email, phone_number FROM sign_up"; // Excluding password for security
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(["status" => "success", "users" => $users]);
    } else {
        echo json_encode(["status" => "success", "message" => "No registered users found"]);
    }
    $conn->close();
    exit;
}

// If request method is not POST or GET
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
?>
