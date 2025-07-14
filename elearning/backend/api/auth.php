<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'login':
                login($db, $input);
                break;
            case 'register':
                register($db, $input);
                break;
            case 'logout':
                logout();
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'GET':
        getProfile($db);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function login($db, $data) {
    $error = validateRequired($data, ['username', 'password']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $query = "SELECT id, username, email, password, full_name, role, profile_image 
              FROM users WHERE username = :username OR email = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $data['username']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch();
        
        if (password_verify($data['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            unset($user['password']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ]);
        } else {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    } else {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
}

function register($db, $data) {
    $error = validateRequired($data, ['username', 'email', 'password', 'full_name']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    // Check if username or email already exists
    $query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $data['username']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(['error' => 'Username or email already exists'], 409);
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    // Validate password length
    if (strlen($data['password']) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    $role = isset($data['role']) && $data['role'] === 'instructor' ? 'instructor' : 'student';
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (username, email, password, full_name, role) 
              VALUES (:username, :email, :password, :full_name, :role)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $data['username']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':full_name', $data['full_name']);
    $stmt->bindParam(':role', $role);
    
    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful'
        ], 201);
    } else {
        jsonResponse(['error' => 'Registration failed'], 500);
    }
}

function logout() {
    session_destroy();
    jsonResponse([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

function getProfile($db) {
    requireLogin();
    
    $query = "SELECT id, username, email, full_name, role, profile_image, created_at 
              FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch();
        jsonResponse(['user' => $user]);
    } else {
        jsonResponse(['error' => 'User not found'], 404);
    }
}
?>