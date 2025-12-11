<?php
include('header.php');
include_once(__DIR__ . '/../common.php');

// Check permission
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    echo "<script>alert('권한이 없습니다.'); location.href='intranet.php';</script>";
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
?>

<style>
    .calendar-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr); /* 3 columns for 12 months */
        gap: 20px;
        margin-top: 20px;
    }
    .month-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        background: #fff;
    }
    .month-title {
        text-align: center;
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 1.1em;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        font-size: 0.9em;
    }
    .calendar-header {
        font-weight: bold;
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }
    .calendar-day {
        padding: 8px 0;
        cursor: pointer;
        border-radius: 4px;
    }
    .calendar-day:hover {
        background-color: #f0f0f0;
    }
    .calendar-day.holiday {
        color: red;
        font-weight: bold;
    }
    .calendar-day.weekend {
        color: #888; /* Visual cue for weekends, though logic relies on DB/calc */
    }
    .calendar-day.empty {
        cursor: default;
    }
    .calendar-day.empty:hover {
        background-color: transparent;
    }
    
    @media (max-width: 1200px) {
        .calendar-container { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .calendar-container { grid-template-columns: 1fr; }
    }
</style>

<h2>휴일 관리 (<?php echo $year; ?>년)</h2>

<div style="text-align: center; margin-bottom: 20px;">
    <a href="?year=<?php echo $year - 1; ?>" class="btn btn-secondary">&lt; 이전 해</a>
    <span style="font-size: 1.5em; font-weight: bold; margin: 0 20px;"><?php echo $year; ?></span>
    <a href="?year=<?php echo $year + 1; ?>" class="btn btn-secondary">다음 해 &gt;</a>
</div>

<div class="info-box">
    <ul style="margin-bottom: 0;">
        <li>날짜를 클릭하여 휴일 여부를 토글할 수 있습니다.</li>
        <li><span style="color: red; font-weight: bold;">빨간색</span> 날짜는 휴일로 지정된 날입니다.</li>
        <li><span style="color: black; font-weight: bold;">검정색</span> 날짜는 평일입니다.</li>
    </ul>
</div>

<div class="calendar-container">
    <?php
    for ($m = 1; $m <= 12; $m++) {
        $firstDayOfMonth = mktime(0, 0, 0, $m, 1, $year);
        $numberDays = date('t', $firstDayOfMonth);
        $dateComponents = getdate($firstDayOfMonth);
        $dayOfWeek = $dateComponents['wday']; // 0 (Sunday) - 6 (Saturday)
        
        echo '<div class="month-card">';
        echo '<div class="month-title">' . $m . '월</div>';
        echo '<div class="calendar-grid">';
        
        // Headers
        $daysOfWeek = ['일', '월', '화', '수', '목', '금', '토'];
        foreach ($daysOfWeek as $day) {
            $color = ($day == '일') ? 'color: red;' : (($day == '토') ? 'color: blue;' : '');
            echo '<div class="calendar-header" style="' . $color . '">' . $day . '</div>';
        }
        
        // Empty slots before first day
        for ($i = 0; $i < $dayOfWeek; $i++) {
            echo '<div class="calendar-day empty"></div>';
        }
        
        // Days
        for ($d = 1; $d <= $numberDays; $d++) {
            $currentDate = sprintf('%04d-%02d-%02d', $year, $m, $d);
            $dayProp = date('w', mktime(0,0,0,$m,$d,$year));
            $isWeekend = ($dayProp == 0 || $dayProp == 6);
            
            echo '<div class="calendar-day" data-date="' . $currentDate . '" id="day-' . $currentDate . '">';
            echo $d;
            echo '</div>';
            
            $dayOfWeek++;
            if ($dayOfWeek == 7) {
                $dayOfWeek = 0;
            }
        }
        
        // Empty slots after last day (optional, for grid completeness)
        if ($dayOfWeek != 0) {
            for ($i = $dayOfWeek; $i < 7; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
        }
        
        echo '</div>'; // End calendar-grid
        echo '</div>'; // End month-card
    }
    ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const year = <?php echo $year; ?>;
    
    // Load holidays
    fetch(`../process/holiday_process.php?action=get_holidays&year=${year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.holidays.forEach(date => {
                    const el = document.getElementById(`day-${date}`);
                    if (el) {
                        el.classList.add('holiday');
                    }
                });
            } else {
                alert('휴일 정보를 불러오는데 실패했습니다: ' + data.message);
            }
        })
        .catch(err => console.error(err));
        
    // Toggle holiday
    document.querySelectorAll('.calendar-day:not(.empty)').forEach(dayEl => {
        dayEl.addEventListener('click', function() {
            const date = this.dataset.date;
            
            const formData = new FormData();
            formData.append('action', 'toggle_holiday');
            formData.append('date', date);
            
            fetch('../process/holiday_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.is_holiday) {
                        this.classList.add('holiday');
                    } else {
                        this.classList.remove('holiday');
                    }
                } else {
                    alert('처리 실패: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('서버 통신 오류');
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>
