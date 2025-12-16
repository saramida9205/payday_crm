<?php

require_once __DIR__ . '/../common.php';

// --- Development Error Reporting ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/php_errors.log'); // Log errors to a file in the parent directory

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

// 싱글모드 확인
$is_single_mode = isset($_GET['contract_id']) && !empty($_GET['contract_id']) && isset($_GET['mod']) && $_GET['mod'] == 'single';

//회사정보가져오기
$company_info = get_all_company_info($link);
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company_info['company_name'] ?? ''); ?> CRM</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Admin Submenu Styles */
        .submenu {
            list-style: none;
            padding-left: 20px;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .submenu li a {
            font-size: 0.9em;
            padding: 8px 15px;
        }

        .toggle-icon {
            float: right;
            transition: transform 0.3s ease;
            font-size: 0.8em;
        }

        .has-submenu.open .toggle-icon {
            transform: rotate(180deg);
        }
    </style>
</head>

<body>
    <?php if (!$is_single_mode): ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div> <!-- Overlay for mobile -->

        <div class="main-container"> <!-- This container wraps both sidebar and content -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <!--                <a href="intranet.php" class="sidebar-header">Payday CRM</a>  -->
                    <a href="intranet.php" class="sidebar-logo"><img src="../uploads/company/logo.png" style="width: 90%; height: 40px;" alt="CRM"></a>
                </div>
                <div id="sidebar-time-display" style="padding: 10px 15px; color: #e0e0e0; font-size: 13px; text-align: center; border-bottom: 1px solid #444;">
                    <!-- Current time will be inserted here by JavaScript -->
                </div>
                <div class="user-info">
                    <p><strong><?php echo htmlspecialchars($_SESSION["name"]); ?></strong>님</p>
                    <a href="../process/logout_process.php" class="logout-btn">로그아웃</a>
                </div>
                <ul class="nav">
                    <li><a href="intranet.php" class="<?php echo in_array($current_page, ['intranet.php']) ? 'active' : ''; ?>">인트라넷</a></li>
                    <li><a href="customer_manage.php" class="<?php echo in_array($current_page, ['customer_manage.php', 'customer_detail.php']) ? 'active' : ''; ?>">고객관리</a></li>
                    <li><a href="contract_manage.php" class="<?php echo in_array($current_page, ['contract_manage.php', 'transaction_ledger.php', 'expected_interest_view.php']) ? 'active' : ''; ?>">계약관리</a></li>
                    <li><a href="collection_manage.php" class="<?php echo in_array($current_page, ['collection_manage.php', 'collection_trash.php']) ? 'active' : ''; ?>">회수관리</a></li>
                    <li><a href="transaction_manage.php" class="<?php echo in_array($current_page, ['transaction_manage.php']) ? 'active' : ''; ?>">입출금관리</a></li>
                    <li><a href="reports.php" class="<?php echo in_array($current_page, ['reports.php']) ? 'active' : ''; ?>">보고서</a></li>
                    <li><a href="daily_report.php" class="<?php echo in_array($current_page, ['daily_report.php']) ? 'active' : ''; ?>">업무일보</a></li>
                    <li><a href="bond_ledger.php" class="<?php echo in_array($current_page, ['bond_ledger.php']) ? 'active' : ''; ?>">채권원장</a></li>
                    <li><a href="sms.php" class="<?php echo in_array($current_page, ['sms.php', 'sms_log.php']) ? 'active' : ''; ?>">SMS발송</a></li>
                    <li><a href="sms_log.php" class="<?php echo in_array($current_page, ['sms_log.php']) ? 'active' : ''; ?>">SMS발송내역</a></li>
                    <li><a href="certificate_print.php" class="<?php echo in_array($current_page, ['certificate_print.php']) ? 'active' : ''; ?>">증명서인쇄</a></li>
                    <li><a href="manual.php?mod=single" class="<?php echo in_array($current_page, ['manual.php']) ? 'active' : ''; ?>">사용설명서</a></li>
                    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                        <?php
                        $admin_pages = ['bond_ledger_history.php', 'employee_manage.php', 'condition_change_manage.php', 'backup_manage.php', 'holiday_manage.php', 'settings.php', 'company_images.php'];
                        $is_admin_active = in_array($current_page, $admin_pages);
                        ?>
                        <li class="has-submenu <?php echo $is_admin_active ? 'open' : ''; ?>">
                            <a href="#" id="admin-menu-toggle">
                                관리자메뉴 <span class="toggle-icon">▼</span>
                            </a>
                            <ul class="submenu" id="admin-submenu" style="display: <?php echo $is_admin_active ? 'block' : 'none'; ?>;">
                                <li><a href="bond_ledger_history.php" class="<?php echo in_array($current_page, ['bond_ledger_history.php']) ? 'active' : ''; ?>">백업 채권원장</a></li>
                                <li><a href="employee_manage.php" class="<?php echo in_array($current_page, ['employee_manage.php']) ? 'active' : ''; ?>">직원관리</a></li>
                                <li><a href="condition_change_manage.php" class="<?php echo in_array($current_page, ['condition_change_manage.php']) ? 'active' : ''; ?>">조건변경관리</a></li>
                                <li><a href="backup_manage.php" class="<?php echo in_array($current_page, ['backup_manage.php']) ? 'active' : ''; ?>">데이터베이스관리</a></li>
                                <li><a href="holiday_manage.php" class="<?php echo in_array($current_page, ['holiday_manage.php']) ? 'active' : ''; ?>">휴일관리</a></li>
                                <li><a href="settings.php" class="<?php echo in_array($current_page, ['settings.php']) ? 'active' : ''; ?>">시스템 설정</a></li>
                                <li><a href="company_images.php" class="<?php echo in_array($current_page, ['company_images.php']) ? 'active' : ''; ?>">회사관련이미지</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($is_single_mode): ?>
            <div class="single-mode-content"> <!-- This class should match the CSS -->
            <?php else: ?>
                <div class="main-content"> <!-- This class should match the CSS -->
                <?php endif; ?>
                <!-- Mobile Header (Visible only on mobile) -->
                <div class="mobile-header" style="display: none;">
                    <button class="hamburger-btn" id="hamburgerBtn">☰</button>
                    <span class="mobile-title"><?php echo htmlspecialchars($company_info['company_name'] ?? ''); ?> CRM</span>
                    <div style="width: 24px;"></div> <!-- Spacer for centering -->
                </div>

                <div class="content-inner">
                    <?php
                    if (isset($_SESSION['message'])) {
                        echo '<div class="alert alert-success">' . $_SESSION['message'] . '</div>';
                        unset($_SESSION['message']);
                    }
                    if (isset($_SESSION['error_message'])) {
                        echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
                        unset($_SESSION['error_message']);
                    }
                    ?>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // --- Mobile Sidebar Toggle ---
                            const hamburgerBtn = document.getElementById('hamburgerBtn');
                            const sidebar = document.getElementById('sidebar');
                            const sidebarOverlay = document.getElementById('sidebarOverlay');
                            const mobileHeader = document.querySelector('.mobile-header');

                            // Check if we are on mobile to show/hide elements
                            function checkMobile() {
                                if (window.innerWidth <= 768) {
                                    if (mobileHeader) mobileHeader.style.display = 'flex';
                                } else {
                                    if (mobileHeader) mobileHeader.style.display = 'none';
                                    if (sidebar) sidebar.classList.remove('active');
                                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                                }
                            }

                            // Initial check and resize listener
                            checkMobile();
                            window.addEventListener('resize', checkMobile);

                            if (hamburgerBtn && sidebar && sidebarOverlay) {
                                hamburgerBtn.addEventListener('click', function() {
                                    sidebar.classList.toggle('active');
                                    sidebarOverlay.classList.toggle('active');
                                });

                                sidebarOverlay.addEventListener('click', function() {
                                    sidebar.classList.remove('active');
                                    sidebarOverlay.classList.remove('active');
                                });
                            }

                            // --- Sidebar Time Display ---
                            function updateSidebarTime() {
                                const timeDisplay = document.getElementById('sidebar-time-display');
                                if (timeDisplay) {
                                    const now = new Date();
                                    const options = {
                                        year: 'numeric',
                                        month: '2-digit',
                                        day: '2-digit',
                                        weekday: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                        hour12: false
                                    };
                                    timeDisplay.textContent = now.toLocaleString('ko-KR', options).replace(/\. /g, '.');
                                }
                            }
                            updateSidebarTime();
                            setInterval(updateSidebarTime, 1000);

                            // --- Auto-hide alerts after 5 seconds ---
                            setTimeout(function() {
                                let alerts = document.querySelectorAll('.alert');
                                alerts.forEach(function(alert) {
                                    alert.style.transition = 'opacity 0.5s';
                                    alert.style.opacity = '0';
                                    setTimeout(function() {
                                        alert.style.display = 'none';
                                    }, 500);
                                });
                            }, 3000);

                            // --- Admin Menu Toggle ---
                            const adminToggle = document.getElementById('admin-menu-toggle');
                            const adminSubmenu = document.getElementById('admin-submenu');
                            const adminLi = document.querySelector('.has-submenu');

                            if (adminToggle && adminSubmenu) {
                                adminToggle.addEventListener('click', function(e) {
                                    e.preventDefault(); // Prevent default anchor behavior

                                    const isOpen = adminSubmenu.style.display === 'block';

                                    if (isOpen) {
                                        adminSubmenu.style.display = 'none';
                                        if (adminLi) adminLi.classList.remove('open');
                                    } else {
                                        adminSubmenu.style.display = 'block';
                                        if (adminLi) adminLi.classList.add('open');
                                    }
                                });
                            }
                        });
                    </script>