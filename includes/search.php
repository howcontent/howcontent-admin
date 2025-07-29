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

// 검색어 체크
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
if (empty($query)) {
    send_json_response([
        'success' => false,
        'message' => '검색어를 입력해주세요.'
    ]);
    exit;
}

try {
    $user = $session->get_user();
    $results = [];
    $searchTerm = "%{$query}%";
    
    // 견적서 검색
    $stmt = $pdo->prepare("
        SELECT 
            'estimate' as type,
            id,
            code,
            name as title,
            work_type,
            status,
            created_at
        FROM estimates
        WHERE 
            code LIKE :term OR
            name LIKE :term OR
            email LIKE :term OR
            phone LIKE :term OR
            company LIKE :term
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute(['term' => $searchTerm]);
    $estimates = $stmt->fetchAll();
    $results['estimates'] = $estimates;

    // 작업 검색
    $stmt = $pdo->prepare("
        SELECT 
            'task' as type,
            t.id,
            t.title,
            t.status,
            t.created_at,
            u.name as assigned_to
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE 
            t.title LIKE :term OR
            t.description LIKE :term OR
            u.name LIKE :term
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute(['term' => $searchTerm]);
    $tasks = $stmt->fetchAll();
    $results['tasks'] = $tasks;

    // 관리자인 경우 고객 검색 추가
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT 
                'client' as type,
                id,
                name,
                email,
                phone,
                business_name,
                created_at
            FROM users
            WHERE 
                role = 'client' AND
                (name LIKE :term OR
                email LIKE :term OR
                phone LIKE :term OR
                business_name LIKE :term OR
                business_number LIKE :term)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute(['term' => $searchTerm]);
        $clients = $stmt->fetchAll();
        $results['clients'] = $clients;
    }

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'results' => $results
    ];

    send_json_response($response);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '검색 중 오류가 발생했습니다.'
    ]);
} 