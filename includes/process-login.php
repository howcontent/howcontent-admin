<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

// AJAX 요청 확인
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// 이메일 유효성 검사
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// 비밀번호 검증
function verify_password($input_password, $stored_password) {
    return hash('sha256', $input_password) === $stored_password;
}

// JSON 응답 전송
function send_json_response($data) {
    echo json_encode($data);
    exit;
}

try {
    // POST 데이터 검증
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
    $remember = filter_input(INPUT_POST, 'remember', FILTER_VALIDATE_BOOLEAN);

    if (!$email || !$password) {
        send_json_response([
            'success' => false,
            'message' => '이메일과 비밀번호를 모두 입력해주세요.'
        ]);
    }

    if (!validate_email($email)) {
        send_json_response([
            'success' => false,
            'message' => '유효하지 않은 이메일 주소입니다.'
        ]);
    }

    // 사용자 조회
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !verify_password($password, $user['password'])) {
        send_json_response([
            'success' => false,
            'message' => '이메일 또는 비밀번호가 일치하지 않습니다.'
        ]);
    }

    // 세션 생성
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // 자동 로그인 처리
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires]);
        
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
    }

    // 리다이렉트 URL 결정
    $redirect = '/admin/';
    if ($user['role'] === 'client') {
        $redirect = '/user/';
    }

    send_json_response([
        'success' => true,
        'message' => '로그인되었습니다.',
        'redirect' => $redirect
    ]);

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '로그인 처리 중 오류가 발생했습니다.'
    ]);
} 