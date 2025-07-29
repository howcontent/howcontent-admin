<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 인증 확인
checkAuth();

// 데이터베이스 연결
$db = getDB();

// HTTP 메소드에 따른 처리
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getClient($db, $_GET['id']);
        } else {
            getClients($db);
        }
        break;
    
    case 'POST':
        createClient($db);
        break;
    
    case 'PUT':
        updateClient($db);
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteClient($db, $_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '클라이언트 ID가 필요합니다.']);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => '허용되지 않는 메소드입니다.']);
        break;
}

// 클라이언트 목록 조회
function getClients($db) {
    try {
        $stmt = $db->prepare('
            SELECT id, company_name, contact_name, email, phone, address, notes, status, 
                   created_at, updated_at 
            FROM clients 
            ORDER BY company_name ASC
        ');
        $stmt->execute();
        
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($clients);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '클라이언트 목록을 불러오는데 실패했습니다.']);
    }
}

// 단일 클라이언트 조회
function getClient($db, $id) {
    try {
        $stmt = $db->prepare('
            SELECT id, company_name, contact_name, email, phone, address, notes, status,
                   created_at, updated_at 
            FROM clients 
            WHERE id = ?
        ');
        $stmt->execute([$id]);
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            echo json_encode($client);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '클라이언트를 찾을 수 없습니다.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '클라이언트 정보를 불러오는데 실패했습니다.']);
    }
}

// 클라이언트 생성
function createClient($db) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 필수 필드 검증
        if (!isset($data['company_name']) || !isset($data['contact_name']) || 
            !isset($data['email']) || !isset($data['phone'])) {
            http_response_code(400);
            echo json_encode(['error' => '필수 정보가 누락되었습니다.']);
            return;
        }
        
        $stmt = $db->prepare('
            INSERT INTO clients (
                company_name, contact_name, email, phone, address, notes, status
            ) VALUES (
                :company_name, :contact_name, :email, :phone, :address, :notes, :status
            )
        ');
        
        $stmt->execute([
            ':company_name' => $data['company_name'],
            ':contact_name' => $data['contact_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':address' => $data['address'] ?? '',
            ':notes' => $data['notes'] ?? '',
            ':status' => 'active'
        ]);
        
        $clientId = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => '클라이언트가 성공적으로 생성되었습니다.',
            'id' => $clientId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '클라이언트 생성에 실패했습니다.']);
    }
}

// 클라이언트 수정
function updateClient($db) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => '클라이언트 ID가 필요합니다.']);
            return;
        }
        
        $stmt = $db->prepare('
            UPDATE clients 
            SET company_name = :company_name,
                contact_name = :contact_name,
                email = :email,
                phone = :phone,
                address = :address,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        
        $stmt->execute([
            ':id' => $data['id'],
            ':company_name' => $data['company_name'],
            ':contact_name' => $data['contact_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':address' => $data['address'] ?? '',
            ':notes' => $data['notes'] ?? ''
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => '클라이언트 정보가 성공적으로 수정되었습니다.']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '클라이언트를 찾을 수 없습니다.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '클라이언트 정보 수정에 실패했습니다.']);
    }
}

// 클라이언트 삭제
function deleteClient($db, $id) {
    try {
        $stmt = $db->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => '클라이언트가 성공적으로 삭제되었습니다.']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '클라이언트를 찾을 수 없습니다.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '클라이언트 삭제에 실패했습니다.']);
    }
} 