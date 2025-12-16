# Payday 총괄 제작 가이드 (Comprehensive Construction Guide)

## 📋 문서 개요
본 문서는 Payday 시스템을 실제로 개발하는 전 과정을 총괄하는 마스터 플랜입니다. **기획서**와 **기술설명서**를 기반으로, 어떤 순서로 무엇을 만들어야 하는지, 각 단계의 핵심 포인트와 주의사항을 상세히 안내합니다.

---

## 📍 개발 로드맵 (Development Roadmap)

### 전체 프로세스 개요
```
[PHASE 1] 환경 구축
    ↓
[PHASE 2] 데이터베이스 구축
    ↓
[PHASE 3] 핵심 로직 개발 (common.php)
    ↓
[PHASE 4] 인증 시스템
    ↓
[PHASE 5] UI 공통 레이아웃
    ↓
[PHASE 6] 핵심 기능 페이지 개발
    ↓
[PHASE 7] 부가 기능 및 통합
    ↓
[PHASE 8] 테스트 및 최적화
    ↓
[PHASE 9] 운영 준비
```

---

## PHASE 1: 환경 구축 (Environment Setup)

### 1-1. 로컬 개발 환경 설치
#### Windows (XAMPP 사용)
```bash
1. XAMPP 다운로드 및 설치 (https://www.apachefriends.org)
2. C:\xampp\htdocs\payday 폴더 생성
3. XAMPP Control Panel에서 Apache, MySQL 시작
```

#### Mac (MAMP 사용)
```bash
1. MAMP 다운로드 및 설치
2. /Applications/MAMP/htdocs/payday 폴더 생성
3. MAMP 실행 및 서버 시작
```

#### Linux (Native)
```bash
# Apache, PHP,  MySQL 설치
sudo apt update
sudo apt install apache2 php php-mysqli php-curl php-mbstring mysql-server

# 프로젝트 폴더 생성
sudo mkdir -p /var/www/html/payday
sudo chown -R www-data:www-data /var/www/html/payday
```

### 1-2. 디렉토리 구조 생성
```bash
cd /path/to/payday
mkdir -p css js pages process uploads/contracts uploads/company backup templates
```

### 1-3. 권한 설정 (Linux/Mac)
```bash
chmod 755 payday
chmod 775 uploads backup
```

---

## PHASE 2: 데이터베이스 구축 (Database Construction)

### 2-1. 데이터베이스 생성 및 문자셋 설정
```sql
CREATE DATABASE payday CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE payday;
```

### 2-2. 테이블 생성 순서 (의존성 고려)

#### 순서가 중요한 이유
- Foreign Key 제약 조건 때문에 참조되는 테이블이 먼저 존재해야 함

#### 1단계: 독립 테이블 (참조 없음)
```sql
-- 1. 회사 정보
CREATE TABLE company_info (...);

-- 2. 휴일
CREATE TABLE holidays (...);

-- 3. 사용자 (관리자)
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100),
  permission_level ENUM('user','admin','superadmin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 2단계: 고객 테이블
```sql
CREATE TABLE customers (...);
-- 인덱스 생성
CREATE INDEX idx_name ON customers(name);
CREATE INDEX idx_phone ON customers(phone);
```

#### 3단계: 계약 테이블 (customers 참조)
```sql
CREATE TABLE contracts (
  ...
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);
```

#### 4단계: 종속 테이블들
```sql
-- 수납 (contracts 참조)
CREATE TABLE collections (...);

-- 조건변경 (contracts 참조)
CREATE TABLE condition_changes (...);

-- 계약비용 (contracts 참조)
CREATE TABLE contract_expenses (...);

-- 스냅샷
CREATE TABLE bond_ledger_snapshots (...);

-- SMS 로그
CREATE TABLE sms_log (...);

-- SMS 템플릿
CREATE TABLE sms_templates (...);
```

### 2-3. 초기 데이터 삽입
```sql
-- 기본 관리자 계정 생성 (비밀번호: admin123)
INSERT INTO users (username, password, name, permission_level) 
VALUES ('admin', '$2y$10$...해시된비밀번호...', '시스템관리자', 'superadmin');

-- 회사 정보 기본값
INSERT INTO company_info (id, company_name, slack_notifications_enabled) 
VALUES (1, '(주)페이데이', 1);

