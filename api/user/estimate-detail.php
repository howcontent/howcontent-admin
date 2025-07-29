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
    
    // 견적 코드 검증
    if (empty($_GET['code'])) {
        throw new Exception('견적 코드가 누락되었습니다.');
    }
    
    $code = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 견적 정보 조회
    $stmt = $db->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM tasks WHERE estimate_id = e.id) as task_count,
               (SELECT COUNT(*) FROM tasks WHERE estimate_id = e.id AND status = 'completed') as completed_task_count
        FROM estimates e
        WHERE e.code = ?
        AND e.client_id = ?
    ");
    $stmt->execute([$code, $user['id']]);
    $estimate = $stmt->fetch();
    
    if (!$estimate) {
        throw new Exception('견적 정보를 찾을 수 없습니다.');
    }
    
    // 진행률 계산
    if ($estimate['task_count'] > 0) {
        $estimate['progress'] = round(($estimate['completed_task_count'] / $estimate['task_count']) * 100);
    } else {
        $estimate['progress'] = 0;
    }
    
    // 작업 목록 조회
    $stmt = $db->prepare("
        SELECT t.*,
               u.name as assigned_to_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.estimate_id = ?
        ORDER BY 
            CASE t.status
                WHEN 'in_progress' THEN 1
                WHEN 'review' THEN 2
                WHEN 'pending' THEN 3
                WHEN 'completed' THEN 4
            END,
            t.updated_at DESC
    ");
    $stmt->execute([$estimate['id']]);
    $tasks = $stmt->fetchAll();
    
    // 댓글 목록 조회
    $stmt = $db->prepare("
        SELECT c.*,
               u.name as user_name,
               u.role as user_role
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.task_id IN (SELECT id FROM tasks WHERE estimate_id = ?)
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$estimate['id']]);
    $comments = $stmt->fetchAll();
    
    // 민감한 정보 제거
    unset($estimate['client_id']);
    foreach ($tasks as &$task) {
        unset($task['estimate_id']);
    }
    foreach ($comments as &$comment) {
        unset($comment['user_id']);
    }
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'estimate' => $estimate,
        'tasks' => $tasks,
        'comments' => $comments
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("견적 상세 정보 조회 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 