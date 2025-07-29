<?php
require_once 'session.php';

// AJAX 요청 확인
if (!is_ajax_request()) {
    die("잘못된 접근입니다.");
}

try {
    // 세션 삭제
    $session->destroy_session();

    send_json_response([
        'success' => true,
        'message' => '로그아웃되었습니다.',
        'redirect' => '/'
    ]);

} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => '로그아웃 처리 중 오류가 발생했습니다.'
    ]);
} 