-- 2025년 공휴일 등록
INSERT INTO holidays (holiday_date, holiday_name, type) VALUES
('2025-01-01', '신정', 'holiday'),
('2025-03-01', '삼일절', 'holiday'),
('2025-05-05', '어린이날', 'holiday'),
...
```

---

## PHASE 3: 핵심 로직 개발 (Core Logic - common.php)

### 3-1. config.php 작성
```php
<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'payday');

// API Keys
define('WIDESHOT_API_URL', 'https://api.wideshot.co.kr');
define('WIDESHOT_API_KEY', 'YOUR_API_KEY_HERE');

// Database connection
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

mysqli_set_charset($link, "utf8mb4");
?>
```

### 3-2. common.php 핵심 함수 작성 순서

#### 1순위: 기본 유틸리티 함수
```php
// 윤년 확인
function is_leap_year($year) { ... }

// 휴일 확인
function isHoliday($date_str) { ... }

// 상태 표시 HTML
function get_status_display($status) { ... }
```

#### 2순위: 금리 관련 함수
```php
// 금리 이력 조회 (조건변경 포함)
function get_interest_rate_history($link, $contract_id, $contract) {
    // 1. contracts 테이블에서 초기 금리 가져오기
    // 2. condition_changes 테이블에서 변경 이력 조회
    // 3. 날짜순 정렬하여 배열로 반환
}
```

#### 3순위: 이자 계산 함수 (가장 중요!)
```php
function calculateAccruedInterestForPeriod(
    $link, $contract, $principal, 
    $start_date_str, $end_date_str, $due_date_str
) {
    // ⚠️ 이 함수가 시스템의 핵심!
    
    // STEP1: 날짜 객체 생성
    $start_date = new DateTime($start_date_str);
    $end_date = new DateTime($end_date_str);
    $due_date = new DateTime($due_date_str);
    
    // STEP2: 금리 이력 조회
    $rate_history = get_interest_rate_history($link, $contract['id'], $contract);
    
    // STEP3: 계산 체크포인트 설정 (시작일, 금리변경일, 약정일, 종료일)
    $checkpoints = [$start_date];
    if ($due_date > $start_date && $due_date < $end_date) {
        $checkpoints[] = $due_date;
    }
    foreach ($rate_history as $change) {
        $change_date = new DateTime($change['start_date']);
        if ($change_date > $start_date && $change_date < $end_date) {
            $checkpoints[] = $change_date;
        }
    }
    $checkpoints[] = $end_date;
    
    // 중복 제거 및 정렬
    $unique_checkpoints = array_unique($checkpoints);
    usort($unique_checkpoints, function($a, $b) {
        return $a <=> $b;
    });
    
    // STEP4: 구간별 이자 계산
    $normal_interest = 0;
    $overdue_interest = 0;
    
    for ($i = 0; $i < count($unique_checkpoints) - 1; $i++) {
        $period_start = $unique_checkpoints[$i];
        $period_end = $unique_checkpoints[$i + 1];
        
        // 일수 계산
        $days = $period_end->diff($period_start)->days;
        
        // 해당 구간의 금리 찾기
        $current_rates = $rate_history[0];
        foreach ($rate_history as $change) {
            if ($period_start->format('Y-m-d') >= $change['start_date']) {
                $current_rates = $change;
            }
        }
        
        // 일별 루프 (윤년 고려)
        $temp_date = clone $period_start;
        for ($d = 0; $d < $days; $d++) {
            $year = (int)$temp_date->format('Y');
            $days_in_year = is_leap_year($year) ? 366 : 365;
            
            // 정상 이자 계산
            $daily_rate = $current_rates['interest_rate'] / 100 / $days_in_year;
            $normal_interest += $principal * $daily_rate;
            
            // 연체 이자 계산 (약정일 경과 시)
            if ($temp_date >= $due_date) {
                $penalty_rate = ($current_rates['overdue_rate'] - $current_rates['interest_rate']) / 100 / $days_in_year;
                $overdue_interest += $principal * $penalty_rate;
            }
            
            $temp_date->modify('+1 day');
        }
    }
    
    // STEP5: 반환 (원 미만 버림)
    return [
        'normal' => floor($normal_interest),
        'overdue' => floor($overdue_interest),
        'total' => floor($normal_interest + $overdue_interest)
    ];
}
```

#### 4순위: 수납 처리 함수
```php
function process_collection(
    $link, $contract_id, $collection_date_str, $total_amount,
    $expense_payment, $interest_payment, $principal_payment,
    $memo, $expense_memo, $transaction_id
) {
    // 트랜잭션 시작
    mysqli_begin_transaction($link);
    
    try {
        // 1. 유효성 검사
        // 2. 현재 계약 상태 조회
        // 3. 발생 이자 계산
        // 4. 자동 분개
        // 5. collections 테이블에 저장 (경비/이자/원금 각각)
        // 6. contracts 테이블 업데이트
        // 7. contract_expenses 처리 (경비 수납 시)
        
        mysqli_commit($link);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($link);
        throw $e;
    }
}
```

### 3-3. 개발 시 주의사항
❗ **DECIMAL 타입 사용**: 금액은 절대 FLOAT 사용 금지
❗ **PreparedStatement 필수**: SQL Injection 방지
❗ **트랜잭션 범위**: 수납 처리는 반드시 트랜잭션 내에서
❗ **윤년 처리**: 2월 29일 정확히 계산
❗ **금액 버림**: floor() 사용, round()아님

---

## PHASE 4: 인증 시스템 (Authentication)

### 4-1. login.php 작성
```php
<?php
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payday - 로그인</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h2>Payday 로그인</h2>
        <form action="process/login_process.php" method="post">
            <input type="text" name="username" placeholder="아이디" required>
            <input type="password" name="password" placeholder="비밀번호" required>
            <button type="submit">로그인</button>
        </form>
    </div>
