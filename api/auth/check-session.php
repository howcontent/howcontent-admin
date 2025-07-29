<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');

try {
    $session = Session::getInstance();
    
    // 세션 유효성 검사
    if (!$session->isValid()) {
        // 자동 로그인 토큰 확인
        if (isset($_COOKIE['remember_token'])) {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT rt.*, u.* 
                FROM remember_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token = ? 
                AND rt.expires_at > NOW()
            ");
            $stmt->execute([$_COOKIE['remember_token']]);
            $token = $stmt->fetch();
            
            if ($token) {
                // 세션 갱신
                $session->login([
                    'id' => $token['user_id'],
                    'email' => $token['email'],
                    'name' => $token['name'],
                    'role' => $token['role']
                ]);
                
                // 토큰 갱신
                $new_token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $db->prepare("
                    UPDATE remember_tokens 
                    SET token = ?, expires_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_token, $expires, $token['id']]);
                
                setcookie('remember_token', $new_token, strtotime('+30 days'), '/', '', true, true);
            } else {
                throw new Exception('세션이 만료되었습니다.');
            }
        } else {
            throw new Exception('세션이 만료되었습니다.');
        }
    }
    
    // 현재 사용자 정보 가져오기
    $user = $session->getCurrentUser();
    
    if (!$user) {
        throw new Exception('사용자 정보를 찾을 수 없습니다.');
    }
    
    // 데이터베이스에서 추가 정보 조회
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND is_read = 0) as unread_notifications
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
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'user' => $user_data
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("세션 체크 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 