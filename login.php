<?php
// Include process for login
require_once "process/login_process.php";

// Check if the user is already logged in, if yes then redirect him to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: pages/intranet.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM 로그인</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="/ico.svg" type="image/svg+xml">
</head>

<body>
    <div class="login-container">
        <div class="form-container">
            <a href="intranet.php" class="sidebar-logo">
                <center><img src="uploads/company/logo.png" style="width: 95%; height: 70px;" alt="CRM"></center>
            </a>
            <!--            <h2 style="text-align: center; margin-bottom: 25px;">Payday CRM 로그인</h2> -->
            <?php
            if (!empty($login_err)) {
                echo '<div class="alert alert-danger" style="text-align: center; margin-bottom: 15px;">' . $login_err . '</div>';
            }
            ?>
            <br>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-col">
                    <label>아이디</label>
                    <input type="text" name="username" value="<?php echo $username; ?>" required>
                </div>
                <div class="form-col">
                    <label>비밀번호</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-buttons" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">로그인</button>
                </div>
            </form>

            <?php
            // 앱(User-Agent에 'PaydayApp' 포함)이 아닌 경우에만 APK 다운로드 링크 표시
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (strpos($user_agent, 'payday CRM') === false) {
                echo '<div style="text-align: center; margin-top: 20px; font-size: 14px;">';
                echo '<a href="/payday.apk" style="color: #666; text-decoration: none; border-bottom: 1px dashed #666;">📥 앱 다운로드 (Android)</a>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</body>

</html>