</body>
</html>
```

### 4-2. process/login_process.php
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
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['permission_level'] = $user['permission_level'];
            
            header("location: ../index.php");
            exit;
        }
    }
    
    header("location: ../login.php?error=1");
}
?>
```

---

## PHASE 5: UI 공통 레이아웃 (Layout Components)

### 5-1. pages/header.php
```php
<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

require_once dirname(__DIR__) . '/common.php';

$page_title = '페이데이';
$current_page = basename($_SERVER['PHP_SELF']);
$is_single_mode = isset($_GET['contract_id']) && !empty($_GET['contract_id']) 
                  && isset($_GET['mod']) && $_GET['mod'] == 'single';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
```

### 5-2. pages/sidebar.php
```php
<div class="sidebar">
    <div class="logo">
        <h2>PAYDAY</h2>
    </div>
    <nav class="menu">
        <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="icon-dashboard"></i> 대시보드
        </a>
        <a href="contract_manage.php">계약 관리</a>
        <a href="collection_manage.php">수납 관리</a>
        <a href="customer_manage.php">고객 관리</a>
        <a href="transaction_ledger.php">거래원장</a>
        <a href="bond_ledger.php">채권원장</a>
        <a href="sms.php">SMS 발송</a>
        <a href="reports.php">보고서</a>
        <a href="settings.php">설정</a>
        <a href="../process/logout.php">로그아웃</a>
    </nav>
</div>
```

### 5-3. css/style.css 핵심 스타일
```css
/* 레이아웃 */
body {
    margin: 0;
    font-family: 'Noto Sans KR', sans-serif;
    display: flex;
}

.sidebar {
    width: 250px;
    background: #2c3e50;
    color: white;
    height: 100vh;
    position: fixed;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    width: calc(100% - 250px);
}

/* 반응형 (모바일) */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: static;
    }
    .main-content {
        margin-left: 0;
        width: 100%;
    }
}
```

---

## PHASE 6: 핵심 기능 페이지 개발

### 개발 우선순위
1️⃣ **계약 관리** (contract_manage.php) - 가장 기본
2️⃣ **수납 관리** (collection_manage.php) - 핵심 기능
3️⃣ **고객 관리** (customer_manage.php)
4️⃣ **거래원장** (transaction_ledger.php)
5️⃣ **SMS 발송** (sms.php)
6️⃣ **보고서** (reports.php)

### 6-1. 계약 관리 개발 포인트
- **신규 계약 입력 폼**: 고객 선택(AJAX Autocomplete), 금액 천단위 콤마
- **계약 목록**: 페이징, 필터링(상태별, 날짜별)
- **상세 보기**: 조건변경 이력, 수납 내역, 서류 목록

### 6-2. 수납 관리 개발 포인트 (가장 중요!)
```javascript
// 계약 선택 시 AJAX로 발생 이자 조회
$('#contract_selector').change(function() {
    var contract_id = $(this).val();
    var collection_date = $('#collection_date').val();
    
    $.ajax({
        url: '../process/get_accrued_interest.php',
        data: { contract_id: contract_id, collection_date: collection_date },
        success: function(response) {
            var data = JSON.parse(response);
            $('#generated_interest').text(data.total.toLocaleString() + '원');
            $('#outstanding_principal').text(data.principal.toLocaleString() + '원');
        }
    });
});
```

