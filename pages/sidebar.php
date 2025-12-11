<?php
// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: ../login.php");
    exit;
}

// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define the menu items
$menu_items = [
    'intranet.php' => '인트라넷',
    'employee_manage.php' => '직원관리',
    'customer_manage.php' => '고객관리',
    'contract_manage.php' => '계약관리',
    'collection_manage.php' => '회수관리',
    'condition_change_manage.php' => '조건변경관리',
    'transaction_manage.php' => '입출금관리',
    'reports.php' => '보고서',
    'daily_report.php' => '업무일보',
    'sms.php' => 'SMS문자'
];

?>
<div class="sidebar">
    <ul>
        <?php foreach ($menu_items as $url => $title): ?>
            <li class="<?php echo ($current_page == $url) ? 'active' : ''; ?>">
                <a href="<?php echo $url; ?>"><?php echo $title; ?></a>
            </li>
        <?php endforeach; ?>
        <li>
            <a href="../process/logout_process.php">로그아웃</a>
        </li>
    </ul>
</div>