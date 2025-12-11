<?php
include 'header.php';
require_once '../common.php';

// Check for admin permissions
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    echo "<div class='main-content'><h2>접근 권한 없음</h2><p>이 페이지에 접근할 권한이 없습니다.</p></div>";
    include 'footer.php';
    exit;
}
?>

<div class="main-content">
    <h2><i class="fas fa-images"></i> 회사 관련 이미지 관리</h2>
    <p>회사 로고, 사업자등록증, 대부업등록증 및 계좌 사본 이미지를 관리합니다.</p>
    <hr>

    <div class="form-container" style="max-width: 800px; margin-top: 20px;">
        <form action="../process/company_images_process.php" method="post" enctype="multipart/form-data">

            <!-- Logo -->
            <div class="form-group" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h4>1. 회사 로고 (logo.png)</h4>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <label for="logo">이미지 선택</label>
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/png, image/jpeg, image/jpg">
                        <small class="text-muted">권장 사이즈: 가로 200px 이상 (PNG 권장)</small>
                    </div>
                    <div style="width: 150px; text-align: center;">
                        <p style="margin-bottom: 5px;">현재 이미지</p>
                        <?php if (file_exists('../uploads/company/logo.png')): ?>
                            <a href="../uploads/company/logo.png" target="_blank"><img src="../uploads/company/logo.png?v=<?php echo time(); ?>" alt="Logo" style="max-width: 100%; max-height: 100px; border: 1px solid #ddd; cursor: pointer;"></a>
                        <?php else: ?>
                            <div style="width: 100%; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; border: 1px solid #ddd;">없음</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Business Registration Certificate -->
            <div class="form-group" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h4>2. 사업자등록증 (regcert.png)</h4>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <label for="regcert">이미지 선택</label>
                        <input type="file" name="regcert" id="regcert" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div style="width: 150px; text-align: center;">
                        <p style="margin-bottom: 5px;">현재 이미지</p>
                        <?php if (file_exists('../uploads/company/regcert.png')): ?>
                            <a href="../uploads/company/regcert.png" target="_blank"><img src="../uploads/company/regcert.png?v=<?php echo time(); ?>" alt="Business Registration" style="max-width: 100%; max-height: 100px; border: 1px solid #ddd; cursor: pointer;"></a>
                        <?php else: ?>
                            <div style="width: 100%; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; border: 1px solid #ddd;">없음</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Loan Business Registration Certificate -->
            <div class="form-group" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h4>3. 대부업등록증 (loancert.png)</h4>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <label for="loancert">이미지 선택</label>
                        <input type="file" name="loancert" id="loancert" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div style="width: 150px; text-align: center;">
                        <p style="margin-bottom: 5px;">현재 이미지</p>
                        <?php if (file_exists('../uploads/company/loancert.png')): ?>
                            <a href="../uploads/company/loancert.png" target="_blank"><img src="../uploads/company/loancert.png?v=<?php echo time(); ?>" alt="Loan Registration" style="max-width: 100%; max-height: 100px; border: 1px solid #ddd; cursor: pointer;"></a>
                        <?php else: ?>
                            <div style="width: 100%; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; border: 1px solid #ddd;">없음</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Collection Account -->
            <div class="form-group" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h4>4. 집금계좌 (bank01.png)</h4>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <label for="bank01">이미지 선택</label>
                        <input type="file" name="bank01" id="bank01" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div style="width: 150px; text-align: center;">
                        <p style="margin-bottom: 5px;">현재 이미지</p>
                        <?php if (file_exists('../uploads/company/bank01.png')): ?>
                            <a href="../uploads/company/bank01.png" target="_blank"><img src="../uploads/company/bank01.png?v=<?php echo time(); ?>" alt="Collection Account" style="max-width: 100%; max-height: 100px; border: 1px solid #ddd; cursor: pointer;"></a>
                        <?php else: ?>
                            <div style="width: 100%; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; border: 1px solid #ddd;">없음</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Expense Account -->
            <div class="form-group" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <h4>5. 경비계좌 (bank02.png)</h4>
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <label for="bank02">이미지 선택</label>
                        <input type="file" name="bank02" id="bank02" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div style="width: 150px; text-align: center;">
                        <p style="margin-bottom: 5px;">현재 이미지</p>
                        <?php if (file_exists('../uploads/company/bank02.png')): ?>
                            <a href="../uploads/company/bank02.png" target="_blank"><img src="../uploads/company/bank02.png?v=<?php echo time(); ?>" alt="Expense Account" style="max-width: 100%; max-height: 100px; border: 1px solid #ddd; cursor: pointer;"></a>
                        <?php else: ?>
                            <div style="width: 100%; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; border: 1px solid #ddd;">없음</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn btn-primary">이미지 업로드</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>