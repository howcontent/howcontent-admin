<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');

try {
    $session = Session::getInstance();
    
    // 세션 체크
    if (!$session->isValid()) {
        throw new Exception('세션이 만료되었습니다.');
    }
    
    // 현재 사용자 정보
    $user = $session->getCurrentUser();
    
    // 페이지네이션 파라미터
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // 검색 및 필터 파라미터
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 0;
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 기본 WHERE 절
    $where = ['e.client_id = ?'];
    $params = [$user['id']];
    
    // 검색어 조건 추가
    if ($search) {
        $where[] = '(e.code LIKE ? OR e.work_type LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // 상태 필터 조건 추가
    if ($status) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }
    
    // 기간 필터 조건 추가
    if ($date_range > 0) {
        $where[] = 'e.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params[] = $date_range;
    }
    
    // WHERE 절 조합
    $where_clause = implode(' AND ', $where);
    
    // 전체 레코드 수 조회
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM estimates e
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // 전체 페이지 수 계산
    $total_pages = ceil($total / $per_page);
    
    // 현재 페이지가 전체 페이지 수를 초과하지 않도록 조정
    $page = min($page, $total_pages);
    
    // 견적 목록 조회
    $stmt = $db->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM tasks WHERE estimate_id = e.id) as task_count,
               (SELECT COUNT(*) FROM tasks WHERE estimate_id = e.id AND status = 'completed') as completed_task_count
        FROM estimates e
        WHERE $where_clause
        ORDER BY e.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    // 파라미터에 LIMIT, OFFSET 추가
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt->execute($params);
    $estimates = $stmt->fetchAll();
    
    // 견적 목록에 추가 정보 포함
    foreach ($estimates as &$estimate) {
        // 진행률 계산
        if ($estimate['task_count'] > 0) {
            $estimate['progress'] = round(($estimate['completed_task_count'] / $estimate['task_count']) * 100);
        } else {
            $estimate['progress'] = 0;
        }
        
        // 민감한 정보 제거
        unset($estimate['client_id']);
    }
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'estimates' => $estimates,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total,
        'per_page' => $per_page
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("견적 목록 조회 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 