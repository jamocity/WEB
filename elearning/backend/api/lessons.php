<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'detail':
                getLessonDetail($db);
                break;
            case 'progress':
                getLessonProgress($db);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'create':
                createLesson($db, $input);
                break;
            case 'complete':
                markLessonComplete($db, $input);
                break;
            case 'update-progress':
                updateProgress($db, $input);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'PUT':
        updateLesson($db, $input);
        break;
    
    case 'DELETE':
        deleteLesson($db);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getLessonDetail($db) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Lesson ID required'], 400);
    }
    
    $lessonId = $_GET['id'];
    
    // Get lesson details
    $query = "SELECT l.*, c.title as course_title, c.instructor_id 
              FROM lessons l 
              JOIN courses c ON l.course_id = c.id 
              WHERE l.id = :lesson_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Lesson not found'], 404);
    }
    
    $lesson = $stmt->fetch();
    
    // Check if user has access to this lesson
    if (isLoggedIn()) {
        // Check if user is enrolled or is the instructor
        if ($_SESSION['role'] === 'student') {
            $query = "SELECT id FROM enrollments 
                      WHERE student_id = :user_id AND course_id = :course_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':course_id', $lesson['course_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0 && !$lesson['is_free']) {
                jsonResponse(['error' => 'You must be enrolled in this course to access this lesson'], 403);
            }
            
            // Get progress for this lesson
            $query = "SELECT completed, completed_at, watch_time_seconds 
                      FROM lesson_progress 
                      WHERE student_id = :user_id AND lesson_id = :lesson_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':lesson_id', $lessonId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $progress = $stmt->fetch();
                $lesson['completed'] = (bool)$progress['completed'];
                $lesson['completed_at'] = $progress['completed_at'];
                $lesson['watch_time_seconds'] = $progress['watch_time_seconds'];
            } else {
                $lesson['completed'] = false;
                $lesson['completed_at'] = null;
                $lesson['watch_time_seconds'] = 0;
            }
        } elseif ($_SESSION['role'] === 'instructor') {
            // Verify ownership
            if ($lesson['instructor_id'] != $_SESSION['user_id']) {
                jsonResponse(['error' => 'Access denied'], 403);
            }
        }
    } else {
        // Not logged in - only allow free lessons
        if (!$lesson['is_free']) {
            jsonResponse(['error' => 'You must be logged in to access this lesson'], 401);
        }
    }
    
    jsonResponse(['lesson' => $lesson]);
}

function createLesson($db, $data) {
    requireRole('instructor');
    
    $error = validateRequired($data, ['course_id', 'title']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    // Verify course ownership
    $query = "SELECT id FROM courses WHERE id = :course_id AND instructor_id = :instructor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $data['course_id']);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Course not found or access denied'], 404);
    }
    
    // Get next order index
    $query = "SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM lessons WHERE course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $data['course_id']);
    $stmt->execute();
    $nextOrder = $stmt->fetch()['next_order'];
    
    $query = "INSERT INTO lessons (course_id, title, description, video_url, duration_minutes, order_index, is_free) 
              VALUES (:course_id, :title, :description, :video_url, :duration_minutes, :order_index, :is_free)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $data['course_id']);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':description', $data['description'] ?? '');
    $stmt->bindParam(':video_url', $data['video_url'] ?? '');
    $stmt->bindParam(':duration_minutes', $data['duration_minutes'] ?? 0);
    $stmt->bindParam(':order_index', $data['order_index'] ?? $nextOrder);
    $stmt->bindParam(':is_free', $data['is_free'] ?? false);
    
    if ($stmt->execute()) {
        $lessonId = $db->lastInsertId();
        jsonResponse([
            'success' => true,
            'message' => 'Lesson created successfully',
            'lesson_id' => $lessonId
        ], 201);
    } else {
        jsonResponse(['error' => 'Failed to create lesson'], 500);
    }
}

function updateLesson($db, $data) {
    requireRole('instructor');
    
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Lesson ID required'], 400);
    }
    
    $lessonId = $_GET['id'];
    
    // Verify lesson ownership through course
    $query = "SELECT l.id FROM lessons l 
              JOIN courses c ON l.course_id = c.id 
              WHERE l.id = :lesson_id AND c.instructor_id = :instructor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Lesson not found or access denied'], 404);
    }
    
    $updateFields = [];
    $params = [':lesson_id' => $lessonId];
    
    if (isset($data['title'])) {
        $updateFields[] = "title = :title";
        $params[':title'] = $data['title'];
    }
    if (isset($data['description'])) {
        $updateFields[] = "description = :description";
        $params[':description'] = $data['description'];
    }
    if (isset($data['video_url'])) {
        $updateFields[] = "video_url = :video_url";
        $params[':video_url'] = $data['video_url'];
    }
    if (isset($data['duration_minutes'])) {
        $updateFields[] = "duration_minutes = :duration_minutes";
        $params[':duration_minutes'] = $data['duration_minutes'];
    }
    if (isset($data['order_index'])) {
        $updateFields[] = "order_index = :order_index";
        $params[':order_index'] = $data['order_index'];
    }
    if (isset($data['is_free'])) {
        $updateFields[] = "is_free = :is_free";
        $params[':is_free'] = $data['is_free'];
    }
    
    if (empty($updateFields)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }
    
    $query = "UPDATE lessons SET " . implode(', ', $updateFields) . " WHERE id = :lesson_id";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute($params)) {
        jsonResponse(['success' => true, 'message' => 'Lesson updated successfully']);
    } else {
        jsonResponse(['error' => 'Failed to update lesson'], 500);
    }
}

