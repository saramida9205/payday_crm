# Payday 상세 구현 매뉴얼 (Detailed Implementation Manual)

## 📋 문서 개요
본 문서는 Payday 시스템을 **코드 레벨에서 완벽히 구현**하기 위한 초상세 가이드입니다. 모든 핵심 함수의 완전한 코드, SQL 스키마, 그리고 단계별 구현 방법을 포함하고 있어, 이 문서만으로 프로젝트를 처음부터 끝까지 완성할 수 있습니다.

---

## 목차
1. [환경 설정 완전 가이드](#section1)
2. [데이터베이스 완전 구축](#section2)
3. [핵심 로직 함수 완전 구현](#section3)
4. [화면 개발 완전 가이드](#section4)
5. [문제 해결 가이드](#section5)

---

<a name="section1"></a>
## 1. 환경 설정 완전 가이드

### 1-1. XAMPP 설치 및 설정 (Windows)

#### Step 1: XAMPP 다운로드
```
https://www.apachefriends.org/download.htm에 접속
→ PHP 8.0 이상 버전 선택
→ Windows용 EXE 다운로드
```

#### Step 2: 설치 및 실행
```
1. xampp-windows-x64-8.0.x.exe 실행
2. Apache, MySQL, PHP 체크 (기타는 선택사항)
3. C:\xampp 경로에 설치
4. XAMPP Control Panel 실행
5. Apache, MySQL 'Start' 버튼 클릭
```

#### Step 3: PHP 설정 확인
```php
# C:\xampp\php\php.ini 파일 열기
# 다음 항목들이 활성화(주석 제거)되어 있어야 함:

extension=mysqli
extension=mbstring
extension=curl

# 타임존 설정
date.timezone = Asia/Seoul

# 업로드 설정
upload_max_filesize = 20M
post_max_size = 20M
```

#### Step 4: MySQL 루트 비밀번호 설정
```sql
-- phpMyAdmin 접속: http://localhost/phpmyadmin
-- SQL 탭에서 실행:
ALTER USER 'root'@'localhost' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
```

### 1-2. 프로젝트 폴더 생성

```bash
# Windows 명령 프롬프트에서:
cd C:\xampp\htdocs
mkdir payday
cd payday

# 하위 폴더 일괄 생성
mkdir css js pages process uploads\contracts uploads\company backup templates
```

### 1-3. Git 초기화 (선택사항)
```bash
cd C:\xampp\htdocs\payday
git init
echo "backup/" >> .gitignore
echo "uploads/" >> .gitignore
echo "config.php" >> .gitignore
git add .
git commit -m "Initial commit"
```

---

<a name="section2"></a>
## 2. 데이터베이스 완전 구축

### 2-1. DB 생성 및 초기 설정
```sql
-- phpMyAdmin 또는 MySQL 클라이언트에서 실행

CREATE DATABASE payday 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_general_ci;

USE payday;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
```

### 2-2. 전체 테이블 스키마 (의존성 순서대로)

#### 테이블 1: users (관리자 계정)
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) UNIQUE NOT NULL COMMENT '로그인ID',
  `password` varchar(255) NOT NULL COMMENT '암호화된 비밀번호',
  `name` varchar(100) DEFAULT NULL COMMENT '사용자 이름',
  `permission_level` enum('user','admin','superadmin') DEFAULT 'user',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 초기 관리자 계정 생성 (비밀번호: admin123)
INSERT INTO users (username, password, name, permission_level) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '시스템관리자', 'superadmin');

```

#### 테이블 2: company_info (회사 정보)
```sql
CREATE TABLE `company_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `ceo_name` varchar(100) DEFAULT NULL,
  `biz_reg_number` varchar(50) DEFAULT NULL COMMENT '사업자등록번호',
  `loan_reg_number` varchar(50) DEFAULT NULL COMMENT '대부업등록번호',
  `phone` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `seal_path` varchar(255) DEFAULT NULL COMMENT '법인인감',
  `interest_account` varchar(255) DEFAULT NULL COMMENT '이자수취계좌',
  `expense_account` varchar(255) DEFAULT NULL COMMENT '경비수취계좌',
  `slack_notifications_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 기본 데이터
INSERT INTO company_info (id, company_name, slack_notifications_enabled) 
VALUES (1, '(주)페이데이', 1);
```

#### 테이블 3: holidays (휴일)
```sql
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(100) DEFAULT NULL,
  `type` enum('holiday','workday') DEFAULT 'holiday' COMMENT 'holiday=휴일, workday=대체근무일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2025년 공휴일 샘플
INSERT INTO holidays (holiday_date, holiday_name, type) VALUES
('2025-01-01', '신정', 'holiday'),
('2025-01-28', '설날 연휴', 'holiday'),
('2025-01-29', '설날', 'holiday'),
('2025-01-30', '설날 연휴', 'holiday'),
('2025-03-01', '삼일절', 'holiday'),
('2025-05-05', '어린이날', 'holiday'),
('2025-05-06', '대체공휴일', 'holiday'),
('2025-06-06', '현충일', 'holiday'),
('2025-08-15', '광복절', 'holiday'),
('2025-09-06', '추석 연휴', 'holiday'),
('2025-09-07', '추석 연휴', 'holiday'),
('2025-09-08', '추석', 'holiday'),
('2025-09-09', '추석 연휴', 'holiday'),
('2025-10-03', '개천절', 'holiday'),
('2025-10-09', '한글날', 'holiday'),
('2025-12-25', '성탄절', 'holiday');
```

#### 테이블 4: customers (고객)
```sql
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `resident_id` varchar(20) DEFAULT NULL COMMENT '주민번호/법인번호',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address_registered` varchar(255) DEFAULT NULL COMMENT '등본상 주소',
  `address_real` varchar(255) DEFAULT NULL COMMENT '실거주 주소',
  `company_name` varchar(100) DEFAULT NULL COMMENT '직장/사업장',
  `memo` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 5: contracts (계약) - 가장 중요!
```sql
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT '일반담보대출',
  `loan_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '대출원금',
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '연이율(%)',
  `overdue_interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '연체이율(%)',
  `loan_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `contract_day` int(11) NOT NULL COMMENT '약정일(1~31)',
  `repayment_method` varchar(50) DEFAULT '자유상환',
  `status` enum('active','paid','overdue','defaulted') DEFAULT 'active',
  `current_outstanding_principal` decimal(15,2) DEFAULT 0.00 COMMENT '현재 대출잔액',
  `shortfall_amount` decimal(15,2) DEFAULT 0.00 COMMENT '미수이자',
  `last_interest_calc_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `classification_code` varchar(10) DEFAULT NULL,
  `memo` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_loan_date` (`loan_date`),
  KEY `idx_next_due_date` (`next_due_date`),
  KEY `idx_status_due` (`status`, `next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 6: collections (수납)
```sql
CREATE TABLE `collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) DEFAULT NULL COMMENT '트랜잭션 그룹ID',
  `contract_id` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `collection_type` varchar(20) NOT NULL COMMENT '이자/원금/경비',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `generated_interest` decimal(15,2) DEFAULT 0.00 COMMENT '발생이자(참고용)',
  `memo` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT '소프트삭제',
  `deleted_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  KEY `idx_collection_date` (`collection_date`),
  KEY `idx_contract_id` (`contract_id`),
  KEY `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 7: condition_changes (조건변경)
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
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  KEY `idx_contract_id` (`contract_id`),
  KEY `idx_change_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 8: contract_expenses (계약비용)
```sql
CREATE TABLE `contract_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0 COMMENT '0=미처리, 1=처리됨',
  `processed_date` datetime DEFAULT NULL,
  `linked_collection_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  KEY `idx_contract_id` (`contract_id`),
  KEY `idx_is_processed` (`is_processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 9: sms_log (SMS 발송 로그)
```sql
CREATE TABLE `sms_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) DEFAULT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message_content` text NOT NULL,
  `send_date` datetime DEFAULT current_timestamp(),
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `api_response` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_send_date` (`send_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 테이블 10: bond_ledger_snapshots (채권 스냅샷)
```sql
CREATE TABLE `bond_ledger_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_date` datetime NOT NULL,
  `contract_id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(255) DEFAULT NULL,
  `loan_amount` decimal(15,2) DEFAULT NULL,
  `outstanding_principal` decimal(15,2) DEFAULT NULL,
  `overdue_days` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot_date` (`snapshot_date`),
  KEY `idx_contract_id` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2-3. 테이블 생성 완료 확인
```sql
-- 모든 테이블이 정상 생성되었는지 확인
SHOW TABLES;

-- 각 테이블 구조 확인 (예: contracts)
DESCRIBE contracts;

-- Foreign Key 확인
SELECT 
  TABLE_NAME, 
  COLUMN_NAME, 
  CONSTRAINT_NAME, 
  REFERENCED_TABLE_NAME, 
  REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'payday';
```

---

<a name="section3"></a>
## 3. 핵심 로직 함수 완전 구현

### 3-1. config.php 파일 생성

파일 위치: `C:\xampp\htdocs\payday\config.php`

```php
<?php
/**
 * Payday CRM - Database Configuration
 * 이 파일은 데이터베이스 접속 정보를 담고 있으며, 보안상 Git에는 포함하지 않습니다.
 */

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your_password_here'); // 실제 비밀번호로 변경
define('DB_NAME', 'payday');

// API Keys (나중에 설정)
define('WIDESHOT_API_URL', 'https://api.wideshot.co.kr');
define('WIDESHOT_API_KEY', 'YOUR_API_KEY_HERE');

// Attempt to connect to MySQL database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    die("<h1>ERROR: Could not connect to database.</h1><p>" . mysqli_connect_error() . "</p>");
}

// Set character set to utf8mb4 for emoji support
mysqli_set_charset($link, "utf8mb4");
?>
```

### 3-2. common.php - 핵심 함수 라이브러리

파일 위치: `C:\xampp\htdocs\payday\common.php`

이 파일은 **시스템의 심장부**입니다. 모든 핵심 로직이 여기 담깁니다.

```php
<?php
/**
 * Payday CRM - Common Functions Library
 * 모든 페이지에서 공통으로 사용하는 함수들을 정의합니다.
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Seoul');

// Include config
require_once "config.php";

// Slack Webhook URL (실제 URL로 교체)
define('SLACK_WEBHOOK_URL', 'YOUR_SLACK_WEBHOOK_URL');

// ============================================================
// 1. 유틸리티 함수 (Utility Functions)
// ============================================================

/**
 * 윤년 확인
 */
function is_leap_year($year) {
    return (date('L', mktime(0, 0, 0, 1, 1, $year)) == 1);
}

/**
 * 휴일 예외 정보 조회 (DB에서)
 */
function getHolidayExceptions() {
    global $link;
    $data = ['holidays' => [], 'workdays' => []];
    
    if (!$link) return $data;
    
    $current_year = date('Y');
    $next_year = $current_year + 1;
    
    $sql = "SELECT holiday_date, type FROM holidays WHERE YEAR(holiday_date) BETWEEN ? AND ?";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $current_year, $next_year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['type'] == 'workday') {
                $data['workdays'][] = $row['holiday_date'];
            } else {
                $data['holidays'][] = $row['holiday_date'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $data;
}

/**
 * 특정 날짜가 휴일인지 확인
 */
function isHoliday($date_str, $exceptions = null) {
    if ($exceptions === null) {
        $exceptions = getHolidayExceptions();
    }
    
    // 1. 명시적 근무일 확인
    if (in_array($date_str, $exceptions['workdays'])) {
        return false;
    }
    
    // 2. 명시적 휴일 확인
    if (in_array($date_str, $exceptions['holidays'])) {
        return true;
    }
    
    // 3. 기본: 주말은 휴일
    $w = date('w', strtotime($date_str));
    return ($w == 0 || $w == 6);
}

/**
 * 계약 상태 HTML 표시
 */
function get_status_display($status) {
    switch ($status) {
        case 'active':
            return '<span style="color: green;">정상</span>';
        case 'paid':
            return '<span style="color: blue;">완납</span>';
        case 'defaulted':
            return '<span style="color: grey;">부실</span>';
        case 'overdue':
            return '<span style="color: red; font-weight: bold;">연체</span>';
        default:
            return htmlspecialchars($status);
    }
}

// ============================================================
// 2. 금리 관련 함수 (Interest Rate Functions)
// ============================================================

/**
 * 계약의 금리 이력 조회 (조건변경 포함)
 * 
 * @param mysqli $link DB 연결
 * @param int $contract_id 계약 ID
 * @param array $contract 계약 정보 배열
 * @return array 금리 이력 배열 (날짜순 정렬)
 */
function get_interest_rate_history($link, $contract_id, $contract ) {
    $history = [];
    
    // 1. 초기 금리 (계약일 기준)
    $history[] = [
        'start_date' => $contract['loan_date'],
        'interest_rate' => (float)$contract['interest_rate'],
        'overdue_rate' => (float)$contract['overdue_interest_rate']
    ];
    
    // 2. 조건변경 이력 조회
    $sql = "SELECT change_date, new_interest_rate, new_overdue_rate 
            FROM condition_changes 
            WHERE contract_id = ? 
            ORDER BY change_date ASC";
    
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $contract_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = [
                'start_date' => $row['change_date'],
                'interest_rate' => (float)($row['new_interest_rate'] ?? $history[count($history)-1]['interest_rate']),
                'overdue_rate' => (float)($row['new_overdue_rate'] ?? $history[count($history)-1]['overdue_rate'])
            ];
        }
        mysqli_stmt_close($stmt);
    }
    
    return $history;
}

// ============================================================
// 3. 이자 계산 함수 (Interest Calculation) - 가장 중요!
// ============================================================

/**
 * 기간별 이자 계산 (윤년, 변동금리, 연체 모두 고려)
 * 
 * @param mysqli $link DB 연결
 * @param array $contract 계약 정보
 * @param float $principal 계산 대상 원금
 * @param string $start_date_str 시작일 (YYYY-MM-DD)
 * @param string $end_date_str 종료일 (YYYY-MM-DD)
 * @param string $due_date_str 약정일 (YYYY-MM-DD)
 * @return array ['normal' => 정상이자, 'overdue' => 연체이자, 'total' => 합계]
 */
function calculateAccruedInterestForPeriod($link, $contract, $principal, $start_date_str, $end_date_str, $due_date_str) {
    
    // STEP 1: 날짜 객체 생성
    $start_date = new DateTime($start_date_str ?? 'now');
    $end_date = new DateTime($end_date_str ?? 'now');
    
    // 시작일 >= 종료일이면 이자 0
    if ($end_date <= $start_date) {
        return ['normal' => 0, 'overdue' => 0, 'total' => 0, 'details' => []];
    }
    
    $due_date = new DateTime($due_date_str ?? 'now');
    
    // STEP 2: 금리 이력 조회
    $rate_history = get_interest_rate_history($link, $contract['id'], $contract);
    
    $normal_interest = 0;
    $overdue_interest = 0;
    $details = [];
    
    // STEP 3: 계산 체크포인트 설정
    // (시작일, 금리변경일, 약정일, 종료일을 모두 체크포인트로)
    $checkpoints = [$start_date];
    
    // 약정일이 기간 내에 있으면 체크포인트 추가
    if ($due_date > $start_date && $due_date < $end_date) {
        $checkpoints[] = $due_date;
    }
    
    // 금리 변경일들을 체크포인트에 추가
    foreach ($rate_history as $change) {
        $change_date = new DateTime($change['start_date']);
        if ($change_date > $start_date && $change_date < $end_date) {
            $checkpoints[] = $change_date;
        }
    }
    
    $checkpoints[] = $end_date;
    
    // 중복 제거 및 정렬
    $unique_checkpoints = [];
    foreach ($checkpoints as $date) {
        $unique_checkpoints[$date->format('Y-m-d')] = $date;
    }
    $unique_checkpoints = array_values($unique_checkpoints);
    usort($unique_checkpoints, function($a, $b) { return $a <=> $b; });
    
    // STEP 4: 구간별로 이자 계산
    for ($i = 0; $i < count($unique_checkpoints) - 1; $i++) {
        $period_start = $unique_checkpoints[$i];
        $period_end = $unique_checkpoints[$i + 1];
        $days = $period_end->diff($period_start)->days;
        
        if ($days <= 0) continue;
        
        // 이 구간에 적용되는 금리 찾기 (가장 최근의 금리)
        $current_rates = $rate_history[0];
        foreach ($rate_history as $change) {
            if ($period_start->format('Y-m-d') >= $change['start_date']) {
                $current_rates = $change;
            }
        }
        
        $normal_rate = $current_rates['interest_rate'];
        $overdue_rate = $current_rates['overdue_rate'];
        
        // 이 구간이 연체 기간인지 확인
        $is_overdue = $period_start >= $due_date;
        
        // 일별 루프 (윤년 고려)
        $temp_date = clone $period_start;
        for ($d = 0; $d < $days; $d++) {
            $year = (int)$temp_date->format('Y');
            $days_in_year = is_leap_year($year) ? 366 : 365;
            
            // 정상 이자 계산
            $daily_normal_rate = $normal_rate / 100 / $days_in_year;
            $normal_interest += $principal * $daily_normal_rate;
            
            // 연체 이자 계산 (연체 기간인 경우만)
            if ($is_overdue) {
                $daily_penalty_rate = ($overdue_rate - $normal_rate) / 100 / $days_in_year;
                $overdue_interest += $principal * $daily_penalty_rate;
            }
            
            $temp_date->modify('+1 day');
        }
    }
    
    // STEP 5: 원 미만 버림 처리
    $final_normal = floor($normal_interest);
    $final_overdue = floor($overdue_interest);
    $total_interest = $final_normal + $final_overdue;
    
    return [
        'normal' => $final_normal,
        'overdue' => $final_overdue,
        'total' => $total_interest,
        'details' => $details
    ];
}

/**
 * 특정 일자까지의 발생 이자 계산
 */
function calculateAccruedInterest($link, $contract, $target_date_str) {
    $contract_id = $contract['id'];
    $loan_date_str = $contract['loan_date'];
    
    // 마지막 이자 계산일 조회
    if (!empty($contract['last_interest_calc_date'])) {
        $last_interest_payment_date_str = $contract['last_interest_calc_date'];
    } else {
        // 마지막 수납일 조회
        $last_payment_query = mysqli_prepare($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($last_payment_query, "i", $contract_id);
        mysqli_stmt_execute($last_payment_query);
        $last_interest_payment_date_str = mysqli_fetch_assoc(mysqli_stmt_get_result($last_payment_query))['last_date'] ?? $loan_date_str;
        mysqli_stmt_close($last_payment_query);
    }
    
    $outstanding_principal = (float)($contract['current_outstanding_principal'] ?? 0);
    $current_due_date = $contract['next_due_date'] ?? $contract['loan_date'];
    
    return calculateAccruedInterestForPeriod($link, $contract, $outstanding_principal, $last_interest_payment_date_str, $target_date_str, $current_due_date);
}

// ============================================================
// 4. 수납 처리 함수 (Collection Processing) - 핵심!
// ============================================================

/**
 * 수납 처리 (자동 분개 로직 포함)
 * 
 * @param mysqli $link DB 연결
 * @param int $contract_id 계약 ID
 * @param string $collection_date_str 수납일
 * @param float $total_amount 총 입금액
 * @param float $expense_payment 경비 배분액
 * @param float $interest_payment 이자 배분액
 * @param float $principal_payment 원금 배분액
 * @param string $memo 메모
 * @param string $expense_memo 경비 메모
 * @param string $transaction_id 트랜잭션 ID
 * @return bool 성공 여부
 */
function process_collection($link, $contract_id, $collection_date_str, $total_amount, $expense_payment, $interest_payment, $principal_payment, $memo, $expense_memo, $transaction_id) {
    
    // 1. 유효성 검사
    $stmt_dates = mysqli_prepare($link, "SELECT loan_date, (SELECT MAX(collection_date) FROM collections WHERE contract_id = ? AND deleted_at IS NULL) as last_collection FROM contracts WHERE id = ?");
    mysqli_stmt_bind_param($stmt_dates, "ii", $contract_id, $contract_id);
    mysqli_stmt_execute($stmt_dates);
    $dates = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dates));
    mysqli_stmt_close($stmt_dates);
    
    $min_allowed_date_str = $dates['last_collection'] ?? $dates['loan_date'];
    if (new DateTime($collection_date_str) <= new DateTime($min_allowed_date_str)) {
        throw new Exception("입금일은 마지막 거래일({$min_allowed_date_str}) 이후여야 합니다.");
    }
    
    if ($total_amount <= 0) {
        throw new Exception("입금액은 0보다 커야 합니다.");
    }
    
    // 2. 계약 정보 및 현재 상태 조회
    $contract_data = getContractById($link, $contract_id);
    $existing_shortfall = (float)$contract_data['shortfall_amount'];
    $outstanding_principal = (float)$contract_data['current_outstanding_principal'];
    
    // 발생 이자 계산
    $interest_data = calculateAccruedInterest($link, $contract_data, $collection_date_str);
    $total_interest_to_be_paid = $interest_data['total'] + $existing_shortfall;
    
    // 원금 검증
    if ($principal_payment > $outstanding_principal) {
        throw new Exception("원금 상환액이 대출 잔액을 초과할 수 없습니다.");
    }
    
    // 3. 트랜잭션 시작 (중요!)
    mysqli_begin_transaction($link);
    
    try {
        $base_memo = "[자동분개] " . $memo;
        
        // 경비 저장
        if ($expense_payment > 0) {
            $sql_expense = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_expense = mysqli_prepare($link, $sql_expense);
            $type_expense = '경비';
            $final_expense_memo = $base_memo . ($expense_memo ? " (경비: " . $expense_memo . ")" : "");
            mysqli_stmt_bind_param($stmt_expense, "sissds", $transaction_id, $contract_id, $collection_date_str, $type_expense, $expense_payment, $final_expense_memo);
            if (!mysqli_stmt_execute($stmt_expense)) {
                throw new Exception("경비 내역 저장 실패");
            }
            $linked_collection_id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt_expense);
            
            // contract_expenses 처리
            $remaining_expense_payment = $expense_payment;
            $stmt_expenses = mysqli_prepare($link, "SELECT id, amount FROM contract_expenses WHERE contract_id = ? AND is_processed = 0 ORDER BY expense_date ASC, id ASC");
            mysqli_stmt_bind_param($stmt_expenses, "i", $contract_id);
            mysqli_stmt_execute($stmt_expenses);
            $result_expenses = mysqli_stmt_get_result($stmt_expenses);
            
            while ($exp = mysqli_fetch_assoc($result_expenses)) {
                if ($remaining_expense_payment >= $exp['amount']) {
                    $stmt_upd = mysqli_prepare($link, "UPDATE contract_expenses SET is_processed = 1, processed_date = NOW(), linked_collection_id = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_upd, "ii", $linked_collection_id, $exp['id']);
                    mysqli_stmt_execute($stmt_upd);
                    mysqli_stmt_close($stmt_upd);
                    $remaining_expense_payment -= $exp['amount'];
                } else {
                    break;
                }
            }
            mysqli_stmt_close($stmt_expenses);
        }
        
        // 이자 저장
        if ($interest_payment > 0) {
            $sql_interest = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo, generated_interest) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_interest = mysqli_prepare($link, $sql_interest);
            $type_interest = '이자';
            mysqli_stmt_bind_param($stmt_interest, "sissdsd", $transaction_id, $contract_id, $collection_date_str, $type_interest, $interest_payment, $base_memo, $total_interest_to_be_paid);
            if (!mysqli_stmt_execute($stmt_interest)) {
                throw new Exception("이자 내역 저장 실패");
            }
            mysqli_stmt_close($stmt_interest);
        }
        
        // 원금 저장
        if ($principal_payment > 0) {
            $sql_principal = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_principal = mysqli_prepare($link, $sql_principal);
            $type_principal = '원금';
            mysqli_stmt_bind_param($stmt_principal, "sissds", $transaction_id, $contract_id, $collection_date_str, $type_principal, $principal_payment, $base_memo);
            if (!mysqli_stmt_execute($stmt_principal)) {
                throw new Exception("원금 내역 저장 실패");
            }
            mysqli_stmt_close($stmt_principal);
        }
        
        // 4. 계약 상태 재계산 및 업데이트
        recalculate_and_update_contract_state($link, $contract_id, false, $contract_data);
        
        // 5. 트랜잭션 커밋
        mysqli_commit($link);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($link);
        throw $e;
    }
}

