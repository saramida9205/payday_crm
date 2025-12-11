<?php
require_once '../common.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// 최고 관리자만 접근 가능
if ($_SESSION['permission_level'] != 0) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_holidays') {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    // Fetch explicit exceptions
    $sql = "SELECT holiday_date, type FROM holidays WHERE YEAR(holiday_date) = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $holidays = []; // Dates that are holidays (either explicit or default)
    $workdays = []; // Dates that are workdays (explicit exceptions)
    
    // 1. Load DB exceptions
    $db_exceptions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $db_exceptions[$row['holiday_date']] = $row['type'];
    }
    mysqli_stmt_close($stmt);

    // 2. Generate full calendar status for the year (to simplify frontend logic)
    // Actually, frontend just needs to know which dates to mark as Red.
    // Red = (Weekend AND NOT workday_exception) OR (Weekday AND holiday_exception)
    
    // Let's just return the DB exceptions and let frontend decide?
    // Or better, return a list of ALL holidays for the year so frontend is dumb.
    
    $all_holidays = [];
    
    $start = new DateTime("$year-01-01");
    $end = new DateTime("$year-12-31");
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($period as $dt) {
        $date_str = $dt->format('Y-m-d');
        $w = $dt->format('w');
        $is_weekend = ($w == 0 || $w == 6);
        
        $type = $db_exceptions[$date_str] ?? null;
        
        $is_holiday = false;
        if ($type === 'workday') {
            $is_holiday = false;
        } elseif ($type === 'holiday') {
            $is_holiday = true;
        } else {
            $is_holiday = $is_weekend;
        }
        
        if ($is_holiday) {
            $all_holidays[] = $date_str;
        }
    }
    
    echo json_encode(['success' => true, 'holidays' => $all_holidays]);
    exit;
}

if ($action === 'toggle_holiday') {
    $date = $_POST['date'] ?? '';
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => '날짜가 지정되지 않았습니다.']);
        exit;
    }

    // 1. Determine default status
    $w = date('w', strtotime($date));
    $is_weekend = ($w == 0 || $w == 6);
    
    // 2. Check current DB status
    $check_sql = "SELECT type FROM holidays WHERE holiday_date = ?";
    $check_stmt = mysqli_prepare($link, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $date);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($result);
    $current_db_type = $row['type'] ?? null;
    mysqli_stmt_close($check_stmt);

    $new_is_holiday = false;

    if ($current_db_type) {
        // If entry exists, delete it (restore default)
        // Logic: 
        // - If it was 'holiday' (on a weekday), deleting it makes it a workday (default).
        // - If it was 'workday' (on a weekend), deleting it makes it a holiday (default).
        $del_sql = "DELETE FROM holidays WHERE holiday_date = ?";
        $del_stmt = mysqli_prepare($link, $del_sql);
        mysqli_stmt_bind_param($del_stmt, "s", $date);
        mysqli_stmt_execute($del_stmt);
        mysqli_stmt_close($del_stmt);
        
        $new_is_holiday = $is_weekend; // Restored to default
    } else {
        // If no entry, insert exception
        if ($is_weekend) {
            // Weekend -> Make it a Workday
            $type = 'workday';
            $desc = '주말 근무';
            $new_is_holiday = false;
        } else {
            // Weekday -> Make it a Holiday
            $type = 'holiday';
            $desc = '임시 공휴일';
            $new_is_holiday = true;
        }
        
        $ins_sql = "INSERT INTO holidays (holiday_date, type, description) VALUES (?, ?, ?)";
        $ins_stmt = mysqli_prepare($link, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, "sss", $date, $type, $desc);
        mysqli_stmt_execute($ins_stmt);
        mysqli_stmt_close($ins_stmt);
    }

    echo json_encode(['success' => true, 'is_holiday' => $new_is_holiday]);
    exit;
}

echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
?>
