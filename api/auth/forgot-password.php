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
    // 이메일 주소 검증
    if (empty($_POST['email'])) {
        throw new Exception('이메일 주소를 입력해주세요.');
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('유효하지 않은 이메일 주소입니다.');
    }

    // 데이터베이스 연결
    $db = getDB();

    // 사용자 조회
    $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // 보안을 위해 사용자가 없어도 성공 메시지 반환
        echo json_encode([
            'success' => true,
            'message' => '비밀번호 재설정 링크가 이메일로 발송되었습니다.'
        ]);
        exit;
    }

    // 기존 미사용 토큰 삭제
    $stmt = $db->prepare("
        DELETE FROM password_resets 
        WHERE user_id = ? AND used = 0
    ");
    $stmt->execute([$user['id']]);

    // 새 토큰 생성
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 토큰 저장
    $stmt = $db->prepare("
        INSERT INTO password_resets (
            user_id, token, expires_at, created_at
        ) VALUES (
            ?, ?, ?, NOW()
        )
    ");
    $stmt->execute([$user['id'], $token, $expires]);

    // 비밀번호 재설정 이메일 발송
    sendPasswordResetEmail($email, $user['name'], $token);

    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '비밀번호 재설정 링크가 이메일로 발송되었습니다.'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("비밀번호 찾기 처리 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 비밀번호 재설정 이메일 발송
 */
function sendPasswordResetEmail($email, $name, $token) {
    $reset_url = SITE_URL . '/reset-password.html?token=' . $token;
    
    $to = $email;
    $subject = "HowContent Admin 비밀번호 재설정";
    
    $message = "
    <html>
    <head>
        <title>비밀번호 재설정</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>안녕하세요, {$name}님!</h2>
            <p>비밀번호 재설정을 요청하셨습니다.</p>
            <p>아래 링크를 클릭하여 새로운 비밀번호를 설정해주세요:</p>
            <p><a href='{$reset_url}' style='display: inline-block; padding: 10px 20px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>비밀번호 재설정</a></p>
            <p>이 링크는 1시간 동안만 유효합니다.</p>
            <br>
            <p>비밀번호 재설정을 요청하지 않으셨다면 이 이메일을 무시하시면 됩니다.</p>
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