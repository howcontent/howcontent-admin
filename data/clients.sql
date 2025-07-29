-- 클라이언트 테이블 생성
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 인덱스 생성
CREATE INDEX idx_company_name ON clients(company_name);
CREATE INDEX idx_contact_name ON clients(contact_name);
CREATE INDEX idx_email ON clients(email);
CREATE INDEX idx_status ON clients(status);

-- 샘플 데이터 삽입
INSERT INTO clients (company_name, contact_name, email, phone, address, notes, status) VALUES
('(주)하우컨텐츠', '김영희', 'kim@howcontent.com', '010-1234-5678', '서울시 강남구 테헤란로 123', '주요 파트너사', 'active'),
('디자인스튜디오', '이철수', 'lee@designstudio.com', '010-2345-6789', '서울시 서초구 반포대로 456', '디자인 전문 업체', 'active'),
('테크솔루션', '박지민', 'park@techsolution.com', '010-3456-7890', '서울시 마포구 홍대로 789', '웹 개발 전문', 'active'),
('크리에이티브랩', '정민수', 'jung@creativelab.com', '010-4567-8901', '서울시 성동구 왕십리로 321', '콘텐츠 제작 전문', 'inactive'),
('스마트미디어', '한소영', 'han@smartmedia.com', '010-5678-9012', '서울시 용산구 이태원로 654', '미디어 컨설팅', 'active'); 