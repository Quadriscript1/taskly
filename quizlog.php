<?php  
// Start session if needed
// session_start(); 

require_once 'constant.php';  

// Ensure $conn is defined properly
if (!isset($conn) || $conn->connect_error) {  
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . ($conn->connect_error ?? 'Connection not established')]));  
}  

// Create Sign Up Table if not exists  
$createTableQuery = "CREATE TABLE IF NOT EXISTS quiz_table (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    full_name VARCHAR(255) NOT NULL UNIQUE,  
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  
)";  
$conn->query($createTableQuery);

 

$message = "";

// Handle JSON & Form Data Input
$input_data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    // Use JSON input if available, else fallback to $_POST
    $full_name = isset($input_data['full_name']) ? trim($input_data['full_name']) : (isset($_POST['full_name']) ? trim($_POST['full_name']) : null);

    if (empty($full_name)) {  
        echo json_encode(["status" => "error", "message" => "Full name is required"]);
        exit;
    } 

    // Check for duplicate entry  
    $checkDuplicateQuery = "SELECT id FROM quiz_table WHERE full_name = ?";  
    $stmt = $conn->prepare($checkDuplicateQuery);
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {  
        $stmt->close(); 

        // Insert User  
        $insertQuery = "INSERT INTO quiz_table (full_name, created_at) VALUES (?, NOW())";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("s", $full_name);
        
        if ($stmt->execute()) {  
            $user_id = $stmt->insert_id;  
            $_SESSION["user_id"] = $user_id;  
            $_SESSION["full_name"] = $full_name;
            
            $message = "Signup successful! Welcome, " . htmlspecialchars($full_name) . "!";  
            echo json_encode(["status" => "success", "message" => $message]);
        } else {  
            echo json_encode(["status" => "error", "message" => "Signup failed: " . $stmt->error]);  
        }  
    } else {  
        echo json_encode(["status" => "error", "message" => "This name is already registered"]);
    }
    $stmt->close();  
    $conn->close();
    exit; // Exit after sending JSON response  
}  

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $user_id = intval($_GET['id']); // Sanitize input

    $query = "SELECT id, full_name, created_at FROM quiz_table WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(["status" => "success", "data" => $user]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}
?>
