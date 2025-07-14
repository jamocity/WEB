<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'list':
                getCourses($db);
                break;
            case 'detail':
                getCourseDetail($db);
                break;
            case 'my-courses':
                getMyCourses($db);
                break;
            case 'my-enrollments':
                getMyEnrollments($db);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'create':
                createCourse($db, $input);
                break;
            case 'enroll':
                enrollInCourse($db, $input);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'PUT':
        updateCourse($db, $input);
        break;
    
    case 'DELETE':
        deleteCourse($db);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getCourses($db) {
    $query = "SELECT c.*, u.full_name as instructor_name,
                     (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
              FROM courses c 
              JOIN users u ON c.instructor_id = u.id 
              WHERE c.status = 'published'
              ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    jsonResponse(['courses' => $courses]);
}

function getCourseDetail($db) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Course ID required'], 400);
    }
    
    $courseId = $_GET['id'];
    
    // Get course details
    $query = "SELECT c.*, u.full_name as instructor_name, u.profile_image as instructor_image
              FROM courses c 
              JOIN users u ON c.instructor_id = u.id 
              WHERE c.id = :course_id AND c.status = 'published'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Course not found'], 404);
    }
    
    $course = $stmt->fetch();
    
    // Get course lessons
    $query = "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY order_index";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    $lessons = $stmt->fetchAll();
    
    // Get course quizzes
    $query = "SELECT * FROM quizzes WHERE course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    $quizzes = $stmt->fetchAll();
    
    // Check if user is enrolled (if logged in)
    $isEnrolled = false;
    $progress = 0;
    if (isLoggedIn()) {
        $query = "SELECT progress_percentage FROM enrollments 
                  WHERE student_id = :user_id AND course_id = :course_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':course_id', $courseId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $isEnrolled = true;
            $enrollment = $stmt->fetch();
            $progress = $enrollment['progress_percentage'];
        }
    }
    
    $course['lessons'] = $lessons;
    $course['quizzes'] = $quizzes;
    $course['is_enrolled'] = $isEnrolled;
    $course['progress'] = $progress;
    
    jsonResponse(['course' => $course]);
}

function getMyCourses($db) {
    requireRole('instructor');
    
    $query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count,
                     (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
              FROM courses c 
              WHERE c.instructor_id = :instructor_id
              ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    jsonResponse(['courses' => $courses]);
}

function getMyEnrollments($db) {
    requireRole('student');
    
    $query = "SELECT c.*, e.enrolled_at, e.progress_percentage, e.completed_at,
                     u.full_name as instructor_name
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              JOIN users u ON c.instructor_id = u.id
              WHERE e.student_id = :student_id
              ORDER BY e.enrolled_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $enrollments = $stmt->fetchAll();
    jsonResponse(['enrollments' => $enrollments]);
}

function createCourse($db, $data) {
    requireRole('instructor');
    
    $error = validateRequired($data, ['title', 'description']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $query = "INSERT INTO courses (title, description, instructor_id, level, status) 
              VALUES (:title, :description, :instructor_id, :level, :status)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->bindParam(':level', $data['level'] ?? 'beginner');
    $stmt->bindParam(':status', $data['status'] ?? 'draft');
    
    if ($stmt->execute()) {
        $courseId = $db->lastInsertId();
        jsonResponse([
            'success' => true,
            'message' => 'Course created successfully',
            'course_id' => $courseId
        ], 201);
    } else {
        jsonResponse(['error' => 'Failed to create course'], 500);
    }
}

function updateCourse($db, $data) {
    requireRole('instructor');
    
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Course ID required'], 400);
    }
    
    $courseId = $_GET['id'];
    
    // Verify course ownership
    $query = "SELECT id FROM courses WHERE id = :course_id AND instructor_id = :instructor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Course not found or access denied'], 404);
    }
    
    $updateFields = [];
    $params = [':course_id' => $courseId];
    
    if (isset($data['title'])) {
        $updateFields[] = "title = :title";
        $params[':title'] = $data['title'];
    }
    if (isset($data['description'])) {
        $updateFields[] = "description = :description";
        $params[':description'] = $data['description'];
    }
    if (isset($data['level'])) {
        $updateFields[] = "level = :level";
        $params[':level'] = $data['level'];
    }
    if (isset($data['status'])) {
        $updateFields[] = "status = :status";
        $params[':status'] = $data['status'];
    }
    
    if (empty($updateFields)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }
    
    $query = "UPDATE courses SET " . implode(', ', $updateFields) . " WHERE id = :course_id";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        jsonResponse(['success' => true, 'message' => 'Course updated successfully']);
    } else {
        jsonResponse(['error' => 'Failed to update course'], 500);
    }
}

function enrollInCourse($db, $data) {
    requireRole('student');
    
    $error = validateRequired($data, ['course_id']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $courseId = $data['course_id'];
    
    // Check if course exists and is published
    $query = "SELECT id FROM courses WHERE id = :course_id AND status = 'published'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Course not found or not available'], 404);
    }
    
    // Check if already enrolled
    $query = "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(['error' => 'Already enrolled in this course'], 409);
    }
    
    // Enroll student
    $query = "INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    
    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'message' => 'Successfully enrolled in course'
        ], 201);
    } else {
        jsonResponse(['error' => 'Failed to enroll in course'], 500);
    }
}

function deleteCourse($db) {
    requireRole('instructor');
    
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Course ID required'], 400);
    }
    
    $courseId = $_GET['id'];
    
    // Verify course ownership
    $query = "SELECT id FROM courses WHERE id = :course_id AND instructor_id = :instructor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Course not found or access denied'], 404);
    }
    
    $query = "DELETE FROM courses WHERE id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Course deleted successfully']);
    } else {
        jsonResponse(['error' => 'Failed to delete course'], 500);
    }
}
?>