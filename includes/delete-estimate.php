<?php
require_once 'db.php';
require_once 'session.php';

// 세션 체크
$session = new SessionManager($pdo);
$session->require_admin();

// AJAX 요청 체크
if (!is_ajax_request()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}

// JSON 요청 데이터 파싱
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['code']) || empty($data['code'])) {
    send_json_response([
        'success' => false,
        'message' => '견적 코드가 필요합니다.'
    ]);
    exit;
}

$code = $data['code'];

try {
    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 견적서 존재 여부 확인
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM estimates
        WHERE code = :code
    ");
    $stmt->execute(['code' => $code]);
    $estimate = $stmt->fetch();

    if (!$estimate) {
        throw new Exception('견적서를 찾을 수 없습니다.');
    }

    // 완료된 견적서는 삭제 불가
    if ($estimate['status'] === 'completed') {
        throw new Exception('완료된 견적서는 삭제할 수 없습니다.');
    }

    // 관련 작업 로그 삭제
    $stmt = $pdo->prepare("
        DELETE FROM task_logs
        WHERE task_id IN (
            SELECT id FROM tasks WHERE estimate_id = :estimate_id
        )
    ");
    $stmt->execute(['estimate_id' => $estimate['id']]);

    // 관련 댓글 삭제
    $stmt = $pdo->prepare("
        DELETE FROM comments
        WHERE task_id IN (
            SELECT id FROM tasks WHERE estimate_id = :estimate_id
        )
    ");
    $stmt->execute(['estimate_id' => $estimate['id']]);

    // 관련 작업 삭제
    $stmt = $pdo->prepare("
        DELETE FROM tasks
        WHERE estimate_id = :estimate_id
    ");
    $stmt->execute(['estimate_id' => $estimate['id']]);

    // 견적서 삭제
    $stmt = $pdo->prepare("
        DELETE FROM estimates
        WHERE id = :id
    ");
    $stmt->execute(['id' => $estimate['id']]);

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => '견적서가 삭제되었습니다.'
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Delete Estimate Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 