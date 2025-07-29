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

// JSON 요청 데이터 파싱
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['taskId']) || !isset($data['status'])) {
    send_json_response([
        'success' => false,
        'message' => '필수 정보가 누락되었습니다.'
    ]);
    exit;
}

$taskId = intval($data['taskId']);
$status = $data['status'];

// 상태 유효성 검사
$validStatuses = ['pending', 'in_progress', 'review', 'completed'];
if (!in_array($status, $validStatuses)) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 상태입니다.'
    ]);
    exit;
}

try {
    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 작업 존재 여부 및 현재 상태 확인
    $stmt = $pdo->prepare("
        SELECT t.id, t.status, t.assigned_to, t.estimate_id, e.status as estimate_status
        FROM tasks t
        LEFT JOIN estimates e ON t.estimate_id = e.id
        WHERE t.id = :id
    ");
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        throw new Exception('작업을 찾을 수 없습니다.');
    }

    // 완료된 견적의 작업은 상태 변경 불가
    if ($task['estimate_status'] === 'completed') {
        throw new Exception('완료된 견적의 작업은 상태를 변경할 수 없습니다.');
    }

    // 작업 상태 업데이트
    $stmt = $pdo->prepare("
        UPDATE tasks
        SET 
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $taskId,
        'status' => $status
    ]);

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
            'status_change',
            :description
        )
    ");
    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => $user['id'],
        'description' => "작업 상태가 '{$task['status']}'에서 '{$status}'로 변경되었습니다."
    ]);

    // 담당자에게 알림 생성
    if ($task['assigned_to']) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                content
            ) VALUES (
                :user_id,
                'task_status_change',
                '작업 상태 변경',
                :content
            )
        ");
        $stmt->execute([
            'user_id' => $task['assigned_to'],
            'content' => "작업 '{$task['id']}'의 상태가 '{$status}'로 변경되었습니다."
        ]);
    }

    // 견적 상태 업데이트 (모든 작업이 완료된 경우)
    if ($status === 'completed' && $task['estimate_id']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM tasks
            WHERE estimate_id = :estimate_id
        ");
        $stmt->execute(['estimate_id' => $task['estimate_id']]);
        $result = $stmt->fetch();

        if ($result['total'] === $result['completed']) {
            $stmt = $pdo->prepare("
                UPDATE estimates
                SET 
                    status = 'completed',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $task['estimate_id']]);
        }
    }

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => '작업 상태가 변경되었습니다.'
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Update Task Status Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 