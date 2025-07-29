<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    
    // 견적 코드 검증
    if (empty($_GET['code'])) {
        throw new Exception('견적 코드가 누락되었습니다.');
    }
    
    $code = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
    
    // 데이터베이스 연결
    $db = getDB();
    
    // 견적 정보 조회
    $stmt = $db->prepare("
        SELECT e.*,
               u.name as client_name,
               u.company_name,
               u.business_number
        FROM estimates e
        JOIN users u ON e.client_id = u.id
        WHERE e.code = ?
        AND e.client_id = ?
    ");
    $stmt->execute([$code, $user['id']]);
    $estimate = $stmt->fetch();
    
    if (!$estimate) {
        throw new Exception('견적 정보를 찾을 수 없습니다.');
    }
    
    // 작업 목록 조회
    $stmt = $db->prepare("
        SELECT t.*,
               u.name as assigned_to_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.estimate_id = ?
        ORDER BY t.created_at ASC
    ");
    $stmt->execute([$estimate['id']]);
    $tasks = $stmt->fetchAll();
    
    // PDF 생성
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // HTML 템플릿 생성
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>견적서</title>
        <style>
            body {
                font-family: "NanumGothic", sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
            }
            .header h1 {
                font-size: 24px;
                margin: 0;
                padding: 20px 0;
                border-bottom: 2px solid #333;
            }
            .info-section {
                margin-bottom: 30px;
            }
            .info-section h2 {
                font-size: 18px;
                margin-bottom: 10px;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .info-table th,
            .info-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .info-table th {
                background: #f5f5f5;
                width: 30%;
            }
            .task-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .task-table th,
            .task-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .task-table th {
                background: #f5f5f5;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
            }
            .footer p {
                margin: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>견적서</h1>
            </div>
            
            <div class="info-section">
                <h2>기본 정보</h2>
                <table class="info-table">
                    <tr>
                        <th>견적 번호</th>
                        <td>' . $estimate['code'] . '</td>
                    </tr>
                    <tr>
                        <th>발행일</th>
                        <td>' . date('Y년 m월 d일', strtotime($estimate['created_at'])) . '</td>
                    </tr>
                    <tr>
                        <th>고객명</th>
                        <td>' . $estimate['client_name'] . '</td>
                    </tr>
                    ' . ($estimate['company_name'] ? '
                    <tr>
                        <th>회사명</th>
                        <td>' . $estimate['company_name'] . '</td>
                    </tr>
                    ' : '') . '
                    ' . ($estimate['business_number'] ? '
                    <tr>
                        <th>사업자등록번호</th>
                        <td>' . $estimate['business_number'] . '</td>
                    </tr>
                    ' : '') . '
                </table>
            </div>
            
            <div class="info-section">
                <h2>견적 내용</h2>
                <table class="info-table">
                    <tr>
                        <th>작업 종류</th>
                        <td>' . $estimate['work_type'] . '</td>
                    </tr>
                    <tr>
                        <th>예산</th>
                        <td>' . number_format($estimate['budget']) . '원</td>
                    </tr>
                    <tr>
                        <th>디자인 필요 여부</th>
                        <td>' . ($estimate['needs_design'] ? '필요' : '불필요') . '</td>
                    </tr>
                    <tr>
                        <th>마감일</th>
                        <td>' . date('Y년 m월 d일', strtotime($estimate['deadline'])) . '</td>
                    </tr>
                    <tr>
                        <th>요구사항</th>
                        <td>' . nl2br($estimate['requirements']) . '</td>
                    </tr>
                </table>
            </div>
            
            ' . (count($tasks) > 0 ? '
            <div class="info-section">
                <h2>작업 목록</h2>
                <table class="task-table">
                    <thead>
                        <tr>
                            <th>작업명</th>
                            <th>담당자</th>
                            <th>상태</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . implode('', array_map(function($task) {
                            return '
                            <tr>
                                <td>' . $task['title'] . '</td>
                                <td>' . ($task['assigned_to_name'] ?: '미배정') . '</td>
                                <td>' . getStatusText($task['status']) . '</td>
                            </tr>
                            ';
                        }, $tasks)) . '
                    </tbody>
                </table>
            </div>
            ' : '') . '
            
            <div class="footer">
                <p>HowContent Admin</p>
                <p>이메일: ' . ADMIN_EMAIL . '</p>
                <p>전화: 02-1234-5678</p>
                <p>발행일: ' . date('Y년 m월 d일') . '</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // PDF 파일 저장
    $filename = 'estimate_' . $code . '_' . date('Ymd') . '.pdf';
    $output_path = '../../uploads/estimates/' . $filename;
    
    // 디렉토리가 없으면 생성
    if (!file_exists('../../uploads/estimates')) {
        mkdir('../../uploads/estimates', 0777, true);
    }
    
    file_put_contents($output_path, $dompdf->output());
    
    // 성공 응답
    echo json_encode([
        'success' => true,
        'message' => 'PDF가 생성되었습니다.',
        'pdf_url' => '/uploads/estimates/' . $filename
    ]);

} catch (Exception $e) {
    // 에러 로깅
    error_log("PDF 생성 중 오류 발생: " . $e->getMessage());
    
    // 클라이언트에 에러 응답
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 상태 텍스트 변환
 */
function getStatusText($status) {
    $statusMap = [
        'requested' => '요청됨',
        'in_progress' => '진행중',
        'feedback' => '피드백중',
        'completed' => '완료',
        'pending' => '대기중',
        'review' => '검토중'
    ];
    return $statusMap[$status] ?? $status;
} 