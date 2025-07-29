<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

// POST 요청이 아닌 경우 처리
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 요청 방식입니다.']);
    exit;
}

try {
    $session = Session::getInstance();
    
    // 세션 체크
    if (!$session->isValid()) {
        throw new Exception('세션이 만료되었습니다.');
    }
    
    // 현재 사용자 정보
    $user = $session->getCurrentUser();
    
    // 필수 필드 검증
    if (empty($_POST['estimate_code']) || empty($_POST['content'])) {
        throw new Exception('필수 항목이 누락되었습니다.');
    }
    
    // 입력값 필터링
    $estimate_code = filter_var($_POST['estimate_code'], FILTER_SANITIZE_STRING);
    $content = filter_var($_POST['content'], FILTER_SANITIZE_STRING);
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 견적 정보 조회
    $stmt = $db->prepare("
        SELECT e.*, t.id as task_id
        FROM estimates e
        LEFT JOIN tasks t ON t.estimate_id = e.id AND t.status != 'completed'
        WHERE e.code = ?
        AND e.client_id = ?
        ORDER BY t.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$estimate_code, $user['id']]);
    $estimate = $stmt->fetch();
    
    if (!$estimate) {
        throw new Exception('견적 정보를 찾을 수 없습니다.');
    }
    
    // 작업이 없는 경우 새 작업 생성
    if (!$estimate['task_id']) {
        $stmt = $db->prepare("
            INSERT INTO tasks (
                estimate_id,
                title,
                status,
                created_at
            ) VALUES (
                ?,
                '견적 문의',
                'pending',
                NOW()
            )
        ");
        $stmt->execute([$estimate['id']]);
        $task_id = $db->lastInsertId();
    } else {
        $task_id = $estimate['task_id'];
    }
    
    // 댓글 저장
    $stmt = $db->prepare("
        INSERT INTO comments (
            task_id,
            user_id,
            content,
            created_at
        ) VALUES (
            ?, ?, ?, NOW()
        )
    ");
    $stmt->execute([$task_id, $user['id'], $content]);
    
    // 작업 상태 업데이트
    $stmt = $db->prepare("
        UPDATE tasks 
        SET status = 'feedback',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$task_id]);
    
    // 작업 로그 기록
    logTaskAction($task_id, $user['id'], 'comment_added', $content);
    
    // 담당자에게 알림 발송
    $stmt = $db->prepare("
        SELECT assigned_to
        FROM tasks
        WHERE id = ?
        AND assigned_to IS NOT NULL
    ");
    $stmt->execute([$task_id]);
    $assigned_to = $stmt->fetchColumn();
    
    if ($assigned_to) {
        createNotification(
            $assigned_to,
            'comment_added',
            "견적 {$estimate_code}에 새로운 댓글이 등록되었습니다."
        );
    }
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '댓글이 등록되었습니다.'
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("댓글 등록 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 