function markLessonComplete($db, $data) {
    requireRole('student');
    
    $error = validateRequired($data, ['lesson_id']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $lessonId = $data['lesson_id'];
    
    // Verify enrollment
    $query = "SELECT l.course_id FROM lessons l 
              JOIN enrollments e ON l.course_id = e.course_id 
              WHERE l.id = :lesson_id AND e.student_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'You must be enrolled in this course'], 403);
    }
    
    $courseId = $stmt->fetch()['course_id'];
    
    // Insert or update lesson progress
    $query = "INSERT INTO lesson_progress (student_id, lesson_id, completed, completed_at, watch_time_seconds) 
              VALUES (:student_id, :lesson_id, 1, NOW(), :watch_time)
              ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW(), watch_time_seconds = :watch_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':watch_time', $data['watch_time_seconds'] ?? 0);
    
    if ($stmt->execute()) {
        // Update course progress
        updateCourseProgress($db, $_SESSION['user_id'], $courseId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Lesson marked as complete'
        ]);
    } else {
        jsonResponse(['error' => 'Failed to update lesson progress'], 500);
    }
}

function updateProgress($db, $data) {
    requireRole('student');
    
    $error = validateRequired($data, ['lesson_id', 'watch_time_seconds']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $lessonId = $data['lesson_id'];
    
    // Verify enrollment
    $query = "SELECT l.course_id FROM lessons l 
              JOIN enrollments e ON l.course_id = e.course_id 
              WHERE l.id = :lesson_id AND e.student_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'You must be enrolled in this course'], 403);
    }
    
    // Update watch time
    $query = "INSERT INTO lesson_progress (student_id, lesson_id, watch_time_seconds) 
              VALUES (:student_id, :lesson_id, :watch_time)
              ON DUPLICATE KEY UPDATE watch_time_seconds = :watch_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':watch_time', $data['watch_time_seconds']);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Progress updated']);
    } else {
        jsonResponse(['error' => 'Failed to update progress'], 500);
    }
}

function getLessonProgress($db) {
    requireRole('student');
    
    if (!isset($_GET['course_id'])) {
        jsonResponse(['error' => 'Course ID required'], 400);
    }
    
    $courseId = $_GET['course_id'];
    
    $query = "SELECT l.id, l.title, lp.completed, lp.completed_at, lp.watch_time_seconds
              FROM lessons l
              LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = :user_id
              WHERE l.course_id = :course_id
              ORDER BY l.order_index";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    $progress = $stmt->fetchAll();
    jsonResponse(['progress' => $progress]);
}

function updateCourseProgress($db, $studentId, $courseId) {
    // Calculate overall course progress
    $query = "SELECT 
                COUNT(*) as total_lessons,
                SUM(CASE WHEN lp.completed = 1 THEN 1 ELSE 0 END) as completed_lessons
              FROM lessons l
              LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = :student_id
              WHERE l.course_id = :course_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    $result = $stmt->fetch();
    $totalLessons = $result['total_lessons'];
    $completedLessons = $result['completed_lessons'];
    
    $progressPercentage = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
    $isCompleted = $progressPercentage >= 100;
    
    $query = "UPDATE enrollments 
              SET progress_percentage = :progress, completed_at = " . ($isCompleted ? "NOW()" : "NULL") . "
              WHERE student_id = :student_id AND course_id = :course_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':progress', $progressPercentage);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
}

function deleteLesson($db) {
    requireRole('instructor');
    
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Lesson ID required'], 400);
    }
    
    $lessonId = $_GET['id'];
    
    // Verify lesson ownership through course
    $query = "SELECT l.id FROM lessons l 
              JOIN courses c ON l.course_id = c.id 
              WHERE l.id = :lesson_id AND c.instructor_id = :instructor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    $stmt->bindParam(':instructor_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Lesson not found or access denied'], 404);
    }
    
    $query = "DELETE FROM lessons WHERE id = :lesson_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lesson_id', $lessonId);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Lesson deleted successfully']);
    } else {
        jsonResponse(['error' => 'Failed to delete lesson'], 500);
    }
}
?>