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

// 파라미터 가져오기
$status = isset($_GET['status']) ? $_GET['status'] : '';
$assignee = isset($_GET['assignee']) ? $_GET['assignee'] : '';
$dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // 기본 WHERE 절
    $where = [];
    $params = [];

    // 상태 필터
    if (!empty($status)) {
        $where[] = "t.status = :status";
        $params['status'] = $status;
    }

    // 담당자 필터
    if (!empty($assignee)) {
        $where[] = "t.assigned_to = :assignee";
        $params['assignee'] = $assignee;
    }

    // 날짜 범위 필터
    $now = new DateTime();
    $start = new DateTime();

    switch ($dateRange) {
        case 'today':
            $start->setTime(0, 0, 0);
            $where[] = "t.start_date >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'week':
            $start->modify('-1 week')->setTime(0, 0, 0);
            $where[] = "t.start_date >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'month':
            $start->modify('first day of this month')->setTime(0, 0, 0);
            $where[] = "t.start_date >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'overdue':
            $where[] = "t.end_date < CURRENT_DATE AND t.status != 'completed'";
            break;
    }

    // 검색어 필터
    if (!empty($search)) {
        $where[] = "(
            t.title LIKE :search OR
            t.description LIKE :search OR
            u.name LIKE :search
        )";
        $params['search'] = "%{$search}%";
    }

    // WHERE 절 조합
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // 작업 목록 조회
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,
            t.description,
            t.status,
            t.start_date,
            t.end_date,
            u.name as assignedTo
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        {$whereClause}
        ORDER BY t.end_date ASC
    ");

    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    // 상태별 작업 수 조회
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM tasks
        GROUP BY status
    ");
    $stmt->execute();
    $counts = [];
    while ($row = $stmt->fetch()) {
        $counts[$row['status']] = $row['count'];
    }

    // 대기중인 작업 수 조회
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tasks
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingCount = $stmt->fetch()['count'];

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'tasks' => $tasks,
        'counts' => $counts,
        'pendingCount' => $pendingCount
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '작업 목록을 불러오는 중 오류가 발생했습니다.'
    ]);
} 