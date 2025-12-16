# Payday 프로젝트 기술설명서 (Technical Specification Document)

## 📋 문서 개요
본 문서는 Payday 시스템의 기술적 구조, 데이터베이스 스키마, API 연동, 그리고 개발 환경 구성 방법을 상세히 설명합니다.

---

## 1. 기술 스택 (Technology Stack)

### 1.1 서버 환경
| 구성 요소 | 기술 | 버전 |
|----------|------|------|
| **운영체제** | Windows / Linux | Any |
| **웹 서버** | Apache / Nginx | 2.4+ |
| **PHP** | PHP | 7.4+ (8.0+ 권장) |
| **데이터베이스** | MySQL / MariaDB | 5.7+ / 10.3+ |
| **세션 저장소** | File-based sessions | - |

### 1.2 프론트엔드
- **HTML5**: 시맨틱 마크업
- **CSS3**: 반응형 디자인 (Media Queries)
- **JavaScript**: ES6+ (Vanilla JS)
- **jQuery**: DOM 조작 및 AJAX (3.x)

### 1.3 외부 API
- **Wideshot SMS API**: 문자 발송
- **Slack Webhook API**: 실시간 알림

### 1.4 개발 도구
- **버전 관리**: Git
- **로컬 개발**: XAMPP / MAMP / Docker
- **DB 관리**: phpMyAdmin / MySQL Workbench

---

## 2. 시스템 아키텍처 (System Architecture)

### 2.1 전체 구조도
```
┌─────────────┐
│   Browser   │ (사용자 UI)
└──────┬──────┘
       │ HTTP/HTTPS
       ↓
┌──────────────────┐
│  Apache/Nginx    │ (웹 서버)
│  + PHP-FPM       │
└────────┬─────────┘
         │
    ┌────┴────┐
    │         │
    ↓         ↓
┌─────────┐  ┌──────────┐
│  Pages  │  │ Process  │ (PHP 스크립트)
│  (UI)   │  │ (Logic)  │
└────┬────┘  └────┬─────┘
     │            │
     └────┬───────┘
          ↓
┌──────────────────┐
│   common.php     │ (핵심 로직 라이브러리)
│  - Interest Calc │
│  - Payment Proc  │
│  - DB Functions  │
└────────┬─────────┘
         ↓
┌──────────────────┐
│  MySQL Database  │
│  - contracts     │
│  - collections   │
│  - customers     │
└──────────────────┘
```

### 2.2 디렉토리 구조
```
payday/
├── css/
│   └── style.css          # 전역 스타일
├── js/
│   └── main.js            # 공통 자바스크립트
├── pages/                 # UI 화면
│   ├── header.php         # 공통 헤더
│   ├── footer.php         # 공통 푸터
│   ├── sidebar.php        # 사이드바 메뉴
│   ├── contract_manage.php
│   ├── collection_manage.php
│   ├── customer_manage.php
│   ├── transaction_ledger.php
│   ├── bond_ledger.php
│   ├── sms.php
│   ├── reports.php
│   └── settings.php
├── process/               # 백엔드 처리
│   ├── login_process.php
│   ├── contract_process.php
│   ├── collection_process.php
│   ├── sms_process.php
│   └── ...
├── uploads/               # 업로드 파일 저장소
│   ├── contracts/         # 계약서류
│   ├── company/           # 회사 이미지 (로고, 직인)
│   └── temp/
├── backup/                # DB 백업 파일
├── templates/             # 증명서 템플릿
├── config.php             # DB 연결 설정
├── common.php             # 핵심 함수 라이브러리
├── login.php              # 로그인 페이지
└── index.php              # 메인 진입점
```

---

## 3. 데이터베이스 설계 (Database Schema)

### 3.1 ERD (Entity Relationship Diagram)
```
customers (고객)
    ↓ 1:N
contracts (계약)
    ↓ 1:N
collections (수납)

contracts
    ↓ 1:N
condition_changes (조건변경)

contracts
    ↓ 1:N
contract_expenses (비용)
```

### 3.2 핵심 테이블 상세 스키마

