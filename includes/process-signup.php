<?php
require_once 'session.php';

// AJAX 요청 확인
if (!is_ajax_request()) {
    die("잘못된 접근입니다.");
}

// POST 데이터 검증
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
$passwordConfirm = filter_input(INPUT_POST, 'passwordConfirm', FILTER_UNSAFE_RAW);
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$businessName = filter_input(INPUT_POST, 'businessName', FILTER_SANITIZE_STRING);
$businessNumber = filter_input(INPUT_POST, 'businessNumber', FILTER_SANITIZE_STRING);
$termsAgreed = filter_input(INPUT_POST, 'termsAgreed', FILTER_VALIDATE_BOOLEAN);

// 필수 필드 확인
if (!$email || !$password || !$passwordConfirm || !$name || !$phone || !$businessName || !$businessNumber) {
    send_json_response([
        'success' => false,
        'message' => '모든 필수 항목을 입력해주세요.'
    ]);
}

// 이용약관 동의 확인
if (!$termsAgreed) {
    send_json_response([
        'success' => false,
        'message' => '이용약관에 동의해주세요.'
    ]);
}

// 이메일 형식 검증
if (!validate_email($email)) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 이메일 주소입니다.'
    ]);
}

// 비밀번호 일치 확인
if ($password !== $passwordConfirm) {
    send_json_response([
        'success' => false,
        'message' => '비밀번호가 일치하지 않습니다.'
    ]);
}

// 비밀번호 복잡도 검증
if (!validate_password($password)) {
    send_json_response([
        'success' => false,
        'message' => '비밀번호는 8자 이상이어야 합니다.'
    ]);
}

// 전화번호 형식 검증
if (!validate_phone($phone)) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 전화번호 형식입니다.'
    ]);
}

// 사업자등록번호 형식 검증
if (!validate_business_number($businessNumber)) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 사업자등록번호 형식입니다.'
    ]);
}

try {
    // 이메일 중복 확인
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        send_json_response([
            'success' => false,
            'message' => '이미 사용 중인 이메일 주소입니다.'
        ]);
    }

    // 사업자등록번호 중복 확인
    $stmt = $pdo->prepare("SELECT id FROM users WHERE business_number = ?");
    $stmt->execute([$businessNumber]);
    if ($stmt->fetch()) {
        send_json_response([
            'success' => false,
            'message' => '이미 등록된 사업자등록번호입니다.'
        ]);
    }

    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 사용자 등록
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password, name, phone, business_name, business_number, role) 
         VALUES (?, ?, ?, ?, ?, ?, 'client')"
    );
    $stmt->execute([
        $email,
        hash_password($password),
        $name,
        format_phone_number($phone),
        $businessName,
        format_business_number($businessNumber)
    ]);

    $userId = $pdo->lastInsertId();

    // 트랜잭션 커밋
    $pdo->commit();

    // 자동 로그인
    $session->create_session($userId);

    send_json_response([
        'success' => true,
        'message' => '회원가입이 완료되었습니다.',
        'redirect' => '/user/'
    ]);

} catch (PDOException $e) {
    // 트랜잭션 롤백
    $pdo->rollBack();
    
    error_log("Signup Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '회원가입 처리 중 오류가 발생했습니다.'
    ]);
} 