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
    $required_fields = ['email', 'password', 'password_confirm', 'name', 'phone'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("필수 항목이 누락되었습니다: $field");
        }
    }

    // 입력값 필터링
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $company_name = filter_var($_POST['company_name'] ?? '', FILTER_SANITIZE_STRING);
    $business_number = filter_var($_POST['business_number'] ?? '', FILTER_SANITIZE_STRING);

    // 이메일 유효성 검사
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('유효하지 않은 이메일 주소입니다.');
    }

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

    // 전화번호 형식 검사
    if (!preg_match('/^[0-9]{2,3}-[0-9]{3,4}-[0-9]{4}$/', $phone)) {
        throw new Exception('유효하지 않은 전화번호 형식입니다.');
    }

    // 사업자등록번호 형식 검사 (입력된 경우)
    if (!empty($business_number) && !preg_match('/^[0-9]{3}-[0-9]{2}-[0-9]{5}$/', $business_number)) {
        throw new Exception('유효하지 않은 사업자등록번호 형식입니다.');
    }

    // 데이터베이스 연결
    $db = getDB();

    // 이메일 중복 검사
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('이미 사용 중인 이메일 주소입니다.');
    }

    // 비밀번호 해시화
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 사용자 등록
    $stmt = $db->prepare("
        INSERT INTO users (
            email, password, name, phone, 
            company_name, business_number, role,
            created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, 'client',
            NOW()
        )
    ");

    $stmt->execute([
        $email, $password_hash, $name, $phone,
        $company_name, $business_number
    ]);

    $user_id = $db->lastInsertId();

    // 가입 환영 이메일 발송
    sendWelcomeEmail($email, $name);

    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '회원가입이 완료되었습니다.'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("회원가입 처리 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 가입 환영 이메일 발송
 */
function sendWelcomeEmail($email, $name) {
    $to = $email;
    $subject = "HowContent Admin 회원가입을 환영합니다.";
    
    $message = "
    <html>
    <head>
        <title>HowContent Admin 회원가입 환영</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>안녕하세요, {$name}님!</h2>
            <p>HowContent Admin 회원가입을 진심으로 환영합니다.</p>
            <p>저희 서비스를 통해 쇼핑몰 운영의 부담을 덜어드리겠습니다.</p>
            <br>
            <p>지금 바로 로그인하여 서비스를 이용해보세요:</p>
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