#### (1) **customers** - 고객 테이블
```sql
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '고객명',
  `resident_id` varchar(20) DEFAULT NULL COMMENT '주민번호/법인번호',
  `phone` varchar(20) DEFAULT NULL COMMENT '연락처',
  `email` varchar(100) DEFAULT NULL,
  `address_registered` varchar(255) DEFAULT NULL COMMENT '등본상 주소',
  `address_real` varchar(255) DEFAULT NULL COMMENT '실거주 주소',
  `company_name` varchar(100) DEFAULT NULL COMMENT '직장명',
  `memo` text DEFAULT NULL COMMENT '특이사항',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### (2) **contracts** - 계약 테이블
```sql
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT '일반담보대출',
  `loan_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '대출원금',
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '연이율(%)',
  `overdue_interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '연체이율(%)',
  `loan_date` date NOT NULL COMMENT '대출일',
  `maturity_date` date NOT NULL COMMENT '만기일',
  `contract_day` int(11) NOT NULL COMMENT '약정일(1~31)',
  `repayment_method` varchar(50) DEFAULT '자유상환',
  `status` enum('active','paid','overdue','defaulted') DEFAULT 'active',
  `current_outstanding_principal` decimal(15,2) DEFAULT 0.00 COMMENT '현재 대출잔액',
  `shortfall_amount` decimal(15,2) DEFAULT 0.00 COMMENT '미수이자 누적',
  `last_interest_calc_date` date DEFAULT NULL COMMENT '최종이자계산일',
  `next_due_date` date DEFAULT NULL COMMENT '차회납입일',
  `classification_code` varchar(10) DEFAULT NULL COMMENT '구분코드',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  KEY `idx_status` (`status`),
  KEY `idx_loan_date` (`loan_date`),
  KEY `idx_next_due_date` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**중요 필드 설명**:
- `current_outstanding_principal`: 실시간 대출잔액 (원금 입금 시 차감)
- `shortfall_amount`: 이자 부족분 누적액 (다음 입금 시 우선 변제)
- `last_interest_calc_date`: 이자를 마지막으로 계산한 날짜 (입금일)

#### (3) **collections** - 수납 테이블
```sql
CREATE TABLE `collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) DEFAULT NULL COMMENT '트랜잭션 그룹ID',
  `contract_id` int(11) NOT NULL,
  `collection_date` date NOT NULL COMMENT '수납일',
  `collection_type` varchar(20) NOT NULL COMMENT '이자/원금/경비',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `generated_interest` decimal(15,2) DEFAULT 0.00 COMMENT '발생이자(참고)',
  `memo` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  `deleted_by` varchar(50) DEFAULT NULL COMMENT '삭제자',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  KEY `idx_collection_date` (`collection_date`),
  KEY `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`collection_type` 종류**:
- `경비`: 선취수수료, 중도상환수수료 등
- `이자`: 정상이자 + 연체이자
- `원금`: 대출원금 상환

#### (4) **condition_changes** - 조건변경 이력
```sql
CREATE TABLE `condition_changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `change_date` date NOT NULL COMMENT '변경적용일',
  `new_interest_rate` decimal(5,2) DEFAULT NULL,
  `new_overdue_rate` decimal(5,2) DEFAULT NULL,
  `memo` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  KEY `idx_change_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### (5) **contract_expenses** - 계약비용 관리
```sql
CREATE TABLE `contract_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `expense_date` date NOT NULL COMMENT '비용발생일',
  `amount` decimal(15,2) NOT NULL COMMENT '비용금액',
  `description` varchar(255) DEFAULT NULL COMMENT '비용내역',
  `is_processed` tinyint(1) DEFAULT 0 COMMENT '처리여부',
  `processed_date` datetime DEFAULT NULL,
  `linked_collection_id` int(11) DEFAULT NULL COMMENT '연결된수납ID',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### (6) **holidays** - 휴일 관리
