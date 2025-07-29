<?php
require_once 'session.php';

// AJAX 요청 확인
if (!is_ajax_request()) {
    die("잘못된 접근입니다.");
}

// POST 데이터 검증
$estimateCode = filter_input(INPUT_POST, 'estimateCode', FILTER_SANITIZE_STRING);
$details = filter_input(INPUT_POST, 'modificationDetails', FILTER_SANITIZE_STRING);

if (!$estimateCode || !$details) {
    send_json_response([
        'success' => false,
        'message' => '필수 항목을 모두 입력해주세요.'
    ]);
}

try {
    // 견적서 조회
    $stmt = $pdo->prepare(
        "SELECT e.*, t.id as task_id, t.status as task_status 
         FROM estimates e 
         LEFT JOIN tasks t ON t.estimate_id = e.id 
         WHERE e.code = ?"
    );
    $stmt->execute([$estimateCode]);
    $estimate = $stmt->fetch();

    if (!$estimate) {
        send_json_response([
            'success' => false,
            'message' => '견적서를 찾을 수 없습니다.'
        ]);
    }

    // 접근 권한 확인
    $user = $session->get_user();
    $hasAccess = false;

    if ($user) {
        // 관리자/스태프는 모든 견적서 접근 가능
        if ($session->is_staff()) {
            $hasAccess = true;
        }
        // 본인의 견적서만 접근 가능
        else if ($estimate['user_id'] === $user['id']) {
            $hasAccess = true;
        }
    }
    // 비로그인 사용자는 견적 요청자 정보가 일치하는 경우에만 접근 가능
    else {
        $requestEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $requestPhone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

        if ($requestEmail && $requestPhone) {
            if ($estimate['email'] === $requestEmail && 
                format_phone_number($requestPhone) === $estimate['phone']) {
                $hasAccess = true;
            }
        }
    }

    if (!$hasAccess) {
        send_json_response([
            'success' => false,
            'message' => '견적서에 접근할 권한이 없습니다.'
        ]);
    }

    // 견적서 상태 확인
    if ($estimate['status'] === 'completed') {
        send_json_response([
            'success' => false,
            'message' => '이미 완료된 견적서는 수정할 수 없습니다.'
        ]);
    }

    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 견적서 상태 업데이트
    $stmt = $pdo->prepare(
        "UPDATE estimates 
         SET status = 'feedback' 
         WHERE code = ?"
    );
    $stmt->execute([$estimateCode]);

    // 작업 상태 업데이트
    if ($estimate['task_id']) {
        $stmt = $pdo->prepare(
            "UPDATE tasks 
             SET status = 'review' 
             WHERE id = ?"
        );
        $stmt->execute([$estimate['task_id']]);

        // 작업 로그 추가
        $stmt = $pdo->prepare(
            "INSERT INTO task_logs (task_id, user_id, action, description) 
             VALUES (?, ?, 'modification_requested', ?)"
        );
        $stmt->execute([
            $estimate['task_id'],
            $user ? $user['id'] : null,
            "수정 요청 사항:\n" . $details
        ]);

        // 댓글 추가
        $stmt = $pdo->prepare(
            "INSERT INTO comments (task_id, user_id, content) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $estimate['task_id'],
            $user ? $user['id'] : null,
            "[수정 요청]\n" . $details
        ]);

        // 담당자에게 알림
        if ($estimate['assigned_to']) {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, title, content) 
                 VALUES (?, 'task_feedback', '작업 수정 요청', ?)"
            );
            $stmt->execute([
                $estimate['assigned_to'],
                "견적 코드: {$estimateCode}\n" .
                "수정 요청 사항:\n" . $details
            ]);
        }
    }

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => '수정 요청이 접수되었습니다.'
    ]);

} catch (PDOException $e) {
    // 트랜잭션 롤백
    $pdo->rollBack();
    
    error_log("Modification Request Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '수정 요청 처리 중 오류가 발생했습니다.'
    ]);
} 