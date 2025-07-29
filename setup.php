<?php
require_once 'includes/db.php';

try {
    // schema.sql 파일 읽기
    $sql = file_get_contents('data/schema.sql');
    
    // 각 SQL 명령어 실행
    $db->exec($sql);
    
    echo "데이터베이스 설정이 완료되었습니다.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 