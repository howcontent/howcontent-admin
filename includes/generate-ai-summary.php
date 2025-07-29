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

if (!isset($data['description']) || empty($data['description'])) {
    send_json_response([
        'success' => false,
        'message' => '작업 설명이 필요합니다.'
    ]);
    exit;
}

$description = $data['description'];

try {
    // OpenAI API 키 설정
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        throw new Exception('OpenAI API 키가 설정되지 않았습니다.');
    }

    // API 요청 데이터 구성
    $requestData = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => '작업 설명을 간단하고 명확하게 요약해주세요. 핵심 내용만 포함하고, 중요한 세부사항은 유지하세요.'
            ],
            [
                'role' => 'user',
                'content' => $description
            ]
        ],
        'max_tokens' => 150,
        'temperature' => 0.7
    ];

    // API 요청
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('AI 요약 생성 중 오류가 발생했습니다.');
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('AI 응답 형식이 올바르지 않습니다.');
    }

    $summary = trim($result['choices'][0]['message']['content']);

    // 응답 데이터 구성
    $response = [
        'success' => true,
        'summary' => $summary
    ];

    send_json_response($response);

} catch (Exception $e) {
    error_log("Generate AI Summary Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 