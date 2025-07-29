<?php
require_once 'db.php';
require_once 'session.php';

// 세션 체크
$session = new SessionManager($pdo);
$session->require_login();

// AJAX 요청 체크
if (!is_ajax_request()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}

try {
    $user = $session->get_user();
    
    // 사용자의 알림 가져오기
    $stmt = $pdo->prepare("
        SELECT 
            id,
            type,
            title,
            content,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $notifications = $stmt->fetchAll();

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'notifications' => $notifications
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '알림을 불러오는 중 오류가 발생했습니다.'
    ]);
} 