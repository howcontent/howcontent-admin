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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 작업 ID입니다.'
    ]);
    exit;
}

$taskId = intval($_GET['id']);

try {
    // 작업 정보 조회
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,
            t.description,
            t.status,
            t.start_date,
            t.end_date,
            u.name as assignedTo,
            e.code as estimateCode,
            e.name as customerName,
            e.email as customerEmail,
            e.phone as customerPhone
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN estimates e ON t.estimate_id = e.id
        WHERE t.id = :id
    ");
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        send_json_response([
            'success' => false,
            'message' => '작업을 찾을 수 없습니다.'
        ]);
        exit;
    }

    // 작업 로그 조회
    $stmt = $pdo->prepare("
        SELECT 
            l.action,
            l.description,
            l.created_at,
            u.name as userName
        FROM task_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.task_id = :task_id
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(['task_id' => $taskId]);
    $logs = $stmt->fetchAll();

    // 댓글 조회
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.content,
            c.created_at,
            u.name as userName
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.task_id = :task_id
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(['task_id' => $taskId]);
    $comments = $stmt->fetchAll();

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'task' => [
            'id' => $task['id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'status' => $task['status'],
            'startDate' => $task['start_date'],
            'endDate' => $task['end_date'],
            'assignedTo' => $task['assignedTo'],
            'estimate' => [
                'code' => $task['estimateCode'],
                'customerName' => $task['customerName'],
                'customerEmail' => $task['customerEmail'],
                'customerPhone' => $task['customerPhone']
            ]
        ],
        'logs' => $logs,
        'comments' => $comments
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '작업 정보를 불러오는 중 오류가 발생했습니다.'
    ]);
} 