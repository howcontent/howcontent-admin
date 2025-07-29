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
    $session = Session::getInstance();
    
    // 세션 체크
    if (!$session->isValid()) {
        throw new Exception('세션이 만료되었습니다.');
    }
    
    // 현재 사용자 정보
    $user = $session->getCurrentUser();
    
    // 필수 필드 검증
    if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['new_password_confirm'])) {
        throw new Exception('필수 항목이 누락되었습니다.');
    }
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $new_password_confirm = $_POST['new_password_confirm'];
    
    // 새 비밀번호 유효성 검사
    if (strlen($new_password) < 8) {
        throw new Exception('비밀번호는 8자 이상이어야 합니다.');
    }
    
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $new_password)) {
        throw new Exception('비밀번호는 영문, 숫자, 특수문자를 모두 포함해야 합니다.');
    }
    
    // 새 비밀번호 일치 확인
    if ($new_password !== $new_password_confirm) {
        throw new Exception('새 비밀번호가 일치하지 않습니다.');
    }
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 현재 비밀번호 확인
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $current_user = $stmt->fetch();
    
    if (!password_verify($current_password, $current_user['password'])) {
        throw new Exception('현재 비밀번호가 일치하지 않습니다.');
    }
    
    // 새 비밀번호가 이전 비밀번호와 동일한지 확인
    if (password_verify($new_password, $current_user['password'])) {
        throw new Exception('새 비밀번호는 현재 비밀번호와 달라야 합니다.');
    }
    
    // 비밀번호 해시화
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 비밀번호 업데이트
    $stmt = $db->prepare("
        UPDATE users 
        SET password = ?,
            password_changed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$password_hash, $user['id']]);
    
    // 다른 기기의 자동 로그인 토큰 삭제
    $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // 비밀번호 변경 알림 이메일 발송
    sendPasswordChangedEmail($user['email'], $user['name']);
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '비밀번호가 성공적으로 변경되었습니다.'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("비밀번호 변경 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 비밀번호 변경 알림 이메일 발송
 */
function sendPasswordChangedEmail($email, $name) {
    $to = $email;
    $subject = "HowContent Admin 비밀번호 변경 알림";
    
    $message = "
    <html>
    <head>
        <title>비밀번호 변경 알림</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>안녕하세요, {$name}님!</h2>
            <p>회원님의 비밀번호가 성공적으로 변경되었습니다.</p>
            <p>본인이 변경하지 않았다면 즉시 고객센터로 연락해주세요.</p>
            <br>
            <p>문의사항이 있으시면 언제든 연락주세요:</p>
            <p>이메일: " . ADMIN_EMAIL . "</p>
            <p>감사합니다.</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: HowContent Admin <" . ADMIN_EMAIL . ">\r\n";
    
    mail($to, $subject, $message, $headers);
} 