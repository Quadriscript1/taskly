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

// Set response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
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

// Handle POST request for login
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
                    
                    // Check if user exists
                    $check_user = "SELECT id, full_name, email, profile_image FROM sign_up WHERE email = ?";
                    $stmt = $conn->prepare($check_user);
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        // Generate JWT token for session
                        $jwt_token = generateJWTToken($user['id']);
                        echo json_encode([
                            "status" => "success", 
                            "message" => "Login successful",
                            "user" => [
                                "id" => $user['id'],
                                "full_name" => $user['full_name'],
                                "email" => $user['email'],
                                "profile_image" => $user['profile_image']
                            ],
                            "token" => $jwt_token
                        ]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "User not registered"]);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(["status" => "error", "message" => "Invalid Google token"]);
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

                // Check if user exists
                $check_user = "SELECT id, full_name, email, profile_image FROM sign_up WHERE email = ?";
                $stmt = $conn->prepare($check_user);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    // Generate JWT token for session
                    $jwt_token = generateJWTToken($user['id']);
                    echo json_encode([
                        "status" => "success", 
                        "message" => "Login successful",
                        "user" => [
                            "id" => $user['id'],
                            "full_name" => $user['full_name'],
                            "email" => $user['email'],
                            "profile_image" => $user['profile_image']
                        ],
                        "token" => $jwt_token
                    ]);
                } else {
                    echo json_encode(["status" => "error", "message" => "User not registered"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid Apple token"]);
                exit;
            }
            break;

        case 'email':
            if (!isset($data['email'], $data['password'])) {
                echo json_encode(["status" => "error", "message" => "Email and password are required"]);
                exit;
            }

            $email = $data['email'];
            $password = $data['password'];
            $remember_me = isset($data['remember_me']) ? (bool)$data['remember_me'] : false;

            // Get user with the provided email
            $sql = "SELECT id, full_name, email, password, profile_image FROM sign_up WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // Verify password
                if (password_verify($password, $user['password'])) {
                    try {
                        // Generate JWT token
                        $jwt_token = generateJWTToken($user['id']);

                        // Update remember_me status if needed
                    if ($remember_me) {
                        $update_sql = "UPDATE sign_up SET remember_me = 1 WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                    }

                        echo json_encode([
                            "status" => "success",
                            "message" => "Login successful",
                            "user" => [
                                "id" => $user['id'],
                                "full_name" => $user['full_name'],
                                "email" => $user['email'],
                                "profile_image" => $user['profile_image']
                            ],
                            "token" => $jwt_token
                        ]);
                    } catch (Exception $e) {
                        echo json_encode([
                            "status" => "error",
                            "message" => "Login successful but token generation failed",
                            "error" => $e->getMessage(),
                            "user" => [
                                "id" => $user['id'],
                                "email" => $user['email']
                            ]
                        ]);
                    }
                } else {
                    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "User not found"]);
            }
            break;

        default:
            echo json_encode(["status" => "error", "message" => "Invalid authentication type"]);
            break;
    }
    exit;
}
// Helper function to generate JWT token
function generateJWTToken($user_id) {
    if (!isset($_ENV['JWT_SECRET_KEY']) || empty($_ENV['JWT_SECRET_KEY'])) {
        throw new Exception('JWT_SECRET_KEY is not set in environment variables');
    }
    
    $secret_key = $_ENV['JWT_SECRET_KEY'];
    $issued_at = time();
    $expiration = $issued_at + (60 * 60 * 24); // 24 hours from now

    $payload = [
        'user_id' => $user_id,
        'iat' => $issued_at,
        'exp' => $expiration
    ];

    try {
        return JWT::encode($payload, $secret_key, 'HS256');
    } catch (Exception $e) {
        throw new Exception('Failed to generate token: ' . $e->getMessage());
    }
}