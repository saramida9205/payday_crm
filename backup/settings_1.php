<?php
include 'header.php';
require_once '../common.php';

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    echo "<div class='main-content'><h2>접근 권한 없음</h2><p>이 페이지에 접근할 권한이 없습니다.</p></div>";
    include 'footer.php';
    exit;
}

$company_info = get_all_company_info($link);
$slack_enabled = $company_info['slack_notifications_enabled'] ?? '1'; // Default to enabled if not set
$wideshot_api_key = $company_info['wideshot_api_key'] ?? '';
$default_sender_phone = $company_info['company_phone'] ?? '1666-6979';

// Company Info Variables
$biz_reg_number = $company_info['biz_reg_number'] ?? '';
$loan_biz_reg_number = $company_info['loan_biz_reg_number'] ?? '';
$company_phone = $company_info['company_phone'] ?? '';
$company_fax = $company_info['company_fax'] ?? '';
$company_email = $company_info['company_email'] ?? '';
$interest_account = $company_info['interest_account'] ?? '';
$expense_account = $company_info['expense_account'] ?? '';
?>

<style>
    /* 부모 컨테이너 스타일 */
    .container22 {
        display: flex;       /* 핵심: 자식들을 가로로 배치 */
        gap: 10px;           /* 필요하다면 사이 간격 추가 */
    }

    /* 자식(섹션) 스타일 - 구분을 위해 색상 추가 */
    .form-container22 {
        flex: 1;             /* 공간을 1:1 비율로 똑같이 나눠 가짐 */
        padding: 20px;
        border: 2px solid #bbb8b8e0;
    }
</style>

<h2><i class="fas fa-cog"></i> 시스템 설정</h2>

<div class="container22" style="margin-top: 20px;">
<div class="form-container22" style="max-width: 600px;">
    <h4>자주 쓰는 메모 관리</h4>
    <div class="form-col" style="margin-bottom: 20px;">
        <p>고객/계약 메모 작성 시 선택할 수 있는 상용구를 관리합니다.</p>
        
        <!-- Existing Memos List -->
        <div id="frequent-memos-list" style="margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background-color: #f9f9f9;">
            <?php
            $frequent_memos_query = mysqli_query($link, "SELECT * FROM frequent_memos ORDER BY id ASC");
            if (mysqli_num_rows($frequent_memos_query) > 0) {
                while ($memo = mysqli_fetch_assoc($frequent_memos_query)) {
                    echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #eee;">';
                    echo '<span>' . htmlspecialchars($memo['memo_text']) . '</span>';
                    echo '<button type="button" class="btn btn-sm btn-danger delete-memo-btn" data-id="' . $memo['id'] . '" style="padding: 2px 5px; font-size: 12px;">삭제</button>';
                    echo '</div>';
                }
            } else {
                echo '<p style="color: #666; font-size: 14px;">등록된 메모가 없습니다.</p>';
            }
            ?>
        </div>

        <!-- Add New Memo -->
        <div class="input-group" style="display: flex; gap: 5px;">
            <input type="text" id="new_frequent_memo" class="form-control" placeholder="새로운 상용구 입력" style="flex-grow: 1;">
            <button type="button" id="add_frequent_memo_btn" class="btn btn-secondary">추가</button>
        </div>
    </div>
</div>

<div class="form-container22" style="max-width: 600px;">
    <h4>구분코드 관리</h4>
    <div class="form-col" style="margin-bottom: 20px;">
        <p>계약 관리를 위한 구분코드를 설정합니다. (예: 001-우량채권)</p>
        
        <!-- Existing Classification Codes List -->
        <div id="classification-codes-list" style="margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background-color: #f9f9f9;">
            <?php
            $classification_codes = get_all_classification_codes($link);
            if (!empty($classification_codes)) {
                foreach ($classification_codes as $code) {
                    echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #eee;">';
                    echo '<span><strong>' . htmlspecialchars($code['code']) . '</strong> - ' . htmlspecialchars($code['name']) . '</span>';
                    echo '<button type="button" class="btn btn-sm btn-danger delete-classification-btn" data-id="' . $code['id'] . '" style="padding: 2px 5px; font-size: 12px;">삭제</button>';
                    echo '</div>';
                }
            } else {
                echo '<p style="color: #666; font-size: 14px;">등록된 구분코드가 없습니다.</p>';
            }
            ?>
        </div>

        <!-- Add New Classification Code -->
        <div class="input-group" style="display: flex; gap: 5px;">
            <input type="text" id="new_classification_code" class="form-control" placeholder="코드 (예: 001)" style="width: 30%;">
            <input type="text" id="new_classification_name" class="form-control" placeholder="구분명 (예: 우량채권)" style="flex-grow: 1;">
            <button type="button" id="add_classification_btn" class="btn btn-secondary">추가</button>
        </div>
    </div>
</div>
</div>

<hr style="margin-top: 20px;">

