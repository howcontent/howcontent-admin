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
    // 필수 필드 검증
    $required_fields = ['work_type', 'budget', 'deadline', 'name', 'email', 'phone'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("필수 항목이 누락되었습니다: $field");
        }
    }

    // 입력값 필터링
    $work_type = filter_var($_POST['work_type'], FILTER_SANITIZE_STRING);
    $budget = filter_var($_POST['budget'], FILTER_SANITIZE_NUMBER_INT);
    $needs_design = isset($_POST['needs_design']) ? 1 : 0;
    $reference_url = filter_var($_POST['reference_url'], FILTER_SANITIZE_URL);
    $deadline = filter_var($_POST['deadline'], FILTER_SANITIZE_STRING);
    $requirements = filter_var($_POST['requirements'], FILTER_SANITIZE_STRING);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $company = filter_var($_POST['company'], FILTER_SANITIZE_STRING);

    // 이메일 유효성 검사
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('유효하지 않은 이메일 주소입니다.');
    }

    // 전화번호 형식 검사
    if (!preg_match('/^[0-9]{2,3}-[0-9]{3,4}-[0-9]{4}$/', $phone)) {
        throw new Exception('유효하지 않은 전화번호 형식입니다.');
    }

    // 데이터베이스 연결
    $db = getDB();

    // 견적 코드 생성
    $estimate_code = generateEstimateCode();

    // 고객 정보 저장 또는 조회
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // 새 고객 등록
        $stmt = $db->prepare("
            INSERT INTO users (email, name, phone, company_name, role) 
            VALUES (?, ?, ?, ?, 'client')
        ");
        $stmt->execute([$email, $name, $phone, $company]);
        $client_id = $db->lastInsertId();
    } else {
        $client_id = $user['id'];
    }

    // 견적 요청 저장
    $stmt = $db->prepare("
        INSERT INTO estimates (
            client_id, code, work_type, budget, needs_design, 
            reference_url, deadline, requirements, status
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, 'requested'
        )
    ");

    $stmt->execute([
        $client_id, $estimate_code, $work_type, $budget, $needs_design,
        $reference_url, $deadline, $requirements
    ]);

    $estimate_id = $db->lastInsertId();

    // AI를 통한 견적 요약 생성
    $summary = generateAISummary([
        'work_type' => $work_type,
        'budget' => $budget,
        'needs_design' => $needs_design,
        'deadline' => $deadline,
        'requirements' => $requirements
    ]);

    // 작업 로그 기록
    logTaskAction($estimate_id, $client_id, 'estimate_requested', $summary);

    // 관리자에게 알림
    $admin_notification = "새로운 견적 요청이 접수되었습니다. (코드: $estimate_code)";
    createNotification(1, 'estimate_requested', $admin_notification);

    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => '견적 요청이 성공적으로 접수되었습니다.',
        'estimate_code' => $estimate_code
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("견적 요청 처리 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * AI를 통한 견적 요약 생성
 */
function generateAISummary($data) {
    try {
        $prompt = "다음 견적 요청 내용을 자연스러운 문장으로 요약해주세요:\n\n";
        $prompt .= "작업 종류: {$data['work_type']}\n";
        $prompt .= "예산: {$data['budget']}원\n";
        $prompt .= "디자인 필요: " . ($data['needs_design'] ? "예" : "아니오") . "\n";
        $prompt .= "마감일: {$data['deadline']}\n";
        $prompt .= "요구사항: {$data['requirements']}\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '견적 요청 내용을 자연스러운 한국어 문장으로 요약해주세요.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 200
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        
        curl_close($ch);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        return "AI 요약 생성에 실패했습니다.";
        
    } catch (Exception $e) {
        error_log("AI 요약 생성 중 오류 발생: " . $e->getMessage());
        return "AI 요약 생성 중 오류가 발생했습니다.";
    }
} 