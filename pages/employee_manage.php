<?php 
    include('../process/employee_process.php');
    include('header.php'); 

    // Fetch employees
    $employees = getEmployees($link);

    function get_permission_display($level) {
        switch ($level) {
            case 0:
                return '<span style="color: red; font-weight: bold;">최고관리자</span>';
            case 1:
                return '<span style="color: blue;">일반직원</span>';
            default:
                return '알 수 없음';
        }
    }
?>

<h2>직원관리</h2>

<?php if (isset($_SESSION['message'])): ?>
	<div class="alert alert-success">
		<?php 
			echo $_SESSION['message']; 
			unset($_SESSION['message']);
		?>
	</div>
<?php endif ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>이름</th>
                <th>아이디</th>
                <th>권한레벨</th>
                <th colspan="2">Action</th>
            </tr>
        </thead>
        
        <tbody>
        <?php foreach ($employees as $row) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo get_permission_display($row['permission_level']); ?></td>
                <td>
                    <a href="employee_manage.php?edit=<?php echo $row['id']; ?>#employee_form" class="btn btn-sm edit_btn">수정</a>
                </td>
                <td>
                    <a href="../process/employee_process.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm del_btn" onclick="return confirm('정말로 이 직원을 삭제하시겠습니까?');">삭제</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<hr style="margin: 30px 0;">

<div id="employee_form" class="form-container employee-form-container">
    <h3><?php echo $update ? '직원 정보 수정' : '신규 직원 추가'; ?></h3>
    <form method="post" action="">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div class="form-col" style="margin-bottom: 15px;">
            <label>이름</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label>아이디</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label>비밀번호 <?php if($update) echo '(수정시에만 입력)'; ?></label>
            <input type="password" name="password" value="" <?php if(!$update) echo 'required'; ?>>
            <small style="color: #666; font-size: 12px;">* 최소 6자 이상 입력해주세요.</small>
        </div>
        <div class="form-col" style="margin-bottom: 20px;">
            <label>권한레벨</label>
            <select name="permission_level" required>
                <option value="0" <?php if ($permission_level == 0) echo 'selected'; ?>>최고관리자</option>
                <option value="1" <?php if ($permission_level == 1) echo 'selected'; ?>>일반직원</option>
            </select>
        </div>

        <div class="form-buttons" style="text-align: right;">
            <?php if ($update == true): ?>
                <button class="btn btn-primary" type="submit" name="update">수정</button>
            <?php else: ?>
                <button class="btn btn-primary" type="submit" name="save">저장</button>
            <?php endif ?>
            <a href="employee_manage.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>