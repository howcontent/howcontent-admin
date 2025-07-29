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

// 필수 필드 체크
$requiredFields = [
    'taskTitle', 'assignedTo', 'startDate', 'endDate', 'status'
];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        send_json_response([
            'success' => false,
            'message' => '필수 정보가 누락되었습니다.'
        ]);
        exit;
    }
}

// 입력값 정리
$data = [
    'title' => sanitize_input($_POST['taskTitle']),
    'assigned_to' => intval($_POST['assignedTo']),
    'start_date' => sanitize_input($_POST['startDate']),
    'end_date' => sanitize_input($_POST['endDate']),
    'status' => sanitize_input($_POST['status']),
    'description' => isset($_POST['description']) ? sanitize_input($_POST['description']) : null,
    'ai_summary' => isset($_POST['aiSummary']) ? sanitize_input($_POST['aiSummary']) : null
];

// 날짜 유효성 검사
$startDate = new DateTime($data['start_date']);
$endDate = new DateTime($data['end_date']);
$today = new DateTime();

if ($startDate > $endDate) {
    send_json_response([
        'success' => false,
        'message' => '시작일이 마감일보다 늦을 수 없습니다.'
    ]);
    exit;
}

if ($startDate < $today) {
    send_json_response([
        'success' => false,
        'message' => '시작일은 오늘 이후여야 합니다.'
    ]);
    exit;
}

// 상태 유효성 검사
$validStatuses = ['pending', 'in_progress', 'review', 'completed'];
if (!in_array($data['status'], $validStatuses)) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 상태입니다.'
    ]);
    exit;
}

try {
    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 담당자 존재 여부 확인
    $stmt = $pdo->prepare("
        SELECT id, name, role
        FROM users
        WHERE id = :id AND role IN ('admin', 'staff')
    ");
    $stmt->execute(['id' => $data['assigned_to']]);
    $assignee = $stmt->fetch();

    if (!$assignee) {
        throw new Exception('유효하지 않은 담당자입니다.');
    }

    // 작업 ID가 있는 경우 수정, 없는 경우 생성
    if (isset($_POST['taskId'])) {
        $taskId = intval($_POST['taskId']);

        // 작업 존재 여부 확인
        $stmt = $pdo->prepare("
            SELECT t.id, t.status, t.assigned_to, e.status as estimate_status
            FROM tasks t
            LEFT JOIN estimates e ON t.estimate_id = e.id
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            throw new Exception('작업을 찾을 수 없습니다.');
        }

        // 완료된 견적의 작업은 수정 불가
        if ($task['estimate_status'] === 'completed') {
            throw new Exception('완료된 견적의 작업은 수정할 수 없습니다.');
        }

        // 작업 수정
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET 
                title = :title,
                assigned_to = :assigned_to,
                start_date = :start_date,
                end_date = :end_date,
                status = :status,
                description = :description,
                ai_summary = :ai_summary,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute(array_merge($data, ['id' => $taskId]));

        // 작업 로그 기록
        $user = $session->get_user();
        $changes = [];

        if ($task['status'] !== $data['status']) {
            $changes[] = "상태가 '{$task['status']}'에서 '{$data['status']}'로 변경";
        }
        if ($task['assigned_to'] !== $data['assigned_to']) {
            $changes[] = "담당자가 변경";
        }

        if (!empty($changes)) {
            $stmt = $pdo->prepare("
                INSERT INTO task_logs (
                    task_id,
                    user_id,
                    action,
                    description
                ) VALUES (
                    :task_id,
                    :user_id,
                    'update',
                    :description
                )
            ");
            $stmt->execute([
                'task_id' => $taskId,
                'user_id' => $user['id'],
                'description' => implode(', ', $changes) . "되었습니다."
            ]);
        }

        // 담당자가 변경된 경우 알림 생성
        if ($task['assigned_to'] !== $data['assigned_to']) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    content
                ) VALUES (
                    :user_id,
                    'task_assigned',
                    '작업 배정',
                    :content
                )
            ");
            $stmt->execute([
                'user_id' => $data['assigned_to'],
                'content' => "작업 '{$data['title']}'이(가) 배정되었습니다."
            ]);
        }

    } else {
        // 작업 생성
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                title,
                assigned_to,
                start_date,
                end_date,
                status,
                description,
                ai_summary
            ) VALUES (
                :title,
                :assigned_to,
                :start_date,
                :end_date,
                :status,
                :description,
                :ai_summary
            )
        ");

        $stmt->execute($data);
        $taskId = $pdo->lastInsertId();

        // 작업 로그 기록
        $user = $session->get_user();
        $stmt = $pdo->prepare("
            INSERT INTO task_logs (
                task_id,
                user_id,
                action,
                description
            ) VALUES (
                :task_id,
                :user_id,
                'create',
                '작업이 생성되었습니다.'
            )
        ");
        $stmt->execute([
            'task_id' => $taskId,
            'user_id' => $user['id']
        ]);

        // 담당자에게 알림 생성
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                content
            ) VALUES (
                :user_id,
                'task_assigned',
                '새로운 작업',
                :content
            )
        ");
        $stmt->execute([
            'user_id' => $data['assigned_to'],
            'content' => "새로운 작업 '{$data['title']}'이(가) 배정되었습니다."
        ]);
    }

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => isset($_POST['taskId']) ? '작업이 수정되었습니다.' : '작업이 생성되었습니다.',
        'taskId' => $taskId
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Process Task Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 