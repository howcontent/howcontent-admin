<?php
require_once 'session.php';

// AJAX 요청 확인
if (!is_ajax_request()) {
    die("잘못된 접근입니다.");
}

// 견적 코드 검증
$code = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);

if (!$code) {
    send_json_response([
        'success' => false,
        'message' => '견적 코드가 필요합니다.'
    ]);
}

try {
    // 견적서 조회
    $stmt = $pdo->prepare(
        "SELECT e.*, t.status as task_status, t.assigned_to 
         FROM estimates e 
         LEFT JOIN tasks t ON t.estimate_id = e.id 
         WHERE e.code = ?"
    );
    $stmt->execute([$code]);
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
        $requestEmail = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
        $requestPhone = filter_input(INPUT_GET, 'phone', FILTER_SANITIZE_STRING);

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

    // 담당자 정보 조회
    $assignedTo = null;
    if ($estimate['assigned_to']) {
        $stmt = $pdo->prepare(
            "SELECT name, email, phone 
             FROM users 
             WHERE id = ?"
        );
        $stmt->execute([$estimate['assigned_to']]);
        $assignedTo = $stmt->fetch();
    }

    // 견적서 데이터 가공
    $result = [
        'code' => $estimate['code'],
        'requestDate' => $estimate['created_at'],
        'customerName' => $estimate['name'],
        'companyName' => $estimate['company'],
        'workType' => $estimate['work_type'],
        'budget' => (int)$estimate['budget'],
        'deadline' => $estimate['deadline'],
        'requirements' => $estimate['requirements'],
        'basePrice' => (int)$estimate['base_price'],
        'designPrice' => (int)$estimate['design_price'],
        'optionPrice' => (int)$estimate['option_price'],
        'totalPrice' => (int)$estimate['total_price'],
        'notes' => $estimate['notes'],
        'status' => $estimate['status'],
        'taskStatus' => $estimate['task_status'],
        'assignedTo' => $assignedTo
    ];

    // 관리자/스태프인 경우 추가 정보 제공
    if ($session->is_staff()) {
        $result['email'] = $estimate['email'];
        $result['phone'] = $estimate['phone'];
        $result['reference'] = $estimate['reference'];
        $result['needDesign'] = (bool)$estimate['need_design'];
    }

    send_json_response([
        'success' => true,
        'estimate' => $result
    ]);

} catch (PDOException $e) {
    error_log("Get Estimate Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '견적서 조회 중 오류가 발생했습니다.'
    ]);
} 