<?php
include('constant.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle CORS Preflight Requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$conn = new mysqli("localhost", "root", "1234", "ecommerce");

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Handle GET request - Fetch task by ID using URL params
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['user_id']) || empty($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Valid User ID is required"]);
        exit;
    }

    $user_id = intval($_GET['user_id']);
    $query = "SELECT id, title, task_description, all_day, start_date, end_date FROM tasks WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($task = $result->fetch_assoc()) {
        $tasks[] = $task;
    }

    if (!empty($tasks)) {
        echo json_encode(["status" => "success", "tasks" => $tasks]);
    } else {
        echo json_encode(["status" => "error", "message" => "No tasks found for this user"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}


// Handle POST request - Save new task using JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON input
    $json_data = json_decode(file_get_contents("php://input"), true);
    if (!$json_data) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON format", "error" => json_last_error_msg()]);
        exit;
    }

    // Extract and validate fields
    $email = trim($json_data['email'] ?? '');
    $title = trim($json_data['title'] ?? '');
    $task_description = trim($json_data['task_description'] ?? '');
    $all_day = isset($json_data['all_day']) ? (int)$json_data['all_day'] : null;
    $start_date = trim($json_data['start_date'] ?? '');
    $end_date = trim($json_data['end_date'] ?? '');

    // Validate fields
    if (empty($email) || empty($title) || empty($task_description) || $all_day === null || empty($start_date) || empty($end_date)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Fetch user ID from sign_up table using email
    $user_query = "SELECT id FROM sign_up WHERE email = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $user_id = $user_row['id'];
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }
    $user_stmt->close();

    // Convert date to correct format
    $start_date = date('Y-m-d H:i:s', strtotime($start_date));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date));

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, task_description, all_day, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $title, $task_description, $all_day, $start_date, $end_date);

    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;

        // Retrieve the inserted task
        $task_query = "SELECT * FROM tasks WHERE id = ?";
        $task_stmt = $conn->prepare($task_query);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        $task = $task_result->fetch_assoc();
        $task_stmt->close();

        echo json_encode([
            "status" => "success",
            "message" => "Task saved successfully",
            "task" => $task
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save task"]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $task_id = null;

    // Check if task_id is provided as a URL parameter
    if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
        $task_id = intval($_GET['task_id']);
    } else {
        // Check if task_id is provided in JSON input
        $json_data = json_decode(file_get_contents("php://input"), true);
        if ($json_data && isset($json_data['task_id']) && is_numeric($json_data['task_id'])) {
            $task_id = intval($json_data['task_id']);
        }
    }

    // Validate task ID
    if (!$task_id || $task_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Valid Task ID is required"]);
        exit;
    }

    // Read JSON input
    if (!isset($json_data)) {
        $json_data = json_decode(file_get_contents("php://input"), true);
    }

    if (!$json_data) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON format", "error" => json_last_error_msg()]);
        exit;
    }

    // Extract and validate required fields
    $title = trim($json_data['title'] ?? '');
    $task_description = trim($json_data['task_description'] ?? '');
    $all_day = isset($json_data['all_day']) ? (int)$json_data['all_day'] : null;
    $start_date = trim($json_data['start_date'] ?? '');
    $end_date = trim($json_data['end_date'] ?? '');

    if (empty($title) || empty($task_description) || $all_day === null || empty($start_date) || empty($end_date)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Convert date to correct format
    $start_date = date('Y-m-d H:i:s', strtotime($start_date));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date));

    // Check if the task exists
    $check_query = "SELECT id FROM tasks WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $task_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result->fetch_assoc()) {
        echo json_encode(["status" => "error", "message" => "Task not found"]);
        exit;
    }
    $check_stmt->close();

    // Update the task in the database
    $update_query = "UPDATE tasks SET title = ?, task_description = ?, all_day = ?, start_date = ?, end_date = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssissi", $title, $task_description, $all_day, $start_date, $end_date, $task_id);

    if ($update_stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Task updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update task"]);
    }

    $update_stmt->close();
    $conn->close();
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $task_id = null;

    // Check if task_id is provided as a URL parameter
    if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
        $task_id = intval($_GET['task_id']);
    } else {
        // Check if task_id is provided in JSON input
        $json_data = json_decode(file_get_contents("php://input"), true);
        if ($json_data && isset($json_data['task_id']) && is_numeric($json_data['task_id'])) {
            $task_id = intval($json_data['task_id']);
        }
    }

    // Validate task ID
    if (!$task_id || $task_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Valid Task ID is required"]);
        exit;
    }

    // Check if the task exists
    $check_query = "SELECT id FROM tasks WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $task_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if (!$check_result->fetch_assoc()) {
        echo json_encode(["status" => "error", "message" => "Task not found"]);
        exit;
    }
    $check_stmt->close();

    // Delete the task
    $delete_query = "DELETE FROM tasks WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $task_id);

    if ($delete_stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Task deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete task"]);
    }

    $delete_stmt->close();
    $conn->close();
    exit;
}




// If request method is not GET or POST
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
?>