```sql
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(100) DEFAULT NULL,
  `type` enum('holiday','workday') DEFAULT 'holiday' COMMENT 'holiday=휴일, workday=대체근무일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### (7) **company_info** - 회사정보
```sql
CREATE TABLE `company_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `ceo_name` varchar(100) DEFAULT NULL,
  `biz_reg_number` varchar(50) DEFAULT NULL COMMENT '사업자등록번호',
  `loan_reg_number` varchar(50) DEFAULT NULL COMMENT '대부업등록번호',
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `seal_path` varchar(255) DEFAULT NULL COMMENT '법인인감',
  `interest_account` varchar(255) DEFAULT NULL COMMENT '이자수취계좌',
  `expense_account` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 인덱스 전략
**조회 성능 최적화를 위한 복합 인덱스**:
- `contracts`: (`status`, `next_due_date`) - 연체 조회 시
- `collections`: (`contract_id`, `collection_date`) - 거래 내역 조회 시
- `customers`: (`name`, `phone`) - 고객 검색 시

---

## 4. 핵심 비즈니스 로직 (Core Business Logic)

### 4.1 이자 계산 알고리즘

#### 수식
```
일일이자 = 대출잔액 × (연이율 ÷ 100) ÷ 해당년도일수(365 or 366)
```

#### 코드 흐름
```php
// 1. 금리 이력 조회 (조건변경 포함)
$rate_history = get_interest_rate_history($link, $contract_id, $contract);

// 2. 계산 구간 분할 (금리변경일, 약정일 등으로 분할)
$checkpoints = [시작일, 금리변경일들, 약정일, 종료일];

// 3. 구간별 루프
foreach ($checkpoints as $period) {
    // 윤년 체크
    $days_in_year = is_leap_year($year) ? 366 : 365;
    
    // 정상이자 계산
    $normal_interest += $principal * ($rate / 100 / $days_in_year);
    
    // 연체 여부 확인 후 연체이자 계산
    if ($current_date >= $due_date) {
        $overdue_interest += $principal * (($overdue_rate - $rate) / 100 / $days_in_year);
    }
}

// 4. 결과 반환 (원 미만 버림)
return ['normal' => floor($normal), 'overdue' => floor($overdue), 'total' => $total];
```

### 4.2 수납 처리 로직

#### 처리 순서
1. **트랜잭션 시작** (`mysqli_begin_transaction`)
2. **발생 이자 계산** (`calculateAccruedInterest`)
3. **금액 배분**:
   ```
   입금액 = 경비 + 이자(부족금 포함) + 원금
   ```
4. **DB 저장**: `collections` 테이블에 3개 행 INSERT (경비/이자/원금)
5. **계약 상태 업데이트**: `current_outstanding_principal`, `shortfall_amount` 차감
6. **트랜잭션 커밋**

#### Pseudo Code
```php
mysqli_begin_transaction($link);

try {
    // 발생 이자 계산
    $interest = calculateAccruedInterest(...);
    $total_interest = $interest['total'] + $existing_shortfall;
    
    // 자동 분개
    $expense_paid = min($total_amount, $expense_amount);
    $interest_paid = min($total_amount - $expense_paid, $total_interest);
    $principal_paid = $total_amount - $expense_paid - $interest_paid;
    
    // DB 저장 (3개 행)
    INSERT INTO collections (...) VALUES (...경비...);
    INSERT INTO collections (...) VALUES (...이자...);
    INSERT INTO collections (...) VALUES (...원금...);
    
    // 계약 업데이트
    UPDATE contracts SET 
        current_outstanding_principal = current_outstanding_principal - $principal_paid,
        shortfall_amount = shortfall_amount + $interest['total'] - $interest_paid
    WHERE id = $contract_id;
    
    mysqli_commit($link);
} catch (Exception $e) {
    mysqli_rollback($link);
    throw $e;
}
```

---

## 5. API 연동 (External API Integration)

### 5.1 Wideshot SMS API

#### 엔드포인트
```
POST https://api.wideshot.co.kr/api/v1/message/sms
```

#### 헤더
```
Content-Type: application/x-www-form-urlencoded
sejongApiKey: {YOUR_API_KEY}
```

#### 요청 파라미터
```php
$data = [
    'userKey' => uniqid('sms_', true),  // 고유 발송 ID
    'receiverTelNo' => '01012345678',   // 수신번호
    'contents' => '메시지 내용',
    'callback' => '02-1234-5678'        // 발신번호
];
```

#### 응답 예시
```json
{
  "code": "200",
  "message": "success",
  "data": {
    "userKey": "sms_abc123",
    "sendStatus": "PENDING"
  }
}
```

### 5.2 Slack Webhook

#### 엔드포인트
```
POST https://hooks.slack.com/services/T00000000/B00000000/XXXX...
```

#### Payload
```json
{
  "text": "*[Payday]* 신규 대출 실행\n고객명: 홍길동\n금액: 50,000,000원",
  "username": "Payday Bot",
  "icon_emoji": ":moneyba g:"
}
```

---

## 6. 보안 정책 (Security Policy)

### 6.1 SQL Injection 방지
- ✅ **모든 쿼리에서 PreparedStatement 사용 필수**
```php
// 올바른 예
$stmt = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $contract_id);

