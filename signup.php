<?php
include('constant.php');


// Set response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
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

// Handle GET request (Fetch Users)
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Check if specific parameters are provided
    $conditions = [];
    $params = [];
    $types = "";

    if (isset($_GET['id'])) {
        $conditions[] = "id = ?";
        $params[] = $_GET['id'];
        $types .= "i"; // "i" for integer
    }

    if (isset($_GET['email'])) {
        $conditions[] = "email = ?";
        $params[] = $_GET['email'];
        $types .= "s"; // "s" for string
    }

    if (isset($_GET['phone_number'])) {
        $conditions[] = "phone_number = ?";
        $params[] = $_GET['phone_number'];
        $types .= "s";
    }

    // Build the SQL query dynamically
    $sql = "SELECT id, full_name, email, phone_number, profile_image FROM sign_up";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(["status" => "success", "users" => $users]);
    } else {
        echo json_encode(["status" => "success", "message" => "No matching users found"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle PUT request (Update User Details)
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        echo json_encode(["status" => "error", "message" => "User ID is required"]);
        exit;
    }

    // Extract user ID and new details
    $id = (int) $data['id'];
    $full_name = mysqli_real_escape_string($conn, $data['full_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $data['email'] ?? '');
    $phone_number = mysqli_real_escape_string($conn, $data['phone_number'] ?? '');
    $profile_image = ''; // Placeholder for profile image path

    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $check_email = "SELECT id FROM sign_up WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Email already in use by another user"]);
            exit;
        }
        $stmt->close();
    }

    // Handle image upload (if provided)
    if (isset($_FILES['profile_image'])) {
        $upload_dir = "uploads/"; // Ensure this directory exists
        $image_name = time() . "_" . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $profile_image = $target_file;
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to upload image"]);
            exit;
        }
    }

    // Update query
    $sql = "UPDATE sign_up SET full_name = ?, email = ?, phone_number = ?, profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $full_name, $email, $phone_number, $profile_image, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed", "error" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        echo json_encode(["status" => "error", "message" => "User ID is required"]);
        exit;
    }

    // Extract user ID
    $id = (int) $data['id'];

    // Check if user exists
    $check_user = "SELECT profile_image FROM sign_up WHERE id = ?";
    $stmt = $conn->prepare($check_user);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    $user = $result->fetch_assoc();
    $profile_image = $user['profile_image'];

    // Delete the user from the database
    $delete_query = "DELETE FROM sign_up WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Delete the profile image if it exists
        if (!empty($profile_image) && file_exists($profile_image)) {
            unlink($profile_image); // Remove the image file from the server
        }
        echo json_encode(["status" => "success", "message" => "User deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete user"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// If request method is not supported
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
?>


