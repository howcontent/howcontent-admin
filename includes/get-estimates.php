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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
$offset = ($page - 1) * $limit;

$status = isset($_GET['status']) ? $_GET['status'] : '';
$workType = isset($_GET['workType']) ? $_GET['workType'] : '';
$dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // 기본 WHERE 절
    $where = [];
    $params = [];

    // 상태 필터
    if (!empty($status)) {
        $where[] = "status = :status";
        $params['status'] = $status;
    }

    // 작업 종류 필터
    if (!empty($workType)) {
        $where[] = "work_type = :workType";
        $params['workType'] = $workType;
    }

    // 날짜 범위 필터
    $now = new DateTime();
    $start = new DateTime();

    switch ($dateRange) {
        case 'today':
            $start->setTime(0, 0, 0);
            $where[] = "created_at >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'week':
            $start->modify('-1 week')->setTime(0, 0, 0);
            $where[] = "created_at >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'month':
            $start->modify('first day of this month')->setTime(0, 0, 0);
            $where[] = "created_at >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
        case 'year':
            $start->modify('first day of january this year')->setTime(0, 0, 0);
            $where[] = "created_at >= :start";
            $params['start'] = $start->format('Y-m-d H:i:s');
            break;
    }

    // 검색어 필터
    if (!empty($search)) {
        $where[] = "(
            code LIKE :search OR
            name LIKE :search OR
            email LIKE :search OR
            phone LIKE :search OR
            company LIKE :search
        )";
        $params['search'] = "%{$search}%";
    }

    // WHERE 절 조합
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // 전체 견적 수 조회
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM estimates
        {$whereClause}
    ");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // 대기중인 견적 수 조회
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM estimates
        WHERE status = 'requested'
    ");
    $stmt->execute();
    $pendingCount = $stmt->fetch()['count'];

    // 견적 목록 조회
    $stmt = $pdo->prepare("
        SELECT 
            code,
            name as customerName,
            work_type,
            budget,
            deadline,
            status,
            created_at as createdAt
        FROM estimates
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT :offset, :limit
    ");

    // LIMIT 파라미터는 bindValue로 처리
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    $estimates = $stmt->fetchAll();

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'estimates' => $estimates,
        'total' => $total,
        'pendingCount' => $pendingCount,
        'currentPage' => $page,
        'totalPages' => ceil($total / $limit)
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '견적 목록을 불러오는 중 오류가 발생했습니다.'
    ]);
} 