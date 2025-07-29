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
    if (empty($_POST['name']) || empty($_POST['phone'])) {
        throw new Exception('필수 항목이 누락되었습니다.');
    }
    
    // 입력값 필터링
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $company_name = filter_var($_POST['company_name'] ?? '', FILTER_SANITIZE_STRING);
    $business_number = filter_var($_POST['business_number'] ?? '', FILTER_SANITIZE_STRING);
    
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
    
    // 사용자 정보 업데이트
    $stmt = $db->prepare("
        UPDATE users 
        SET name = ?,
            phone = ?,
            company_name = ?,
            business_number = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $name,
        $phone,
        $company_name ?: null,
        $business_number ?: null,
        $user['id']
    ]);
    
    // 세션 정보 업데이트
    $session->login([
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $name,
        'role' => $user['role']
    ]);
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '프로필이 성공적으로 저장되었습니다.'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("프로필 업데이트 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 