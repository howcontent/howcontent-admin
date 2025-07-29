<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

// POST 요청이 아닌 경우 처리
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 요청 방식입니다.']);
    exit;
}

try {
    // 필수 필드 검증
    if (empty($_POST['email']) || empty($_POST['password'])) {
        throw new Exception('이메일과 비밀번호를 모두 입력해주세요.');
    }

    // 입력값 필터링
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;

    // 이메일 유효성 검사
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('유효하지 않은 이메일 주소입니다.');
    }

    // 데이터베이스 연결
    $db = getDB();

    // 사용자 조회
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 사용자가 존재하지 않는 경우
    if (!$user) {
        throw new Exception('이메일 또는 비밀번호가 일치하지 않습니다.');
    }

    // 비밀번호 검증
    if (!password_verify($password, $user['password'])) {
        // 로그인 실패 횟수 증가
        $stmt = $db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);

        // 로그인 시도 횟수가 5회 이상인 경우
        if ($user['login_attempts'] >= 4) {
            // 계정 잠금
            $stmt = $db->prepare("UPDATE users SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?");
            $stmt->execute([$user['id']]);
            throw new Exception('로그인 시도 횟수를 초과했습니다. 30분 후에 다시 시도해주세요.');
        }

        throw new Exception('이메일 또는 비밀번호가 일치하지 않습니다.');
    }

    // 계정이 잠겨있는지 확인
    if ($user['is_locked']) {
        $locked_until = strtotime($user['locked_until']);
        if (time() < $locked_until) {
            $remaining_time = ceil(($locked_until - time()) / 60);
            throw new Exception("계정이 잠겨있습니다. {$remaining_time}분 후에 다시 시도해주세요.");
        }

        // 잠금 해제
        $stmt = $db->prepare("UPDATE users SET is_locked = 0, login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
    }

    // 로그인 성공 처리
    $session = Session::getInstance();
    $session->login($user);

    // 로그인 시도 횟수 초기화
    $stmt = $db->prepare("UPDATE users SET login_attempts = 0 WHERE id = ?");
    $stmt->execute([$user['id']]);

    // 로그인 기록 저장
    $stmt = $db->prepare("
        INSERT INTO login_logs (user_id, ip_address, user_agent) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    // 자동 로그인 처리
    if ($remember_me) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $db->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $token, $expires]);

        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
    }

    // 리다이렉트 URL 결정
    $redirect_url = '/user/index.html';
    if ($user['role'] === 'admin') {
        $redirect_url = '/admin/index.html';
    } elseif ($user['role'] === 'team') {
        $redirect_url = '/team/index.html';
    }

    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '로그인에 성공했습니다.',
        'redirect_url' => $redirect_url
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("로그인 처리 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 