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
    
    // 요약 정보 조회
    $summary = [
        'estimate_count' => 0,
        'active_task_count' => 0,
        'completed_task_count' => 0,
        'unread_notification_count' => 0
    ];
    
    // 견적 요청 수
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM estimates 
        WHERE client_id = ?
    ");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $summary['estimate_count'] = (int)$result['count'];
    
    // 진행중인 작업 수
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM tasks t
        JOIN estimates e ON t.estimate_id = e.id
        WHERE e.client_id = ? 
        AND t.status IN ('pending', 'in_progress', 'review')
    ");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $summary['active_task_count'] = (int)$result['count'];
    
    // 완료된 작업 수
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM tasks t
        JOIN estimates e ON t.estimate_id = e.id
        WHERE e.client_id = ? 
        AND t.status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $summary['completed_task_count'] = (int)$result['count'];
    
    // 읽지 않은 알림 수
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? 
        AND is_read = 0
    ");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $summary['unread_notification_count'] = (int)$result['count'];
    
    // 최근 견적 요청 목록
    $stmt = $db->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM tasks WHERE estimate_id = e.id) as task_count
        FROM estimates e
        WHERE e.client_id = ?
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recent_estimates = $stmt->fetchAll();
    
    // 진행중인 작업 목록
    $stmt = $db->prepare("
        SELECT t.*, 
               e.code as estimate_code,
               u.name as assigned_to_name
        FROM tasks t
        JOIN estimates e ON t.estimate_id = e.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE e.client_id = ?
        AND t.status IN ('pending', 'in_progress', 'review')
        ORDER BY 
            CASE t.status
                WHEN 'in_progress' THEN 1
                WHEN 'review' THEN 2
                WHEN 'pending' THEN 3
            END,
            t.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $active_tasks = $stmt->fetchAll();
    
    // 최근 알림 목록
    $stmt = $db->prepare("
        SELECT n.*
        FROM notifications n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recent_notifications = $stmt->fetchAll();
    
    // 읽지 않은 알림을 읽음 처리
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? 
        AND is_read = 0
    ");
    $stmt->execute([$user['id']]);
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'recent_estimates' => $recent_estimates,
        'active_tasks' => $active_tasks,
        'recent_notifications' => $recent_notifications
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("대시보드 데이터 조회 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 