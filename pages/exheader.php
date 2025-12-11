<?php
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
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Payday CRM</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

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
                if(mobileHeader) mobileHeader.style.display = 'flex';
            } else {
                if(mobileHeader) mobileHeader.style.display = 'none';
                if(sidebar) sidebar.classList.remove('active');
                if(sidebarOverlay) sidebarOverlay.classList.remove('active');
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
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    weekday: 'short',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
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
        }, 5000);
    });
</script>