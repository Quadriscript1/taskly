<?php
require 'vendor/autoload.php';
include('constant.php');

use Dotenv\Dotenv;
use Google\Client as GoogleClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Helper function to generate JWT token
function generateJWTToken($user_id) {
    if (!isset($_ENV['JWT_SECRET_KEY']) || empty($_ENV['JWT_SECRET_KEY'])) {
        throw new Exception('JWT_SECRET_KEY is not set in environment variables');
    }
    
    $secret_key = $_ENV['JWT_SECRET_KEY'];
    $issued_at = time();
    $expiration = $issued_at + (60 * 60 * 24); // 24 hours

    $payload = [
        'user_id' => $user_id,
        'iat' => $issued_at,
        'exp' => $expiration
    ];

    return JWT::encode($payload, $secret_key, 'HS256');
}

// Set response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize Google Client
function initGoogleClient() {
    $client = new GoogleClient();
    $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
    $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
    $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
    $client->addScope('email');
    $client->addScope('profile');
    return $client;
}

// Verify Apple Sign In token
function verifyAppleToken($token) {
    try {
        $publicKey = file_get_contents('https://appleid.apple.com/auth/keys');
        $keys = json_decode($publicKey, true);
        
        // Get the key set from Apple's JWKs
        $tks = explode('.', $token);
        if (count($tks) != 3) {
            throw new Exception('Wrong number of segments in token');
        }
        $header = json_decode(base64_decode($tks[0]));
        if (!$header || !isset($header->kid)) {
            throw new Exception('Invalid token header');
        }
        
        // Find the matching key from Apple's key set
        $key = null;
        foreach ($keys['keys'] as $k) {
            if ($k['kid'] === $header->kid) {
                $key = $k;
                break;
            }
        }
        
        if (!$key) {
            throw new Exception('No matching key found');
        }

        $decoded = JWT::decode($token, new Key($key['n'], 'RS256'));
        return [
            'success' => true,
            'data' => $decoded
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle POST request (User Registration)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $auth_type = $data['auth_type'] ?? 'email';

    switch ($auth_type) {
        case 'google':
            if (!isset($data['token'])) {
                echo json_encode(["status" => "error", "message" => "Google token is required"]);
                exit;
            }

            try {
                $client = initGoogleClient();
                $payload = $client->verifyIdToken($data['token']);
                
                if ($payload) {
                    $email = $payload['email'];
                    $full_name = $payload['name'];
                    $google_id = $payload['sub'];
                    $profile_image = $payload['picture'] ?? '';

                    // Check if user exists
                    $check_user = "SELECT id FROM sign_up WHERE email = ?";
                    $stmt = $conn->prepare($check_user);
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        // Update existing user
                        $user = $result->fetch_assoc();
                        echo json_encode(["status" => "success", "message" => "Login successful", "user_id" => $user['id']]);
                    } else {
                        // Create new user
                        $sql = "INSERT INTO sign_up (full_name, email, profile_image) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sss", $full_name, $email, $profile_image);
                        
                        if ($stmt->execute()) {
                            echo json_encode(["status" => "success", "message" => "Sign Up Successful", "user_id" => $stmt->insert_id]);
                        } else {
                            echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => $stmt->error]);
                        }
                    }
                }
            } catch (Exception $e) {
                echo json_encode(["status" => "error", "message" => "Invalid Google token", "error" => $e->getMessage()]);
                exit;
            }
            break;

        case 'apple':
            if (!isset($data['token'])) {
                echo json_encode(["status" => "error", "message" => "Apple token is required"]);
                exit;
            }

            $verification = verifyAppleToken($data['token']);
            if ($verification['success']) {
                $apple_data = $verification['data'];
                $email = $apple_data->email;
                $full_name = $data['full_name'] ?? ''; // Apple might not provide name
                $apple_id = $apple_data->sub;

                // Check if user exists
                $check_user = "SELECT id FROM sign_up WHERE email = ?";
                $stmt = $conn->prepare($check_user);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Update existing user
                    $user = $result->fetch_assoc();
                    echo json_encode(["status" => "success", "message" => "Login successful", "user_id" => $user['id']]);
                } else {
                    // Create new user
                    $sql = "INSERT INTO sign_up (full_name, email) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ss", $full_name, $email);
                    
                    if ($stmt->execute()) {
                        echo json_encode(["status" => "success", "message" => "Sign Up Successful", "user_id" => $stmt->insert_id]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => $stmt->error]);
                    }
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid Apple token", "error" => $verification['error']]);
                exit;
            }
            break;

        case 'email':
            // Existing email registration logic

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
    $remember_me = ($data['remember_me']) ? mysqli_real_escape_string($conn, $data['remember_me']) : 0; // Default value (false)

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
    $sql = "INSERT INTO sign_up (full_name, email, phone_number, password, remember_me) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $full_name, $email, $phone_number, $password, $remember_me);

    try {
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            try {
                // Generate JWT token
                $jwt_token = generateJWTToken($user_id);
                
                // Get user details for response
                $user_query = "SELECT id, full_name, email, profile_image FROM sign_up WHERE id = ?";
                $user_stmt = $conn->prepare($user_query);
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Sign Up Successful",
                    "user" => [
                        "id" => $user['id'],
                        "full_name" => $user['full_name'],
                        "email" => $user['email'],
                        "profile_image" => $user['profile_image']
                    ],
                    "token" => $jwt_token
                ]);
            } catch (Exception $e) {
                // If token generation fails, still return user data but with a warning
                echo json_encode([
                    "status" => "success",
                    "message" => "Sign Up Successful, but session token generation failed. Please try logging in.",
                    "user" => [
                        "id" => $user_id
                    ],
                    "warning" => "Token generation failed: " . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Sign Up Failed", "error" => $e->getMessage()]);
    }
    $stmt->close();
    $conn->close();
    exit;
}
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
    $sql = "SELECT id, full_name, email, phone_number, profile_image, remember_me FROM sign_up";

    
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

// Handle PATCH request (Toggle Remember Me)

if ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['id'])) {
        echo json_encode(["status" => "error", "message" => "User ID is required"]);
        exit;
    }

    $id = (int) $data['id'];

    // Fetch current remember_me status
    $check_query = "SELECT remember_me FROM sign_up WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $new_status = $user['remember_me'] ? 0 : 1; // Toggle between 0 and 1

        // Update remember_me status
        $update_query = "UPDATE sign_up SET remember_me = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $new_status, $id);

        if ($update_stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Remember Me status updated", "remember_me" => $new_status]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update Remember Me status"]);
        }

        $update_stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
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
