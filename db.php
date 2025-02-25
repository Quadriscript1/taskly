<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection
$conn = new mysqli("localhost", "root", "", "todo_list");

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Handle GET request - Fetch task by ID using Params
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(["status" => "error", "message" => "Task ID is required"]);
        exit;
    }

    $task_id = intval($_GET['id']);
    $query = "SELECT * FROM create_task WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($task = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "task" => $task]);
    } else {
        echo json_encode(["status" => "error", "message" => "Task not found"]);
    }
    exit;
}

// Handle POST request - Save new task using JSON or Form Params
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON body
    $json_data = json_decode(file_get_contents("php://input"), true);

    if ($json_data) {
        $title = $json_data['Title'] ?? '';
        $task_description = $json_data['Task_description'] ?? '';
        $add_subtrack = $json_data['Add_subtrack'] ?? '';
        $all_day = $json_data['All_Day'] ?? '';
    } else {
        // If JSON is not sent, use form parameters
        $title = $_POST['Title'] ?? '';
        $task_description = $_POST['Task_description'] ?? '';
        $add_subtrack = $_POST['Add_subtrack'] ?? '';
        $all_day = $_POST['All_Day'] ?? '';
       
    }

    // Validate fields
    if (empty($title) || empty($task_description) || empty($add_subtrack) || empty($all_day)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO create_task (Title, Task_description, Add_subtrack, All_Day) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $task_description, $add_subtrack, $all_day);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Task saved successfully',
            'task_id' => $stmt->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save task']);
    }
    exit;
}
?>