---

## PHASE 7: 부가 기능 및 통합

### 7-1. SMS 발송 기능
- 템플릿 선택 → 고객 필터링 → 변수 치환 → 대량 발송
- 발송 이력 DB 저장

### 7-2. 증명서 발급
- HTML 템플릿 + 인쇄 CSS
- `window.print()` 활용

### 7-3. 백업 기능
```php
// process/backup_db.php
$backup_file = '../backup/payday_' . date('Y-m-d_His') . '.sql';
$command = "mysqldump --user=" . DB_USERNAME . " --password=" . DB_PASSWORD . " --host=" . DB_SERVER . " " . DB_NAME . " > " . $backup_file;
system($command);
```

---

## PHASE 8: 테스트 및 최적화

### 8-1. 단위 테스트
**이자 계산 정확성 검증**:
- 1억원, 연 10%, 1개월 → 정확히 엑셀 계산값과 일치하는지
- 윤년 테스트: 2024-02-28 ~ 2024-03-01 계산
- 금리 변경 테스트: 중도에 금리 변경 후 이자 계산

### 8-2. 통합 테스트
**시나리오 테스트**:
1. 신규 대출 → 정상 입금 3회 → 완납
2. 신규 대출 → 부분 입금(이자 부족) → 다음 입금 시 부족금 우선 변제
3. 연체 발생 → SMS 발송 → 연체 이자 계산 → 입금 처리

### 8-3. 성능 최적화
```sql
-- 슬로우 쿼리 최적화
EXPLAIN SELECT * FROM contracts WHERE status = 'active' ORDER BY next_due_date;

-- 인덱스 추가
CREATE INDEX idx_status_due ON contracts(status, next_due_date);
```

---

## PHASE 9: 운영 준비

### 9-1. 운영 서버 배포
1. **도메인 및 SSL**: HTTPS 필수 (Let's Encrypt 무료 인증서)
2. **php.ini 설정**: `display_errors = Off`, `log_errors = On`
3. **DB 권한**: 운영 DB는 별도 계정 생성 (root 사용 금지)

### 9-2. 백업 자동화
```bash
# /etc/crontab에 추가
0 2 * * * root /usr/bin/mysqldump -u backup_user -p'password' payday > /backup/payday_$(date +\%F).sql
```

### 9-3. 모니터링 설정
- **에러 로그**: `tail -f /var/log/php_errors.log`
- **슬로우 쿼리**: `mysql> SHOW VARIABLES LIKE 'slow_query%';`

---

## 🚨 중요 주의사항 (Critical Warnings)

### ❌ 절대 금지 사항
1. **금액을 FLOAT/DOUBLE로 저장**: 부동소수점 오차 발생 → 반드시 DECIMAL
2. **SQL 문자열 직접 결합**: SQL Injection 취약 → PreparedStatement 사용
3. **트랜잭션 없이 수납 처리**: 데이터 불일치 발생 → 반드시 BEGIN ~ COMMIT
4. **비밀번호 평문 저장**: 보안 위협 → password_hash() 사용
5. **에러 메시지 노출**: 운영 서버에서 display_errors = On → 해커에게 정보 제공

### ⚠️ 반드시 확인할 것
- ✅ `uploads/` 폴더 쓰기 권한
- ✅ PHP `mysqli` 확장 모듈 활성화
- ✅ 타임존 설정: `date_default_timezone_set('Asia/Seoul');`
- ✅ 세션 보안: `session.cookie_httponly = 1`

---

## 📊 프로젝트 완성도 체크리스트

### 필수 기능 (Must Have)
- [ ] 고객 등록/조회
- [ ] 계약 생성/조회
- [ ] 수납 처리 (자동 분개)
- [ ] 이자 계산 (윤년/변동금리)
- [ ] 거래/채권 원장 조회
- [ ] 로그인/로그아웃

### 부가 기능 (Should Have)
- [ ] SMS 발송
- [ ] Slack 알림
- [ ] 증명서 발급
- [ ] 백업/복원
- [ ] 보고서 출력

### 선택 기능 (Nice to Have)
- [ ] 모바일 반응형
- [ ] 엑셀 내보내기
- [ ] 대시보드 차트

---

**문서 버전**: 1.0  
**최종 수정일**: 2025-12 -16  
**작성자**: Payday 프로젝트 팀
