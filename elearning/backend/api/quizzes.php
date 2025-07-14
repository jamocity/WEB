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
                getQuizDetail($db);
                break;
            case 'attempts':
                getQuizAttempts($db);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'create':
                createQuiz($db, $input);
                break;
            case 'submit':
                submitQuiz($db, $input);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getQuizDetail($db) {
    if (!isset($_GET['id'])) {
        jsonResponse(['error' => 'Quiz ID required'], 400);
    }
    
    $quizId = $_GET['id'];
    
    // Get quiz details
    $query = "SELECT q.*, c.title as course_title 
              FROM quizzes q 
              JOIN courses c ON q.course_id = c.id 
              WHERE q.id = :quiz_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Quiz not found'], 404);
    }
    
    $quiz = $stmt->fetch();
    
    // Check if user is enrolled in the course
    if (isLoggedIn()) {
        $query = "SELECT id FROM enrollments 
                  WHERE student_id = :user_id AND course_id = :course_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':course_id', $quiz['course_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            jsonResponse(['error' => 'You must be enrolled in this course to access the quiz'], 403);
        }
        
        // Get previous attempts
        $query = "SELECT COUNT(*) as attempt_count, MAX(score) as best_score 
                  FROM quiz_attempts 
                  WHERE student_id = :user_id AND quiz_id = :quiz_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':quiz_id', $quizId);
        $stmt->execute();
        
        $attemptInfo = $stmt->fetch();
        $quiz['attempt_count'] = $attemptInfo['attempt_count'];
        $quiz['best_score'] = $attemptInfo['best_score'];
        
        // Check if can take quiz (attempts limit)
        $quiz['can_attempt'] = $attemptInfo['attempt_count'] < $quiz['attempts_allowed'];
    }
    
    // Get quiz questions (without correct answers for students)
    $query = "SELECT id, question, option_a, option_b, option_c, option_d, points, order_index 
              FROM quiz_questions 
              WHERE quiz_id = :quiz_id 
              ORDER BY order_index";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    $questions = $stmt->fetchAll();
    
    $quiz['questions'] = $questions;
    
    jsonResponse(['quiz' => $quiz]);
}

function createQuiz($db, $data) {
    requireRole('instructor');
    
    $error = validateRequired($data, ['course_id', 'title', 'questions']);
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
    
    try {
        $db->beginTransaction();
        
        // Create quiz
        $query = "INSERT INTO quizzes (course_id, title, description, passing_score, time_limit_minutes, attempts_allowed) 
                  VALUES (:course_id, :title, :description, :passing_score, :time_limit, :attempts_allowed)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':course_id', $data['course_id']);
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description'] ?? '');
        $stmt->bindParam(':passing_score', $data['passing_score'] ?? 70);
        $stmt->bindParam(':time_limit', $data['time_limit_minutes'] ?? 30);
        $stmt->bindParam(':attempts_allowed', $data['attempts_allowed'] ?? 3);
        $stmt->execute();
        
        $quizId = $db->lastInsertId();
        
        // Add questions
        foreach ($data['questions'] as $index => $question) {
            $query = "INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, points, order_index) 
                      VALUES (:quiz_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_answer, :points, :order_index)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':quiz_id', $quizId);
            $stmt->bindParam(':question', $question['question']);
            $stmt->bindParam(':option_a', $question['option_a']);
            $stmt->bindParam(':option_b', $question['option_b']);
            $stmt->bindParam(':option_c', $question['option_c']);
            $stmt->bindParam(':option_d', $question['option_d']);
            $stmt->bindParam(':correct_answer', $question['correct_answer']);
            $stmt->bindParam(':points', $question['points'] ?? 1);
            $stmt->bindParam(':order_index', $index + 1);
            $stmt->execute();
        }
        
        $db->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Quiz created successfully',
            'quiz_id' => $quizId
        ], 201);
        
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to create quiz'], 500);
    }
}

function submitQuiz($db, $data) {
    requireRole('student');
    
    $error = validateRequired($data, ['quiz_id', 'answers']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $quizId = $data['quiz_id'];
    
    // Get quiz details and verify enrollment
    $query = "SELECT q.*, c.id as course_id 
              FROM quizzes q 
              JOIN courses c ON q.course_id = c.id 
              WHERE q.id = :quiz_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Quiz not found'], 404);
    }
    
    $quiz = $stmt->fetch();
    
    // Verify enrollment
    $query = "SELECT id FROM enrollments 
              WHERE student_id = :user_id AND course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $quiz['course_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'You must be enrolled in this course'], 403);
    }
    
    // Check attempt limit
    $query = "SELECT COUNT(*) as attempt_count FROM quiz_attempts 
              WHERE student_id = :user_id AND quiz_id = :quiz_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    
    $attemptInfo = $stmt->fetch();
    if ($attemptInfo['attempt_count'] >= $quiz['attempts_allowed']) {
        jsonResponse(['error' => 'Maximum attempts reached'], 403);
    }
    
    // Get correct answers
    $query = "SELECT id, correct_answer, points FROM quiz_questions WHERE quiz_id = :quiz_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate score
    $totalQuestions = count($questions);
    $correctAnswers = 0;
    $totalPoints = 0;
    $earnedPoints = 0;
    
    foreach ($questions as $question) {
        $questionId = $question['id'];
        $correctAnswer = $question['correct_answer'];
        $points = $question['points'];
        $totalPoints += $points;
        
        if (isset($data['answers'][$questionId]) && $data['answers'][$questionId] === $correctAnswer) {
            $correctAnswers++;
            $earnedPoints += $points;
        }
    }
    
    $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
    $passed = $score >= $quiz['passing_score'];
    
    // Save attempt
    $query = "INSERT INTO quiz_attempts (student_id, quiz_id, score, total_questions, correct_answers, passed, time_taken_minutes) 
              VALUES (:student_id, :quiz_id, :score, :total_questions, :correct_answers, :passed, :time_taken)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->bindParam(':score', $score);
    $stmt->bindParam(':total_questions', $totalQuestions);
    $stmt->bindParam(':correct_answers', $correctAnswers);
    $stmt->bindParam(':passed', $passed);
    $stmt->bindParam(':time_taken', $data['time_taken_minutes'] ?? 0);
    
    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'score' => $score,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'passed' => $passed,
            'passing_score' => $quiz['passing_score']
        ]);
    } else {
        jsonResponse(['error' => 'Failed to save quiz attempt'], 500);
    }
}

function getQuizAttempts($db) {
    requireLogin();
    
    if (!isset($_GET['quiz_id'])) {
        jsonResponse(['error' => 'Quiz ID required'], 400);
    }
    
    $quizId = $_GET['quiz_id'];
    
    $query = "SELECT * FROM quiz_attempts 
              WHERE student_id = :user_id AND quiz_id = :quiz_id 
              ORDER BY attempted_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':quiz_id', $quizId);
    $stmt->execute();
    
    $attempts = $stmt->fetchAll();
    jsonResponse(['attempts' => $attempts]);
}
?>