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

// 작업 ID 체크
if (!isset($_GET['taskId']) || !is_numeric($_GET['taskId'])) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 작업 ID입니다.'
    ]);
    exit;
}

$taskId = intval($_GET['taskId']);

try {
    // 작업 존재 여부 확인
    $stmt = $pdo->prepare("
        SELECT id
        FROM tasks
        WHERE id = :id
    ");
    $stmt->execute(['id' => $taskId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('작업을 찾을 수 없습니다.');
    }

    // 작업 이력 조회
    $stmt = $pdo->prepare("
        SELECT 
            l.action,
            l.description,
            l.created_at,
            u.name as userName,
            u.role as userRole
        FROM task_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.task_id = :task_id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute(['task_id' => $taskId]);
    $history = $stmt->fetchAll();

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'history' => array_map(function($item) {
            // 작업 유형별 아이콘 추가
            $icons = [
                'create' => '🆕',
                'update' => '✏️',
                'status_change' => '🔄',
                'comment' => '💬'
            ];
            $item['icon'] = $icons[$item['action']] ?? '📝';
            return $item;
        }, $history)
    ];

    send_json_response($response);

} catch (Exception $e) {
    error_log("Get Task History Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 