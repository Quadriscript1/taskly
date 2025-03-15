<?php
require 'vendor/autoload.php';
include('constant.php');

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

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

// Function to get user ID from JWT token
function getUserFromToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        throw new Exception('No token provided');
    }

    $auth_header = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $auth_header);

    try {
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
        return $decoded->user_id;
    } catch (Exception $e) {
        throw new Exception('Invalid token: ' . $e->getMessage());
    }
}

// Database connection


if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}


// Handle GET request - Fetch tasks for the authenticated user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get user ID from token
        $user_id = getUserFromToken();
        
        // Get tasks for the authenticated user
        $query = "SELECT id, title, task_description, all_day, start_date, end_date, status FROM tasks WHERE user_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $tasks = [];
        while ($task = $result->fetch_assoc()) {
            $tasks[] = $task;
        }

        echo json_encode([
            "status" => "success", 
            "tasks" => $tasks,
            "message" => empty($tasks) ? "No tasks found" : "Tasks retrieved successfully"
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
    }
    exit;
}




// Handle POST request - Save new task using JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get user ID from token
        $user_id = getUserFromToken();
        
        // Read JSON input
        $json_data = json_decode(file_get_contents("php://input"), true);
        if (!$json_data) {
            throw new Exception("Invalid JSON format: " . json_last_error_msg());
        }

        // Extract and validate fields
        $title = trim($json_data['title'] ?? '');
        $task_description = trim($json_data['task_description'] ?? '');
        $all_day = isset($json_data['all_day']) ? (int)$json_data['all_day'] : null;
        $start_date = trim($json_data['start_date'] ?? '');
        $end_date = trim($json_data['end_date'] ?? '');

        // Validate required fields
        if (empty($title) || empty($task_description) || $all_day === null || empty($start_date) || empty($end_date)) {
            throw new Exception("All fields are required");
        }

        // Convert dates to correct format
        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));

        // Insert task into database
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, task_description, all_day, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ississ", $user_id, $title, $task_description, $all_day, $start_date, $end_date);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Task created successfully",
                "task" => [
                    "id" => $stmt->insert_id,
                    "title" => $title,
                    "task_description" => $task_description,
                    "all_day" => $all_day,
                    "start_date" => $start_date,
                    "end_date" => $end_date,
                    "status" => "pending"
                ]
            ]);
        } else {
            throw new Exception("Failed to save task");
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
    }
    exit;
}




// Handle PUT request (Update Task)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        // Get user ID from token
        $user_id = getUserFromToken();
        
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

        if (!$task_id || $task_id <= 0) {
            throw new Exception("Valid Task ID is required");
        }

        if (!isset($json_data)) {
            $json_data = json_decode(file_get_contents("php://input"), true);
        }

        if (!$json_data) {
            throw new Exception("Invalid JSON format");
        }

        // Extract and validate fields
        $title = trim($json_data['title'] ?? '');
        $task_description = trim($json_data['task_description'] ?? '');
        $all_day = isset($json_data['all_day']) ? (int)$json_data['all_day'] : null;
        $start_date = trim($json_data['start_date'] ?? '');
        $end_date = trim($json_data['end_date'] ?? '');
        $status = trim($json_data['status'] ?? '');

        if (empty($title) || empty($task_description) || $all_day === null || empty($start_date) || empty($end_date) || empty($status)) {
            throw new Exception("All fields are required");
        }

        $valid_statuses = ["pending", "completed", "in_progress"];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Invalid status");
        }

        // Verify task belongs to user
        $check_query = "SELECT id FROM tasks WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $task_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if (!$check_result->fetch_assoc()) {
            throw new Exception("Task not found or you don't have permission to modify it");
        }
        $check_stmt->close();

        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));

        // Update task in database
        $update_query = "UPDATE tasks SET title = ?, task_description = ?, all_day = ?, start_date = ?, end_date = ?, status = ? WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssisssii", $title, $task_description, $all_day, $start_date, $end_date, $status, $task_id, $user_id);

        if ($update_stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Task updated successfully",
                "task" => [
                    "id" => $task_id,
                    "title" => $title,
                    "task_description" => $task_description,
                    "all_day" => $all_day,
                    "start_date" => $start_date,
                    "end_date" => $end_date,
                    "status" => $status
                ]
            ]);
        } else {
            throw new Exception("Failed to update task");
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if (isset($check_stmt)) $check_stmt->close();
        if (isset($update_stmt)) $update_stmt->close();
        if (isset($conn)) $conn->close();
    }
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        // Get user ID from token
        $user_id = getUserFromToken();
        
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

        if (!$task_id || $task_id <= 0) {
            throw new Exception("Valid Task ID is required");
        }

        // Check if the task exists and belongs to the user
        $check_query = "SELECT id FROM tasks WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $task_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if (!$check_result->fetch_assoc()) {
            throw new Exception("Task not found or you don't have permission to delete it");
        }
        $check_stmt->close();

        // Delete the task
        $delete_query = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $task_id, $user_id);

        if ($delete_stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Task deleted successfully"]);
        } else {
            throw new Exception("Failed to delete task");
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        if (isset($check_stmt)) $check_stmt->close();
        if (isset($delete_stmt)) $delete_stmt->close();
        if (isset($conn)) $conn->close();
    }
    exit;
}

// If request method is not GET, POST, PUT, or DELETE
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
