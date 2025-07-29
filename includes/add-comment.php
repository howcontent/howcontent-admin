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
if (!isset($_POST['taskId']) || !isset($_POST['commentText']) || empty($_POST['commentText'])) {
    send_json_response([
        'success' => false,
        'message' => '필수 정보가 누락되었습니다.'
    ]);
    exit;
}

$taskId = intval($_POST['taskId']);
$content = sanitize_input($_POST['commentText']);
$user = $session->get_user();

try {
    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 작업 존재 여부 및 상태 확인
    $stmt = $pdo->prepare("
        SELECT t.id, t.assigned_to, e.status as estimate_status
        FROM tasks t
        LEFT JOIN estimates e ON t.estimate_id = e.id
        WHERE t.id = :id
    ");
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        throw new Exception('작업을 찾을 수 없습니다.');
    }

    // 완료된 견적의 작업은 댓글 작성 불가
    if ($task['estimate_status'] === 'completed') {
        throw new Exception('완료된 견적의 작업에는 댓글을 작성할 수 없습니다.');
    }

    // 댓글 작성
    $stmt = $pdo->prepare("
        INSERT INTO comments (
            task_id,
            user_id,
            content
        ) VALUES (
            :task_id,
            :user_id,
            :content
        )
    ");
    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => $user['id'],
        'content' => $content
    ]);

    // 작업 로그 기록
    $stmt = $pdo->prepare("
        INSERT INTO task_logs (
            task_id,
            user_id,
            action,
            description
        ) VALUES (
            :task_id,
            :user_id,
            'comment',
            '새로운 댓글이 작성되었습니다.'
        )
    ");
    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => $user['id']
    ]);

    // 담당자에게 알림 생성 (작성자가 담당자가 아닌 경우)
    if ($task['assigned_to'] && $task['assigned_to'] !== $user['id']) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                content
            ) VALUES (
                :user_id,
                'new_comment',
                '새로운 댓글',
                :content
            )
        ");
        $stmt->execute([
            'user_id' => $task['assigned_to'],
            'content' => "{$user['name']}님이 작업에 새로운 댓글을 작성했습니다."
        ]);
    }

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => '댓글이 작성되었습니다.'
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Add Comment Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 