// 잘못된 예 (절대 금지!)
$query = "SELECT * FROM contracts WHERE id = $contract_id";
```

### 6.2 XSS 방지
```php
// 출력 시 반드시 이스케이프
echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
```

### 6.3 파일 업로드 보안
```php
// 확장자 검증
$allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
if (!in_array(strtolower($ext), $allowed_ext)) {
    die("허용되지 않는 파일 형식");
}

// 파일명 난수화
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
```

### 6.4 세션 보안
```php
// 세션 설정
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// 로그인 체크
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}
```

---

## 7. 설치 및 배포 가이드 (Installation & Deployment)

### 7.1 시스템 요구사항
- **최소**: PHP 7.4, MySQL 5.7, 2GB RAM
- **권장**: PHP 8.0+, MySQL 8.0+, 4GB RAM, SSD

### 7.2 설치 순서
1. **웹 서버 설치**: Apache 또는 Nginx
2. **PHP 설치**: `mysqli`, `curl`, `mbstring` 확장 모듈 활성화
3. **데이터베이스 생성**: `CREATE DATABASE payday CHARACTER SET utf8mb4;`
4. **SQL 임포트**: `mysql -u root -p payday < payday_db.sql`
5. **설정 파일 수정**: `config.php`에서 DB 접속 정보 입력
6. **권한 설정**: `uploads/` 폴더에 쓰기 권한 부여

### 7.3 환경 변수 설정
```php
// config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'payday_user');
define('DB_PASSWORD', 'secure_password');
define('DB_NAME', 'payday');

// common.php
define('WIDESHOT_API_URL', 'https://api.wideshot.co.kr');
define('WIDESHOT_API_KEY', 'your_api_key_here');
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/...');
```

### 7.4 크론잡 설정 (Linux)
```bash
# 매일 오전 2시 DB 백업
0 2 * * * /usr/bin/mysqldump -u root -p'password' payday > /backup/payday_$(date +\%F).sql

# 매일 오전 12시 채권 스냅샷 생성
0 0 * * * /usr/bin/curl http://localhost/payday/process/create_snapshot.php
```

---

## 8. 성능 최적화 (Performance Optimization)

### 8.1 쿼리 최적화
- `EXPLAIN` 명령어로 실행 계획 분석
- N+1 문제 해결: JOIN 활용
- 페이징 쿼리에 `LIMIT`, `OFFSET` 사용

### 8.2 캐싱 전략
- PHP Op Cache 활성화
- 세션 데이터 최소화
- 정적 파일(CSS/JS) 브라우저 캐싱

### 8.3 데이터베이스 최적화
```sql
-- 정기적인 테이블 최적화
OPTIMIZE TABLE contracts;
OPTIMIZE TABLE collections;

-- 인덱스 확인
SHOW INDEX FROM contracts;
```

---

## 9. 모니터링 및 로깅 (Monitoring & Logging)

### 9.1 에러 로깅
```php
// php.ini 설정
error_reporting = E_ALL
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

### 9.2 슬로우 쿼리 로그
```ini
# my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

---

## 10. 확장 가능성 (Scalability)

### 향후 확장 계획
- [ ] Redis 캐시 서버 도입 (세션 관리)
- [ ] CDN 연동 (정적 파일 배포)
- [ ] API 전용 엔드포인트 구축 (RESTful API)
- [ ] Master-Slave DB 복제 (읽기 부하 분산)
- [ ] Elasticsearch 도입 (전문 검색)

---

**문서 버전**: 1.0  
**최종 수정일**: 2025-12-16  
**작성자**: Payday 프로젝트 팀
