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
            case 'my-certificates':
                getMyCertificates($db);
                break;
            case 'verify':
                verifyCertificate($db);
                break;
            case 'download':
                downloadCertificate($db);
                break;
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    
    case 'POST':
        generateCertificate($db, $input);
        break;
    
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getMyCertificates($db) {
    requireRole('student');
    
    $query = "SELECT c.*, cert.certificate_code, cert.issued_at, u.full_name as instructor_name
              FROM certificates cert
              JOIN courses c ON cert.course_id = c.id
              JOIN users u ON c.instructor_id = u.id
              WHERE cert.student_id = :student_id
              ORDER BY cert.issued_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $certificates = $stmt->fetchAll();
    jsonResponse(['certificates' => $certificates]);
}

function generateCertificate($db, $data) {
    requireRole('student');
    
    $error = validateRequired($data, ['course_id']);
    if ($error) {
        jsonResponse(['error' => $error], 400);
    }
    
    $courseId = $data['course_id'];
    
    // Check if student completed the course
    $query = "SELECT e.progress_percentage, e.completed_at, c.title, c.instructor_id, u.full_name as instructor_name
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              JOIN users u ON c.instructor_id = u.id
              WHERE e.student_id = :student_id AND e.course_id = :course_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'You are not enrolled in this course'], 403);
    }
    
    $enrollment = $stmt->fetch();
    
    if ($enrollment['progress_percentage'] < 100 || empty($enrollment['completed_at'])) {
        jsonResponse(['error' => 'You must complete the course to get a certificate'], 400);
    }
    
    // Check if certificate already exists
    $query = "SELECT certificate_code FROM certificates 
              WHERE student_id = :student_id AND course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch();
        jsonResponse([
            'success' => true,
            'message' => 'Certificate already exists',
            'certificate_code' => $existing['certificate_code']
        ]);
    }
    
    // Check if student passed all required quizzes
    $query = "SELECT q.id, q.title, q.passing_score,
                     (SELECT MAX(score) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = :student_id) as best_score
              FROM quizzes q
              WHERE q.course_id = :course_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    $quizzes = $stmt->fetchAll();
    
    foreach ($quizzes as $quiz) {
        if ($quiz['best_score'] === null || $quiz['best_score'] < $quiz['passing_score']) {
            jsonResponse([
                'error' => "You must pass the quiz '{$quiz['title']}' to get a certificate. Required score: {$quiz['passing_score']}%"
            ], 400);
        }
    }
    
    // Generate unique certificate code
    $certificateCode = generateCertificateCode();
    
    // Insert certificate
    $query = "INSERT INTO certificates (student_id, course_id, certificate_code) 
              VALUES (:student_id, :course_id, :certificate_code)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->bindParam(':certificate_code', $certificateCode);
    
    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'message' => 'Certificate generated successfully',
            'certificate_code' => $certificateCode
        ], 201);
    } else {
        jsonResponse(['error' => 'Failed to generate certificate'], 500);
    }
}

function verifyCertificate($db) {
    if (!isset($_GET['code'])) {
        jsonResponse(['error' => 'Certificate code required'], 400);
    }
    
    $code = $_GET['code'];
    
    $query = "SELECT cert.*, c.title as course_title, c.description as course_description,
                     s.full_name as student_name, s.email as student_email,
                     i.full_name as instructor_name, i.email as instructor_email
              FROM certificates cert
              JOIN courses c ON cert.course_id = c.id
              JOIN users s ON cert.student_id = s.id
              JOIN users i ON c.instructor_id = i.id
              WHERE cert.certificate_code = :code";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $code);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Certificate not found or invalid'], 404);
    }
    
    $certificate = $stmt->fetch();
    jsonResponse(['certificate' => $certificate, 'valid' => true]);
}

