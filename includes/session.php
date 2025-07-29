<?php
// 세션 설정
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1시간

session_start();

require_once __DIR__ . '/db.php';

// 세션 관리 클래스
class SessionManager {
    private $pdo;
    private $user_id;
    private $session_id;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->session_id = session_id();
        $this->user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    // 세션 생성
    public function create_session($user_id, $remember = false) {
        $this->user_id = $user_id;
        $_SESSION['user_id'] = $user_id;
        
        // 기존 세션 삭제
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 새 세션 저장
        $stmt = $this->pdo->prepare(
            "INSERT INTO sessions (id, user_id, data, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $this->session_id,
            $user_id,
            serialize($_SESSION),
            get_client_ip(),
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        if ($remember) {
            // 30일 동안 유지
            $lifetime = time() + (30 * 24 * 60 * 60);
            setcookie('remember_token', $this->session_id, $lifetime, '/', '', true, true);
        }
    }

    // 세션 검증
    public function validate_session() {
        if (!$this->user_id) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM sessions 
             WHERE id = ? AND user_id = ? 
             AND last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$this->session_id, $this->user_id]);
        
        if (!$stmt->fetch()) {
            $this->destroy_session();
            return false;
        }
        
        // 세션 갱신
        $this->update_session();
        return true;
    }

    // 세션 업데이트
    private function update_session() {
        $stmt = $this->pdo->prepare(
            "UPDATE sessions 
             SET last_activity = NOW(), 
                 data = ?, 
                 ip_address = ? 
             WHERE id = ?"
        );
        $stmt->execute([
            serialize($_SESSION),
            get_client_ip(),
            $this->session_id
        ]);
    }

    // 세션 삭제
    public function destroy_session() {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$this->session_id]);
        
        session_unset();
        session_destroy();
        
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    // 자동 로그인 처리
    public function handle_remember_me() {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            
            $stmt = $this->pdo->prepare(
                "SELECT user_id FROM sessions 
                 WHERE id = ? 
                 AND last_activity > DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute([$token]);
            
            if ($row = $stmt->fetch()) {
                $this->create_session($row['user_id'], true);
                return true;
            }
            
            // 만료된 토큰 삭제
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        return false;
    }

    // 사용자 정보 가져오기
    public function get_user() {
        if (!$this->user_id) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, email, name, phone, business_name, business_number, role 
             FROM users 
             WHERE id = ?"
        );
        $stmt->execute([$this->user_id]);
        return $stmt->fetch();
    }

    // 관리자 권한 확인
    public function is_admin() {
        $user = $this->get_user();
        return $user && $user['role'] === 'admin';
    }

    // 스태프 권한 확인
    public function is_staff() {
        $user = $this->get_user();
        return $user && ($user['role'] === 'admin' || $user['role'] === 'staff');
    }

    // 로그인 필요 페이지 체크
    public function require_login() {
        if (!$this->validate_session()) {
            if (is_ajax_request()) {
                send_json_response(['success' => false, 'message' => '로그인이 필요합니다.']);
            } else {
                redirect('/login.html');
            }
        }
    }

    // 관리자 권한 필요 페이지 체크
    public function require_admin() {
        $this->require_login();
        
        if (!$this->is_admin()) {
            if (is_ajax_request()) {
                send_json_response(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
            } else {
                redirect('/');
            }
        }
    }

    // 스태프 권한 필요 페이지 체크
    public function require_staff() {
        $this->require_login();
        
        if (!$this->is_staff()) {
            if (is_ajax_request()) {
                send_json_response(['success' => false, 'message' => '접근 권한이 없습니다.']);
            } else {
                redirect('/');
            }
        }
    }
}

// 세션 매니저 인스턴스 생성
$session = new SessionManager($pdo);

// 자동 로그인 처리
if (!isset($_SESSION['user_id'])) {
    $session->handle_remember_me();
} 