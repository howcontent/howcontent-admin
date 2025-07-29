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

// JSON 요청 데이터 파싱
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['workType']) || !isset($data['budget']) || !isset($data['needDesign'])) {
    send_json_response([
        'success' => false,
        'message' => '필수 정보가 누락되었습니다.'
    ]);
    exit;
}

$workType = $data['workType'];
$budget = intval($data['budget']);
$needDesign = $data['needDesign'] === 'yes';

try {
    // 작업 종류 이름 변환
    $workTypeNames = [
        'product_registration' => '상품 등록',
        'detail_page' => '상세 페이지 제작',
        'marketing' => '마케팅 운영',
        'other' => '기타'
    ];
    $workTypeName = $workTypeNames[$workType] ?? $workType;

    // 예산 포맷
    $formattedBudget = number_format($budget) . '원';

    // 디자인 문구
    $designPhrase = $needDesign ? '디자인 작업을 포함한' : '디자인 작업을 제외한';

    // 기본 문장 생성
    $suggestion = "{$workTypeName} 작업을 요청하셨습니다. {$designPhrase} 작업 예산은 {$formattedBudget}입니다.";

    // 작업 종류별 추가 문구
    switch ($workType) {
        case 'product_registration':
            $suggestion .= " 상품 등록 작업은 상품 정보 입력, 이미지 최적화, 카테고리 설정 등을 포함합니다.";
            break;
        case 'detail_page':
            if ($needDesign) {
                $suggestion .= " 상세 페이지는 브랜드 아이덴티티를 고려한 디자인과 함께 상품의 특징과 장점을 효과적으로 전달하는 구성으로 제작됩니다.";
            } else {
                $suggestion .= " 제공해 주신 디자인 가이드에 따라 상세 페이지를 제작해 드립니다.";
            }
            break;
        case 'marketing':
            $suggestion .= " 마케팅 운영은 타겟 분석, 키워드 전략 수립, 광고 집행 및 성과 분석을 포함합니다.";
            break;
    }

    // 유사 견적 평균 가격 분석
    $stmt = $pdo->prepare("
        SELECT 
            AVG(total_price) as avg_price,
            AVG(DATEDIFF(deadline, created_at)) as avg_days,
            COUNT(*) as count
        FROM estimates
        WHERE 
            work_type = :work_type AND
            need_design = :need_design AND
            status = 'completed' AND
            created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $stmt->execute([
        'work_type' => $workType,
        'need_design' => $needDesign
    ]);
    $stats = $stmt->fetch();

    if ($stats['count'] > 0) {
        $avgPrice = number_format(round($stats['avg_price'])) . '원';
        $avgDays = round($stats['avg_days']);
        
        $suggestion .= "\n\n참고로, 최근 6개월간 유사한 {$stats['count']}건의 작업의 평균 견적은 {$avgPrice}이었으며, 평균 작업 기간은 {$avgDays}일이었습니다.";
    }

    send_json_response([
        'success' => true,
        'suggestion' => $suggestion
    ]);

} catch (Exception $e) {
    error_log("Generate AI Suggestion Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => 'AI 문장 생성 중 오류가 발생했습니다.'
    ]);
} 