function downloadCertificate($db) {
    requireLogin();
    
    if (!isset($_GET['code'])) {
        jsonResponse(['error' => 'Certificate code required'], 400);
    }
    
    $code = $_GET['code'];
    
    // Verify certificate ownership or if user is instructor
    $query = "SELECT cert.*, c.title as course_title, c.description as course_description, c.instructor_id,
                     s.full_name as student_name, i.full_name as instructor_name
              FROM certificates cert
              JOIN courses c ON cert.course_id = c.id
              JOIN users s ON cert.student_id = s.id
              JOIN users i ON c.instructor_id = i.id
              WHERE cert.certificate_code = :code";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $code);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        jsonResponse(['error' => 'Certificate not found'], 404);
    }
    
    $certificate = $stmt->fetch();
    
    // Check if user has access to this certificate
    if ($certificate['student_id'] != $_SESSION['user_id'] && 
        $certificate['instructor_id'] != $_SESSION['user_id']) {
        jsonResponse(['error' => 'Access denied'], 403);
    }
    
    // Generate certificate HTML
    $certificateHtml = generateCertificateHTML($certificate);
    
    jsonResponse([
        'success' => true,
        'certificate_html' => $certificateHtml,
        'certificate' => $certificate
    ]);
}

function generateCertificateCode() {
    // Generate a unique certificate code
    $prefix = 'CERT';
    $timestamp = date('Ymd');
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    return $prefix . '-' . $timestamp . '-' . $random;
}

function generateCertificateHTML($certificate) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate of Completion</title>
        <style>
            body {
                font-family: "Times New Roman", serif;
                margin: 0;
                padding: 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .certificate {
                background: white;
                width: 800px;
                padding: 60px;
                border: 10px solid #2c3e50;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                position: relative;
            }
            .certificate::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
                bottom: 20px;
                border: 3px solid #3498db;
                border-radius: 10px;
            }
            .header {
                color: #2c3e50;
                font-size: 36px;
                font-weight: bold;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 3px;
            }
            .subheader {
                color: #7f8c8d;
                font-size: 18px;
                margin-bottom: 40px;
            }
            .student-name {
                color: #2c3e50;
                font-size: 48px;
                font-weight: bold;
                margin: 30px 0;
                text-decoration: underline;
                text-decoration-color: #3498db;
            }
            .completion-text {
                color: #34495e;
                font-size: 20px;
                margin: 30px 0;
                line-height: 1.6;
            }
            .course-title {
                color: #2c3e50;
                font-size: 28px;
                font-weight: bold;
                margin: 20px 0;
                font-style: italic;
            }
            .footer {
                margin-top: 50px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .signature-section {
                text-align: center;
            }
            .signature-line {
                border-top: 2px solid #2c3e50;
                width: 200px;
                margin: 10px 0;
            }
            .date, .instructor, .certificate-code {
                color: #7f8c8d;
                font-size: 14px;
            }
            .certificate-code {
                position: absolute;
                bottom: 10px;
                right: 20px;
                font-size: 12px;
            }
            .seal {
                width: 80px;
                height: 80px;
                border: 4px solid #e74c3c;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #e74c3c;
                font-weight: bold;
                font-size: 12px;
                text-align: center;
                line-height: 1.2;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">Certificate of Completion</div>
            <div class="subheader">This is to certify that</div>
            
            <div class="student-name">' . htmlspecialchars($certificate['student_name']) . '</div>
            
            <div class="completion-text">
                has successfully completed the online course
            </div>
            
            <div class="course-title">' . htmlspecialchars($certificate['course_title']) . '</div>
            
            <div class="completion-text">
                with dedication and commitment to learning
            </div>
            
            <div class="footer">
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <div class="instructor">Instructor: ' . htmlspecialchars($certificate['instructor_name']) . '</div>
                </div>
                
                <div class="seal">
                    <div>VERIFIED<br>CERTIFICATE</div>
                </div>
                
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <div class="date">Date: ' . date('F d, Y', strtotime($certificate['issued_at'])) . '</div>
                </div>
            </div>
            
            <div class="certificate-code">Certificate ID: ' . htmlspecialchars($certificate['certificate_code']) . '</div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>