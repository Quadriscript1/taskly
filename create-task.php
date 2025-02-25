<?php
include('constant.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle CORS Preflight Requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tasks");

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Handle GET request - Fetch task by ID using URL params
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(["status" => "error", "message" => "Valid Task ID is required"]);
        exit;
    }

    $task_id = intval($_GET['id']);
    $query = "SELECT id, title, task_description,all_day, start_date, end_date FROM tasks WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($task = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "task" => $task]);
    } else {
        echo json_encode(["status" => "error", "message" => "Task not found"]);
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
        echo json_encode(["status" => "error", "message" => "Invalid JSON format"]);
        exit;
    }

    // Extract and validate fields
    $title = trim($json_data['title'] ?? '');
    $task_description = trim($json_data['task_description'] ?? '');
    $all_day = trim($json_data['all_day'] ?? '');
    $start_date = trim($json_data['start_date'] ?? '');
    $end_date = trim($json_data['end_date'] ?? '');

    if (empty($title) || empty($task_description) || empty($all_day) || empty($start_date) || empty($end_date)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO tasks (title, task_description, all_day, start_date,end_date ) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssbss", $title, $task_description,$all_day, $start_date,$end_date );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Task saved successfully",
            "task_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save task"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// If request method is not GET or POST
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
?>
