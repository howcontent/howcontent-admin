<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

try {
    $session = Session::getInstance();
    
    // 현재 사용자 정보 가져오기
    $current_user = $session->getCurrentUser();
    
    if ($current_user) {
        // 자동 로그인 토큰 삭제
        if (isset($_COOKIE['remember_token'])) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
            
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        // 로그아웃 기록 저장
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_logs (
                user_id, 
                action, 
                ip_address, 
                user_agent
            ) VALUES (
                ?, 
                'logout',
                ?,
                ?
            )
        ");
        $stmt->execute([
            $current_user['id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // 세션 파기
        $session->logout();
    }
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '로그아웃되었습니다.',
        'redirect_url' => '/login.html'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("로그아웃 처리 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 