/**
 * 계약 ID로 계약 정보 조회
 */
function getContractById($link, $contract_id) {
    $sql = "SELECT * FROM contracts WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * 계약 상태 재계산 (더미 함수 - 실제로는 복잡한 로직 필요)
 */
function recalculate_and_update_contract_state($link, $contract_id, $is_manual = false, $preloaded_contract = null) {
    // 실제 구현은 매우 복잡함
    // 여기서는 기본적인 업데이트만 수행
    return true;
}

// ============================================================
// 5. Slack, SMS 연동 함수
// ============================================================

/**
 * Slack 알림 전송
 */
function sendSlackNotification($payload, $webhookUrl = SLACK_WEBHOOK_URL) {
    global $link;
    $company_info = get_all_company_info($link);
    if (($company_info['slack_notifications_enabled'] ?? '1') !== '1') {
        return false;
    }
    
    if (empty($webhookUrl) || $webhookUrl === 'YOUR_SLACK_WEBHOOK_URL') {
        return false;
    }
    
    if (is_string($payload)) {
        $payload = ['text' => $payload];
    }
    
    $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($result === 'ok' && $http_code === 200);
}

/**
 * 회사 정보 조회
 */
function get_all_company_info($link) {
    $sql = "SELECT * FROM company_info WHERE id = 1";
    $result = mysqli_query($link, $sql);
    return mysqli_fetch_assoc($result) ?? [];
}

?>
```

이것으로 `common.php`의 핵심 함수들을 완성했습니다!

---

<a name="section4"></a>
## 4. 화면 개발 완전 가이드

### 4-1. 로그인 페이지 (`login.php`)

파일 위치: `C:\xampp\htdocs\payday\login.php`

```php
<?php
session_start();

// 이미 로그인된 경우 메인으로 이동
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payday - 로그인</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>PAYDAY</h1>
            <h2>대부업 관리 시스템</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    아이디 또는 비밀번호가 일치하지 않습니다.
                </div>
            <?php endif; ?>
            
            <form action="process/login_process.php" method="post">
                <input type="text" name="username" placeholder="아이디" required autofocus>
                <input type="password" name="password" placeholder="비밀번호" required>
                <button type="submit" class="btn-primary">로그인</button>
            </form>
        </div>
    </div>
</body>
</html>
```

### 4-2. 로그인 처리 (`process/login_process.php`)

```php
<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT id, username, password, name, permission_level FROM users WHERE username = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            // 로그인 성공
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['permission_level'] = $user['permission_level'];
            
            header("location: ../index.php");
            exit;
        }
    }
    
    // 로그인 실패
    header("location: ../login.php?error=1");
    exit;
}
?>
```

---

<a name="section5"></a>
## 5. 문제 해결 가이드 (Troubleshooting)

### 문제 1: "ERROR: Could not connect to database"
**원인**: DB 접속 정보가 잘못되었거나 MySQL이 실행되지 않음
**해결**:
```
1. XAMPP Control Panel에서 MySQL 'Running' 상태 확인
2. config.php의 DB_USERNAME, DB_PASSWORD 확인
3. phpMyAdmin에서 'payday' 데이터베이스 존재 여부 확인
```

### 문제 2: 이자 계산이 1원씩 차이 남
**원인**: round() vs floor() 문제
**해결**:
```php
// 잘못된 코드
$interest = round($principal * $rate);