<div class="form-container" style="max-width: 600px; margin-top: 20px;">
    <form action="../process/settings_process.php" method="post" id="system-settings-form">
        <input type="hidden" name="action" value="update_system_settings">
        
        <h4>회사 정보 설정</h4>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="biz_reg_number">사업자번호</label>
            <input type="text" name="biz_reg_number" id="biz_reg_number" class="form-control" value="<?php echo htmlspecialchars($biz_reg_number); ?>" placeholder="예: 283-81-00623">
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="loan_biz_reg_number">대부업등록번호</label>
            <input type="text" name="loan_biz_reg_number" id="loan_biz_reg_number" class="form-control" value="<?php echo htmlspecialchars($loan_biz_reg_number); ?>" placeholder="예: 2017-서울송파-0033(대부업)">
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="company_phone">대표번호</label>
            <input type="text" name="company_phone" id="company_phone" class="form-control" value="<?php echo htmlspecialchars($company_phone); ?>" placeholder="예: 1666-6979">
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="company_fax">팩스번호</label>
            <input type="text" name="company_fax" id="company_fax" class="form-control" value="<?php echo htmlspecialchars($company_fax); ?>" placeholder="예: 0504-150-6210">
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="company_email">이메일</label>
            <input type="text" name="company_email" id="company_email" class="form-control" value="<?php echo htmlspecialchars($company_email); ?>" placeholder="예: paydaycapital@daum.net">
        </div>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="interest_account">이자집금계좌</label>
            <input type="text" name="interest_account" id="interest_account" class="form-control" value="<?php echo htmlspecialchars($interest_account); ?>" placeholder="예: 우리 1005-380-207056">
        </div>
        <div class="form-col" style="margin-bottom: 20px;">
            <label for="expense_account">경비계좌</label>
            <input type="text" name="expense_account" id="expense_account" class="form-control" value="<?php echo htmlspecialchars($expense_account); ?>" placeholder="예: 우리 1005-403-207054">
        </div>

        <hr>

        <h4>슬랙(Slack) 알림 설정</h4>
        <div class="form-col" style="margin-bottom: 20px;">
            <p>SMS 발송 성공/실패 등의 주요 이벤트에 대한 슬랙 알림을 받으려면 '사용'으로 설정하세요.</p>
            <div class="form-check-group">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="slack_notifications_enabled" id="slack_enabled_1" value="1" <?php echo ($slack_enabled == '1') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="slack_enabled_1">사용</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="slack_notifications_enabled" id="slack_enabled_0" value="0" <?php echo ($slack_enabled == '0') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="slack_enabled_0">사용 안함</label>
                </div>
            </div>
        </div>

        <hr>

        <h4>SMS API 설정</h4>
        <div class="form-col" style="margin-bottom: 15px;">
            <label for="wideshot_api_key">와이드샷 API Key</label>
            <input type="text" name="wideshot_api_key" id="wideshot_api_key" class="form-control" value="<?php echo htmlspecialchars($wideshot_api_key); ?>" placeholder="세종텔레콤에서 발급받은 API 키">
        </div>
        <div class="form-col" style="margin-bottom: 20px;">
            <label for="default_sender_phone">기본 발신번호 (운영용)</label>
            <input type="text" name="default_sender_phone" id="default_sender_phone" class="form-control" value="<?php echo htmlspecialchars($default_sender_phone); ?>" placeholder="예: 1666-6979">
            <p class="form-text" style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                테스트 발송 시에는 이 번호와 상관없이 테스트용 발신번호(16882200)가 사용됩니다.
            </p>
        </div>

        <div class="form-buttons"><button type="submit" class="btn btn-primary">설정 저장</button></div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Frequent Memo Management Scripts ---
    // Add Memo
    document.getElementById('add_frequent_memo_btn').addEventListener('click', function() {
        const memoText = document.getElementById('new_frequent_memo').value;
        if (!memoText.trim()) {
            alert('메모 내용을 입력해주세요.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../process/settings_process.php';

        const inputMemo = document.createElement('input');
        inputMemo.type = 'hidden';
        inputMemo.name = 'new_frequent_memo';
        inputMemo.value = memoText;
        form.appendChild(inputMemo);

        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'add_frequent_memo';
        inputAction.value = '1';
        form.appendChild(inputAction);

        document.body.appendChild(form);
        form.submit();
    });

    // Delete Memo
    document.querySelectorAll('.delete-memo-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('정말 삭제하시겠습니까?')) return;

            const memoId = this.dataset.id;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../process/settings_process.php';

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'delete_frequent_memo';
            inputId.value = memoId;
            form.appendChild(inputId);

            document.body.appendChild(form);
            form.submit();
        });
    });

    // --- Classification Code Management Scripts ---
    // Add Classification Code
    document.getElementById('add_classification_btn').addEventListener('click', function() {
        const code = document.getElementById('new_classification_code').value;
        const name = document.getElementById('new_classification_name').value;

        if (!code.trim() || !name.trim()) {
            alert('코드와 구분명을 모두 입력해주세요.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../process/settings_process.php';

        const inputCode = document.createElement('input');
        inputCode.type = 'hidden';
        inputCode.name = 'new_classification_code';
        inputCode.value = code;
        form.appendChild(inputCode);

        const inputName = document.createElement('input');
        inputName.type = 'hidden';
        inputName.name = 'new_classification_name';
        inputName.value = name;
        form.appendChild(inputName);

        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'add_classification_code';
        inputAction.value = '1';
        form.appendChild(inputAction);

        document.body.appendChild(form);
        form.submit();
    });

    // Delete Classification Code
    document.querySelectorAll('.delete-classification-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('정말 삭제하시겠습니까?')) return;

            const id = this.dataset.id;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../process/settings_process.php';

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'delete_classification_code';
            inputId.value = id;
            form.appendChild(inputId);

            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>

<?php include 'footer.php'; ?>