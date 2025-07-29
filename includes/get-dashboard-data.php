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

// 날짜 범위 설정
$range = isset($_GET['range']) ? $_GET['range'] : 'month';
$now = new DateTime();
$start = new DateTime();

switch ($range) {
    case 'today':
        $start->setTime(0, 0, 0);
        break;
    case 'week':
        $start->modify('-1 week')->setTime(0, 0, 0);
        break;
    case 'month':
        $start->modify('first day of this month')->setTime(0, 0, 0);
        break;
    case 'year':
        $start->modify('first day of january this year')->setTime(0, 0, 0);
        break;
    default:
        $start->modify('first day of this month')->setTime(0, 0, 0);
}

try {
    // 신규 견적 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM estimates
        WHERE created_at >= :start
    ");
    $stmt->execute(['start' => $start->format('Y-m-d H:i:s')]);
    $newEstimates = $stmt->fetch()['count'];

    // 진행중인 작업 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tasks
        WHERE status = 'in_progress'
    ");
    $stmt->execute();
    $activeTasks = $stmt->fetch()['count'];

    // 신규 고객 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM users
        WHERE role = 'client'
        AND created_at >= :start
    ");
    $stmt->execute(['start' => $start->format('Y-m-d H:i:s')]);
    $newClients = $stmt->fetch()['count'];

    // 매출
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_price), 0) as revenue
        FROM estimates
        WHERE status = 'completed'
        AND created_at >= :start
    ");
    $stmt->execute(['start' => $start->format('Y-m-d H:i:s')]);
    $revenue = $stmt->fetch()['revenue'];

    // 대기중인 견적 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM estimates
        WHERE status = 'requested'
    ");
    $stmt->execute();
    $pendingEstimates = $stmt->fetch()['count'];

    // 대기중인 작업 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tasks
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingTasks = $stmt->fetch()['count'];

    // 최근 견적 요청
    $stmt = $pdo->prepare("
        SELECT 
            e.code,
            e.name as customerName,
            e.work_type,
            e.created_at as requestDate,
            e.status
        FROM estimates e
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentEstimates = $stmt->fetchAll();

    // 진행중인 작업
    $stmt = $pdo->prepare("
        SELECT 
            t.title,
            u.name as assignedTo,
            t.start_date,
            t.end_date,
            t.status
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status = 'in_progress'
        ORDER BY t.end_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $activeTasks = $stmt->fetchAll();

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'newEstimates' => $newEstimates,
        'activeTasks' => $activeTasks,
        'newClients' => $newClients,
        'revenue' => $revenue,
        'pendingEstimates' => $pendingEstimates,
        'pendingTasks' => $pendingTasks,
        'recentEstimates' => $recentEstimates,
        'activeTasks' => $activeTasks
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '데이터를 불러오는 중 오류가 발생했습니다.'
    ]);
} 