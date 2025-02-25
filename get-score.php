<?php
// session_start(); // Start session for user tracking
require_once 'constant.php';

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]));
}

// Handle GET request - Fetch specific user score by user_id
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(["status" => "error", "message" => "Valid user ID is required"]);
        exit;
    }

    $id = intval($_GET['id']);
    $query = "SELECT id, full_name, score, total, percentage, created_at FROM scores WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(["status" => "success", "user" => $user]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
    exit;
}





// Handle POST request - Insert or update score record securely
if ($_SERVER["REQUEST_METHOD"] === "POST") {
   
    $data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    die(json_encode(["status" => "error", "message" => "Invalid JSON input"]));
}

// Check if all required fields exist
if (!isset($data['full_name'], $data['score'], $data['total'])) {
    die(json_encode(["status" => "error", "message" => "Full name, score, and total are required"]));
}

// Assign variables
$full_name = trim($data['full_name']);
$score = intval($data['score']);
$total = intval($data['total']);

// Process data...
echo json_encode(["status" => "success", "message" => "Score received!", "data" => $data]);


    if (empty($full_name) || $score < 0 || $total <= 0 || $score > $total) {
        echo json_encode(["status" => "error", "message" => "Invalid input values"]);
        exit;
    }

    $percentage = ($score / $total) * 100;

    // Retrieve user ID from quiz_table based on full_name
    $userQuery = "SELECT id FROM quiz_table WHERE full_name = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $userResult = $stmt->get_result();

    if ($userResult->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    $user = $userResult->fetch_assoc();
    $user_id = $user['id'];
    $_SESSION["user_id"] = $user_id; // Store user_id in session

    // Insert or update user score
    $query = "INSERT INTO scores (user_id, full_name, score, total, percentage) 
              VALUES (?, ?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE score = VALUES(score), total = VALUES(total), percentage = VALUES(percentage)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isiii", $user_id, $full_name, $score, $total, $percentage);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Score saved successfully!",
            "user_id" => $user_id,
            "full_name" => $full_name,
            "score" => $score,
            "total" => $total,
            "percentage" => round($percentage, 2)
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save score: " . $conn->error]);
    }
    exit;
}

$conn->close();
?>
