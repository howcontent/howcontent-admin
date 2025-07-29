<?php
// 에러 리포팅 설정
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 세션 설정
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();

// 데이터베이스 설정
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'howcontent_admin');

// OpenAI API 설정
define('OPENAI_API_KEY', 'your_openai_api_key');

// 기본 상수 정의
define('SITE_URL', 'https://your-domain.com');
define('ADMIN_EMAIL', 'admin@howcontent.com');

// 데이터베이스 연결 함수
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch (PDOException $e) {
            error_log("데이터베이스 연결 실패: " . $e->getMessage());
            die("서비스에 일시적인 문제가 발생했습니다. 잠시 후 다시 시도해주세요.");
        }
    }
    
    return $db;
}

// XSS 방지 함수
function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// CSRF 토큰 생성
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF 토큰 검증
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("유효하지 않은 요청입니다.");
    }
}

// 견적서 코드 생성
function generateEstimateCode() {
    return substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 3)), 0, 8);
}

// 로그인 체크
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 관리자 체크
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// 알림 생성
function createNotification($userId, $type, $content) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, content) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $type, $content]);
}

// 작업 로그 기록
function logTaskAction($taskId, $userId, $action, $description = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO task_logs (task_id, user_id, action, description) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$taskId, $userId, $action, $description]);
} 