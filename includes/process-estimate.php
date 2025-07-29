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
$requiredFields = [
    'customerName', 'customerEmail', 'customerPhone',
    'workType', 'budget', 'deadline', 'needDesign'
];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        send_json_response([
            'success' => false,
            'message' => '필수 정보가 누락되었습니다.'
        ]);
        exit;
    }
}

// 입력값 검증
if (!validate_email($_POST['customerEmail'])) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 이메일 주소입니다.'
    ]);
    exit;
}

if (!validate_phone($_POST['customerPhone'])) {
    send_json_response([
        'success' => false,
        'message' => '유효하지 않은 전화번호입니다.'
    ]);
    exit;
}

// 입력값 정리
$data = [
    'code' => isset($_POST['code']) ? $_POST['code'] : generate_random_string(8),
    'name' => sanitize_input($_POST['customerName']),
    'email' => sanitize_input($_POST['customerEmail']),
    'phone' => format_phone_number($_POST['customerPhone']),
    'company' => isset($_POST['customerCompany']) ? sanitize_input($_POST['customerCompany']) : null,
    'work_type' => sanitize_input($_POST['workType']),
    'budget' => intval($_POST['budget']),
    'need_design' => $_POST['needDesign'] === 'yes',
    'reference' => isset($_POST['reference']) ? sanitize_input($_POST['reference']) : null,
    'deadline' => sanitize_input($_POST['deadline']),
    'requirements' => isset($_POST['requirements']) ? sanitize_input($_POST['requirements']) : null,
    'base_price' => isset($_POST['basePrice']) ? intval($_POST['basePrice']) : 0,
    'design_price' => isset($_POST['designPrice']) ? intval($_POST['designPrice']) : 0,
    'option_price' => isset($_POST['optionPrice']) ? intval($_POST['optionPrice']) : 0,
    'total_price' => isset($_POST['totalPrice']) ? intval($_POST['totalPrice']) : 0,
    'notes' => isset($_POST['notes']) ? sanitize_input($_POST['notes']) : null,
    'status' => isset($_POST['status']) ? sanitize_input($_POST['status']) : 'requested'
];

try {
    // 트랜잭션 시작
    $pdo->beginTransaction();

    // 견적서 존재 여부 확인 (수정 시)
    if (isset($_POST['code'])) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM estimates
            WHERE code = :code
        ");
        $stmt->execute(['code' => $data['code']]);
        $estimate = $stmt->fetch();

        if (!$estimate) {
            throw new Exception('견적서를 찾을 수 없습니다.');
        }

        // 견적서 수정
        $stmt = $pdo->prepare("
            UPDATE estimates
            SET
                name = :name,
                email = :email,
                phone = :phone,
                company = :company,
                work_type = :work_type,
                budget = :budget,
                need_design = :need_design,
                reference = :reference,
                deadline = :deadline,
                requirements = :requirements,
                base_price = :base_price,
                design_price = :design_price,
                option_price = :option_price,
                total_price = :total_price,
                notes = :notes,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $estimate['id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
            'work_type' => $data['work_type'],
            'budget' => $data['budget'],
            'need_design' => $data['need_design'],
            'reference' => $data['reference'],
            'deadline' => $data['deadline'],
            'requirements' => $data['requirements'],
            'base_price' => $data['base_price'],
            'design_price' => $data['design_price'],
            'option_price' => $data['option_price'],
            'total_price' => $data['total_price'],
            'notes' => $data['notes'],
            'status' => $data['status']
        ]);

        // 작업 상태 업데이트
        if ($data['status'] === 'in_progress') {
            $stmt = $pdo->prepare("
                UPDATE tasks
                SET status = 'in_progress'
                WHERE estimate_id = :estimate_id AND status = 'pending'
            ");
            $stmt->execute(['estimate_id' => $estimate['id']]);
        }

    } else {
        // 견적서 생성
        $stmt = $pdo->prepare("
            INSERT INTO estimates (
                code, name, email, phone, company,
                work_type, budget, need_design, reference,
                deadline, requirements, base_price, design_price,
                option_price, total_price, notes, status
            ) VALUES (
                :code, :name, :email, :phone, :company,
                :work_type, :budget, :need_design, :reference,
                :deadline, :requirements, :base_price, :design_price,
                :option_price, :total_price, :notes, :status
            )
        ");

        $stmt->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
            'work_type' => $data['work_type'],
            'budget' => $data['budget'],
            'need_design' => $data['need_design'],
            'reference' => $data['reference'],
            'deadline' => $data['deadline'],
            'requirements' => $data['requirements'],
            'base_price' => $data['base_price'],
            'design_price' => $data['design_price'],
            'option_price' => $data['option_price'],
            'total_price' => $data['total_price'],
            'notes' => $data['notes'],
            'status' => $data['status']
        ]);

        $estimateId = $pdo->lastInsertId();

        // 작업 생성
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                estimate_id, title, description,
                start_date, end_date, status
            ) VALUES (
                :estimate_id, :title, :description,
                CURRENT_DATE, :deadline, 'pending'
            )
        ");

        $stmt->execute([
            'estimate_id' => $estimateId,
            'title' => "{$data['work_type']} - {$data['name']}",
            'description' => $data['requirements'],
            'deadline' => $data['deadline']
        ]);

        // 관리자에게 알림 생성
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, type, title, content
            ) SELECT 
                id,
                'new_estimate',
                '새로운 견적 요청',
                :content
            FROM users
            WHERE role = 'admin'
        ");

        $content = "{$data['name']}님이 {$data['work_type']} 작업을 요청하셨습니다.";
        $stmt->execute(['content' => $content]);
    }

    // 트랜잭션 커밋
    $pdo->commit();

    send_json_response([
        'success' => true,
        'message' => isset($_POST['code']) ? '견적서가 수정되었습니다.' : '견적서가 생성되었습니다.',
        'code' => $data['code']
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Process Estimate Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 