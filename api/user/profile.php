<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');

try {
    $session = Session::getInstance();
    
    // 세션 체크
    if (!$session->isValid()) {
        throw new Exception('세션이 만료되었습니다.');
    }
    
    // 현재 사용자 정보
    $user = $session->getCurrentUser();
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 사용자 정보 조회
    $stmt = $db->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND is_read = 0) as unread_notifications,
               (SELECT value FROM user_settings WHERE user_id = u.id AND setting_key = 'email_notifications') as email_notifications
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user['id']]);
    $user_data = $stmt->fetch();
    
    // 민감한 정보 제거
    unset($user_data['password']);
    unset($user_data['login_attempts']);
    unset($user_data['is_locked']);
    unset($user_data['locked_until']);
    
    // 이메일 알림 설정이 없는 경우 기본값 true
    if ($user_data['email_notifications'] === null) {
        $user_data['email_notifications'] = true;
    } else {
        $user_data['email_notifications'] = (bool)$user_data['email_notifications'];
    }
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'user' => $user_data
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("프로필 정보 조회 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 