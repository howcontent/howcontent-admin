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
    
    // 이메일 알림 설정 값
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 트랜잭션 시작
    $db->beginTransaction();
    
    try {
        // 기존 설정 삭제
        $stmt = $db->prepare("
            DELETE FROM user_settings 
            WHERE user_id = ? 
            AND setting_key = 'email_notifications'
        ");
        $stmt->execute([$user['id']]);
        
        // 새 설정 추가
        $stmt = $db->prepare("
            INSERT INTO user_settings (
                user_id, 
                setting_key, 
                value, 
                created_at
            ) VALUES (
                ?, 
                'email_notifications', 
                ?, 
                NOW()
            )
        ");
        $stmt->execute([$user['id'], $email_notifications]);
        
        // 트랜잭션 커밋
        $db->commit();
        
        // 성공 응답
        echo json_encode([
            'success' => true,
            'message' => '알림 설정이 저장되었습니다.'
        ]);
        
    } catch (Exception $e) {
        // 트랜잭션 롤백
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // 에러 로깅
    error_log("알림 설정 업데이트 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 