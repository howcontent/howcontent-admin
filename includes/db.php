<?php
// 데이터베이스 연결 설정
define('DB_HOST', 'localhost');
define('DB_NAME', 'howcontent_admin');
define('DB_USER', 'root');
define('DB_PASS', ''); // MySQL Workbench에서 설정한 root 비밀번호로 변경해주세요
define('DB_CHARSET', 'utf8mb4');

// PDO 옵션
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    // 에러 로깅
    error_log("Database Connection Error: " . $e->getMessage());
    
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '데이터베이스 연결에 실패했습니다.']);
    } else {
        echo "데이터베이스 연결에 실패했습니다.";
    }
    exit;
}

// 유틸리티 함수
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function hash_password($password) {
    return hash('sha256', $password);
}

function verify_password($password, $hash) {
    return hash('sha256', $password) === $hash;
}

function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function format_phone_number($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if(strlen($phone) === 11) {
        return substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
    } else if(strlen($phone) === 10) {
        return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

function format_business_number($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    if(strlen($number) === 10) {
        return substr($number, 0, 3) . '-' . substr($number, 3, 2) . '-' . substr($number, 5);
    }
    return $number;
}

function format_price($price) {
    return number_format($price) . '원';
}

function send_json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    return preg_match('/^[0-9]{2,3}-[0-9]{3,4}-[0-9]{4}$/', $phone);
}

function validate_business_number($number) {
    return preg_match('/^[0-9]{3}-[0-9]{2}-[0-9]{5}$/', $number);
}

function validate_password($password) {
    // 최소 8자, 최대 72자
    return strlen($password) >= 8 && strlen($password) <= 72;
}

function create_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// XSS 방어
function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
} 