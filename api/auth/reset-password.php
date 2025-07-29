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
    if (empty($_POST['token']) || empty($_POST['password']) || empty($_POST['password_confirm'])) {
        throw new Exception('필수 항목이 누락되었습니다.');
    }

    $token = $_POST['token'];
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // 비밀번호 유효성 검사
    if (strlen($password) < 8) {
        throw new Exception('비밀번호는 8자 이상이어야 합니다.');
    }

    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $password)) {
        throw new Exception('비밀번호는 영문, 숫자, 특수문자를 모두 포함해야 합니다.');
    }

    // 비밀번호 일치 확인
    if ($password !== $password_confirm) {
        throw new Exception('비밀번호가 일치하지 않습니다.');
    }

    // 데이터베이스 연결
    $db = getDB();

    // 토큰 유효성 검사
    $stmt = $db->prepare("
        SELECT pr.*, u.email, u.name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? 
        AND pr.used = 0
        AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        throw new Exception('유효하지 않거나 만료된 토큰입니다.');
    }

    // 비밀번호 해시화
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 트랜잭션 시작
    $db->beginTransaction();

    try {
        // 비밀번호 업데이트
        $stmt = $db->prepare("
            UPDATE users 
            SET password = ?, 
                password_changed_at = NOW(),
                login_attempts = 0,
                is_locked = 0,
                locked_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$password_hash, $reset['user_id']]);

        // 토큰 사용 처리
        $stmt = $db->prepare("
            UPDATE password_resets 
            SET used = 1, 
                used_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reset['id']]);

        // 비밀번호 변경 알림 이메일 발송
        sendPasswordChangedEmail($reset['email'], $reset['name']);

        // 트랜잭션 커밋
        $db->commit();

        // 성공 응답
        echo json_encode([
            'success' => true,
            'message' => '비밀번호가 성공적으로 변경되었습니다.'
        ]);

    } catch (Exception $e) {
        // 트랜잭션 롤백
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // 에러 로깅
    error_log("비밀번호 재설정 처리 중 오류 발생: " . $e->getMessage());
    
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
            <p>지금 바로 로그인하실 수 있습니다:</p>
            <p><a href='" . SITE_URL . "/login.html' style='display: inline-block; padding: 10px 20px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>로그인하기</a></p>
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