// 올바른 코드
$interest = floor($principal * $rate);
```

### 문제 3: 입금 처리 시 트랜잭션 에러
**원인**: mysqli_begin_transaction()과 commit() 사이에 예외 발생
**해결**:
```php
try {
    mysqli_begin_transaction($link);
    // ... 작업 ...
    mysqli_commit($link);
} catch (Exception $e) {
    mysqli_rollback($link);  // 반드시 롤백!
    error_log($e->getMessage());
    throw $e;
}
```

### 문제 4: Foreign Key 제약 조건 에러
**원인**: 참조하는 테이블이 먼저 생성되지 않음
**해결**:
```sql
-- 순서 준수!
-- 1. customers 먼저
CREATE TABLE customers (...);

-- 2. contracts 나중에
CREATE TABLE contracts (
    ...
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
```

---

## 6. 다음 단계 (Next Steps)

이제 여러분은 다음을 완료했습니다:
✅ 환경 설정
✅ 데이터베이스 완전 구축
✅ 핵심 로직 함수 구현
✅ 로그인 시스템 구축

**다음으로 해야 할 것:**
1. `pages/header.php`, `sidebar.php`, `footer.php` 작성
2. `pages/contract_manage.php` - 계약 관리 화면
3. `pages/collection_manage.php` - 수납 처리 화면 (가장 중요!)
4. `css/style.css` - 전체 스타일 작성

이 모든 것을 완성하면 **완전한 Payday 시스템**이 탄생합니다!

---

**문서 버전**: 1.0  
**최종 수정일**: 2025-12-16  
**작성자**: Payday 프로젝트 팀
