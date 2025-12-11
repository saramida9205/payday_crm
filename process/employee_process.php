<?php
require_once "../common.php";

// Function to get all employees
function getEmployees($link) {
    $sql = "SELECT id, username, name, permission_level, created_at FROM employees";
    $result = mysqli_query($link, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Initialize variables
$name = $username = $password = "";
$permission_level = 0;
$name_err = $username_err = $password_err = "";
$update = false;
$id = 0;

// Add Employee
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    // Populate variables to preserve input
    $name = trim($_POST["name"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $permission_level = $_POST['permission_level'] ?? 0;
    $password = trim($_POST["password"] ?? '');

    $error_msg = "";

    // Validate name
    if (empty($name)) {
        $error_msg = "이름을 입력해주세요.";
    }
    // Validate username
    elseif (empty($username)) {
        $error_msg = "아이디를 입력해주세요.";
    } else {
        // Check if username exists
        $sql = "SELECT id FROM employees WHERE username = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $error_msg = "이미 존재하는 아이디입니다.";
                }
            } else {
                $error_msg = "시스템 오류가 발생했습니다.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if (empty($error_msg)) {
        if (empty($password)) {
            $error_msg = "비밀번호를 입력해주세요.";
        } elseif (strlen($password) < 6) {
            $error_msg = "비밀번호는 최소 6자 이상이어야 합니다.";
        }
    }

    // If no errors, insert into database
    if (empty($error_msg)) {
        $sql = "INSERT INTO employees (name, username, password, permission_level) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $username, $hashed_password, $permission_level);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "직원이 추가되었습니다.";
                header("location: ../pages/employee_manage.php");
                exit();
            } else {
                $error_msg = "데이터베이스 오류가 발생했습니다.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // If there is an error, set session and let the page continue rendering (re-populating the form)
    if (!empty($error_msg)) {
        $_SESSION['error_message'] = $error_msg;
    }
}

// Delete Employee
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($stmt = mysqli_prepare($link, "DELETE FROM employees WHERE id=?")) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = "직원이 삭제되었습니다.";
    }
    header('location: ../pages/employee_manage.php');
    exit();
}

// Get Employee for editing
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $update = true;
    if ($stmt = mysqli_prepare($link, "SELECT name, username, permission_level FROM employees WHERE id=?")) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $name, $username, $permission_level);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Update Employee
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $permission_level = $_POST['permission_level'];
    $password = trim($_POST['password']);

    // Check if the new username is already taken by another user
    $sql_check = "SELECT id FROM employees WHERE username = ? AND id != ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "si", $username, $id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['error_message'] = "해당 아이디는 이미 사용 중입니다.";
            header('location: ../pages/employee_manage.php?edit=' . $id);
            exit();
        }
        mysqli_stmt_close($stmt_check);
    }

    if (!empty($password)) {
        if (strlen($password) < 6) {
            $_SESSION['error_message'] = "비밀번호는 6자 이상이어야 합니다.";
            header('location: ../pages/employee_manage.php?edit=' . $id);
            exit();
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE employees SET name=?, username=?, permission_level=?, password=? WHERE id=?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssisi", $name, $username, $permission_level, $hashed_password, $id);
    } else {
        $sql = "UPDATE employees SET name=?, username=?, permission_level=? WHERE id=?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $name, $username, $permission_level, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "직원 정보가 수정되었습니다.";
    } else {
        $_SESSION['error_message'] = "수정 중 오류가 발생했습니다.";
    }
    mysqli_stmt_close($stmt);
    header('location: ../pages/employee_manage.php');
    exit();
}

?>