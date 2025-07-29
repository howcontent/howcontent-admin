# HowContent Admin

외주 작업 관리를 위한 웹 기반 관리 시스템입니다.

## 주요 기능

- 고객 견적 요청 및 관리
- 팀원 업무 배정 및 관리
- AI 기반 자동화 기능
- 실시간 알림 및 피드백
- 작업 이력 관리

## 기술 스택

- Frontend: HTML5, CSS3, JavaScript (ES6+)
- Backend: PHP 7.4+
- Database: MySQL 8.0+
- Libraries:
  - jsPDF: PDF 생성
  - html2canvas: 화면 캡처

## 시스템 요구사항

- PHP 7.4 이상
- MySQL 8.0 이상
- Apache 2.4 이상 (mod_rewrite 활성화 필요)
- SSL 인증서 (HTTPS 필수)

## 설치 방법

1. 소스 코드 다운로드
```bash
git clone https://github.com/yourusername/howcontent-admin.git
cd howcontent-admin
```

2. 데이터베이스 설정
```bash
# MySQL에서 데이터베이스 생성
mysql -u root -p
CREATE DATABASE howcontent_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 스키마 적용
mysql -u root -p howcontent_admin < data/schema.sql
```

3. 설정 파일 수정
- `includes/db.php` 파일에서 데이터베이스 연결 정보 수정
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'howcontent_admin');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

4. 파일 권한 설정
```bash
# 업로드 디렉토리 권한 설정
chmod -R 755 .
chmod -R 777 uploads/
```

5. Apache 설정
- mod_rewrite 모듈 활성화
```bash
sudo a2enmod rewrite
sudo service apache2 restart
```

## 보안 설정

1. SSL 인증서 설치 및 HTTPS 설정
2. PHP 설정 최적화 (`php.ini`)
   - `display_errors = Off`
   - `error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT`
   - `session.cookie_httponly = 1`
   - `session.cookie_secure = 1`
3. MySQL 사용자 권한 제한
4. 파일 업로드 제한 설정

## 기본 계정

- 관리자 계정
  - 이메일: admin@howcontent.com
  - 비밀번호: admin123!

※ 보안을 위해 초기 설정 후 반드시 비밀번호를 변경해주세요.

## 디렉토리 구조

```
/
├── css/                # 스타일시트 파일
├── js/                 # 자바스크립트 파일
├── includes/           # PHP 백엔드 파일
├── error/             # 에러 페이지
├── admin/             # 관리자 페이지
├── user/              # 사용자 페이지
├── estimate/          # 견적 관련 페이지
├── data/              # 데이터베이스 스키마
└── uploads/           # 업로드 파일 저장소
```

## 라이선스

이 프로젝트는 MIT 라이선스를 따릅니다. 자세한 내용은 [LICENSE](LICENSE) 파일을 참조하세요.

## 문의사항

문의사항이나 버그 리포트는 이슈 트래커를 이용해주세요. 