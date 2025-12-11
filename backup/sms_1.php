<?php 
require_once __DIR__ . '/../common.php';
include_once __DIR__ . '/../process/contract_process.php';
include_once __DIR__ . '/../process/sms_process.php';
include_once 'header.php';

$is_single_mode = isset($_GET['contract_id']) && !empty($_GET['contract_id']);
// $holidays = getHolidays(); // Deprecated
$all_contracts = [];
$company_info = get_all_company_info($link);
$company_phone = $company_info['default_sender_phone'] ?? ''; // DB에 저장된 기본 발신번호 사용
$company_deposit_account = '우리은행 1005-380-207056 (주)페이데이캐피탈대부';

    // --- MULTI-SELECT MODE ---
    $due_days = [1, 5, 6, 10, 11, 15, 16, 20, 21, 25, 26];
    // If due_days are not set in GET, default to all. If it's set but empty (e.g. from form submission with no selection), use an empty array.
    if (isset($_GET['due_days'])) {
        $selected_due_days = $_GET['due_days'];
    } elseif (isset($_GET['next_due_date']) && !empty($_GET['next_due_date'])) {
        $selected_due_days = []; // 상환일이 지정되면 약정일 기본값은 비움
    } else {
        // Default to the next upcoming due day
        $today_day = (int)date('d');
        $next_due_day = null;
        foreach ($due_days as $day) {
            if ($day >= $today_day) {
                $next_due_day = $day;
                break;
            }
        }
        if ($next_due_day === null) {
            $next_due_day = $due_days[0]; // Default to the first one if we're past all of them this month
        }
        $selected_due_days = [$next_due_day];
    }

    $next_due_date_filter = $_GET['next_due_date'] ?? null;
    $all_contracts = getContractsForSms($link, $selected_due_days, $is_single_mode ? (int)$_GET['contract_id'] : null, $next_due_date_filter);

$templates_query = mysqli_query($link, "SELECT * FROM sms_templates ORDER BY id ASC");
$sms_templates = mysqli_fetch_all($templates_query, MYSQLI_ASSOC);

?>

<style>
    /* 공통 스타일 */
    .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .info-table td { padding: 8px; border: 1px solid #eee; }
    .info-table td:nth-child(odd) { background-color: #f9f9f9; font-weight: 600; width: 12%; }
    .info-table td:nth-child(even) { width: 21.33%; }

    .sms-layout-container {
        display: grid;
        grid-template-columns: 30% 35% 35%;
        gap: 10px;
        align-items: start;
    }
    .sms-layout-column {
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 5px;
        background-color: #fff;
    }
    .sms-layout-column h3 { margin-top: 0; }
    #contract-list-container { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; }
    #preview-container { margin-top: 20px; }

    .sms-template-box { 
        border: 1px solid #ddd; 
        padding: 15px; 
        margin-bottom: 15px; 
        border-radius: 5px; 
        font-size: 14px; 
        line-height: 1.6; 
        position: relative; 
    }
    .form-check:nth-child(odd) .form-check-label {
        background-color: #f8f9fa; /* 홀수 항목 배경색 */
    }
    .form-check:nth-child(even) .form-check-label {
        background-color: #ffffff; /* 짝수 항목 배경색 */
    }
    .form-check {
        display: flex; /* 라디오버튼과 라벨을 한 줄에 배치 */
        align-items: center; /* 수직 중앙 정렬 */
        padding: 8px;
        border-radius: 4px;
    }
    .template-list-item {
        white-space: normal; /* 내용이 길면 줄바꿈 되도록 수정 */
    }
    .copy-btn { margin-top: 10px; }
    .send-sms-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #007bff;
    }

    /* 템플릿 관리 섹션 스타일 */
    #sms-templates-container .template-buttons .btn,
    #sms-templates-container .template-buttons .btn-sm {
        width: 100%;
        padding: 2px 5px;
        font-size: 12px;
        line-height: 1.4;
    }
    .template-edit-form textarea {
        font-size: 16px !important;
        height: 180px;
    }

    .step-card {
        opacity: 0.5;
        pointer-events: none;
    }
    .step-card.active {
        opacity: 1;
        pointer-events: auto;
    }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
    .template-variable {
        color: #0056b3;
        font-weight: bold;
        background-color: #e7f1ff;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 0.95em;
    }
</style>

<h2>SMS 문자 발송</h2>

<?php if (isset($_SESSION['message'])): ?>
<div class="msg"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
<div class="msg error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

    <div class="sms-layout-container">
        <!-- Column 1: Steps 1 & 2 -->
        <div class="sms-layout-column" style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Step 1: Filters and Contract List -->
            <div id="step1-card" class="card active">
                <div class="card-header"><h5><i class="fas fa-users"></i> 1단계: 발송 대상 선택</h5></div>
                <div class="card-body">
                <form id="sms-filter-form" method="get" action="sms.php">
                    <h4>약정일 필터</h4>
                    <?php if (!$is_single_mode): ?>
                    <div id="agreement-day-filter" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 14px; margin-bottom: 10px;">
                        <div class="form-check" style="padding:0;"><input class="form-check-input" type="checkbox" id="select-all-due-days"><label class="form-check-label" for="select-all-due-days"><strong>전체</strong></label></div>
                        <?php foreach ($due_days as $day): ?>
                        <div class="form-check" style="padding:0;"><input class="form-check-input due-day-checkbox" type="checkbox" name="due_days[]" value="<?php echo $day; ?>" id="due_day_<?php echo $day; ?>" <?php if (in_array($day, $selected_due_days)) echo 'checked'; ?>><label class="form-check-label" for="due_day_<?php echo $day; ?>"><?php echo $day; ?>일</label></div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <label for="next_due_date_filter" style="font-size: 14px; font-weight: bold; margin-right: 10px;">상환일:</label>
                        <input type="date" id="next_due_date_filter" name="next_due_date" value="<?php echo htmlspecialchars($_GET['next_due_date'] ?? ''); ?>" class="form-control" style="display: inline-block; width: auto;">
                    </div>
                    <button type="submit" id="apply-filter-btn" class="btn btn-secondary btn-sm" style="margin-top: 10px;">필터 적용</button>
                    <?php endif; ?>
                </form>
                <hr style="margin-top: 10px;">
                <h4>계약 목록 (<?php echo count($all_contracts); ?>건)</h4>
                <div style="margin-bottom: 10px; font-size: 12px;">
                    <label style="margin-right: 15px; cursor: pointer;">
                        <input type="checkbox" class="color-filter-checkbox" data-target="due_today" style="vertical-align: middle;">
                        <span style="display:inline-block; width:12px; height:12px; background-color:#ffffcc; border:1px solid #ccc; margin-right:5px; vertical-align:middle;"></span>당일 약정
                    </label>
                    <label style="margin-right: 15px; cursor: pointer;">
                        <input type="checkbox" class="color-filter-checkbox" data-target="due_soon" style="vertical-align: middle;">
                        <span style="display:inline-block; width:12px; height:12px; background-color:#ffe5cc; border:1px solid #ccc; margin-right:5px; vertical-align:middle;"></span>약정일 1~3일이내
                    </label>
                    <label style="cursor: pointer;">
                        <input type="checkbox" class="color-filter-checkbox" data-target="deferred" style="vertical-align: middle;">
                        <span style="display:inline-block; width:12px; height:12px; background-color:#e6f7ff; border:1px solid #ccc; margin-right:5px; vertical-align:middle;"></span>유예약정금 보유
                    </label>
                </div>
                <div id="contract-list-container">
                    <table class="table">
                        <thead><tr><th><input type="checkbox" id="select-all-contracts"></th><th>번호</th><th>고객명</th><th>클릭복사</th><th>약정일자</th></tr></thead>
                        <tbody>
                        <?php 
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);

                        foreach($all_contracts as $c): 
                            $row_style = '';
                            $row_category = ''; // Initialize category

                            if (!empty($c['next_due_date'])) {
                                try {
                                    $due_date = new DateTime($c['next_due_date']);
                                    $due_date->setTime(0, 0, 0);
                                    $interval = $today->diff($due_date);
                                    $days_diff = (int)$interval->format('%r%a');

                                    if ($days_diff == 0) {
                                        $row_style = 'style="background-color: #ffffcc;"'; // yellow
                                        $row_category = 'due_today';
                                    } elseif ($days_diff > 0 && $days_diff <= 3) {
                                        $row_style = 'style="background-color: #ffe5cc;"'; // orange
                                        $row_category = 'due_soon';
                                    }
                                } catch (Exception $e) { /* 날짜 형식이 잘못된 경우 무시 */ }
                            }

                            // 유예약정금이 있는 경우 배경색 변경 (우선순위 높음)
                            if (!empty($c['deferred_agreement_amount']) && $c['deferred_agreement_amount'] > 0) {
                                $row_style = 'style="background-color: #e6f7ff;"'; // light blue
                                $row_category = 'deferred';
                            }
                        ?>
                            <tr <?php echo $row_style; ?> data-category="<?php echo $row_category; ?>">
                                <td><input type="checkbox" class="contract-checkbox" name="contract_ids[]" value="<?php echo $c['contract_id']; ?>" data-info='<?php echo json_encode($c, JSON_HEX_APOS); ?>' <?php if ($is_single_mode && $c['contract_id'] == $_GET['contract_id']) echo 'checked'; ?>></td>
                                <td style="font-size: 0.75rem;"><?php echo $c['contract_id']; ?></td>
                                <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                                <td class="copy-phone" data-clipboard-text="<?php echo htmlspecialchars($c['customer_phone']); ?>" style="cursor: pointer;" title="클릭하여 복사"><?php echo htmlspecialchars($c['customer_phone']); ?></td>
                                <td><?php echo htmlspecialchars($c['next_due_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>

            <!-- Step 2: Template Selection -->
            <div id="step2-card" class="card step-card">
                <div class="card-header"><h5><i class="fas fa-file-alt"></i> 2단계: 템플릿 선택</h5></div>
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input template-checkbox" type="radio" name="template_id" value="manual" id="template-manual" data-text="">
                        <label class="form-check-label template-list-item" for="template-manual" title="메시지를 직접 수정하여 발송합니다.">
                            <strong>[수기 작성]</strong><br> 메시지 직접 입력
                        </label>
                    </div>

                    <?php foreach ($sms_templates as $template): ?>
                    <div class="form-check">
                        <input class="form-check-input template-checkbox" type="radio" name="template_id" value="<?php echo $template['id']; ?>" id="template-<?php echo $template['id']; ?>" data-text="<?php echo htmlspecialchars($template['template_text']); ?>">
                        <label class="form-check-label template-list-item" for="template-<?php echo $template['id']; ?>" title="<?php echo htmlspecialchars($template['template_text']); ?>">
                            <?php 
                                $title = !empty(trim($template['title'])) ? trim($template['title']) : '제목 없음';
                                $content_preview = mb_strimwidth($template['template_text'], 0, 70, "...");
                                echo '<strong>[' . htmlspecialchars($title) . ']</strong><br> ' . htmlspecialchars($content_preview);
                            ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Column 2: Preview -->
        <div class="sms-layout-column">
            <div id="step3-card" class="card step-card">
                <div class="card-header"><h5><i class="fas fa-eye"></i> 3단계: 미리보기</h5></div>
                <div class="card-body">
                    <div id="manual-message-area" style="display:none; margin-bottom: 15px;">
                        <label for="manual-message-input">메시지 내용 수정</label><br>
                        <div style="position: relative; width: 80%; margin-bottom: 15px;">
                            <textarea id="manual-message-input" class="form-control" rows="6" spellcheck="false" style="width: 100%; padding: 10px; line-height: 1.6; font-weight: normal; font-size: 16px; 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding-bottom: 25px;" ></textarea>
                            <div id="char-counter" style="position: absolute; bottom: 10px; right: 15px; font-size: 12px; color: #6c757d;">0 / 2000자</div>
                        </div>
                    </div>
                    <button type="button" id="generate-preview-btn" class="btn btn-primary" style="width: 100%; margin-bottom: 15px;">미리보기 생성</button>
                    <div id="preview-container" style="max-height: 650px; overflow-y: auto;">
                        <p style="color: #6c757d;">대상을 선택하고 템플릿을 적용하여 미리보기를 생성하세요.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 3: Send -->
        <div class="sms-layout-column">
            <div id="step4-card" class="card step-card">
                <div class="card-header"><h5><i class="fas fa-paper-plane"></i> 4단계: 발송하기</h5></div>
                <div class="card-body">
                <form action="../process/sms_process.php" method="post" id="multi-sms-send-form">
                    <!-- 이 필드는 JS로 채워집니다 -->
                    <input type="hidden" name="bulk_data" id="bulk-sms-data-input">

                    <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
                        <div class="form-col">
                            <label for="sender_phone_multi">발신번호</label>
                            <input type="text" id="sender_phone_multi" name="sender_phone" class="form-control" value="<?php echo htmlspecialchars($company_phone); ?>" required>
                        </div>
                        <div class="form-col">
                            <label for="sms_title_multi">문자 제목 (LMS/MMS)</label>
                            <input type="text" id="sms_title_multi" name="title" class="form-control" placeholder="제목 (장문 문자 발송 시)">
                        </div>
                    </div>
                    <div id="recipient-summary" style="margin-top:10px; font-size:12px;">
                        <p style="margin:0;"><strong>선택된 수신자:</strong> <span id="recipient-count">0</span>명</p>
                        <div id="recipient-list" style="max-height: 60px; overflow-y: auto; background: #f8f9fa; padding: 5px; border-radius: 3px;"></div>
                    </div>
                    <button type="submit" name="send_sms_bulk" id="send-bulk-btn" class="btn btn-primary" style="width:100%; margin-top:15px;" disabled>일괄 발송하기</button>
                </form>
            </div>
        </div>
    </div>

<hr>
</div>
<details class="accordion" style="margin-top: 20px;">
    <summary>문자 메시지 템플릿 관리</summary>
    <div class="accordion-content">
        <div id="sms-templates-container" style="display: flex; flex-wrap: wrap; gap: 15px;">
            <?php foreach ($sms_templates as $template): 
                $message = $template['template_text'];
            ?>
            <div class="template-item-container" style="width: calc(25% - 12px);">
                <div class="sms-template-box" id="template-box-<?php echo $template['id']; ?>" style="overflow: auto; height: 180px; min-height: 150px;">
                    <div class="template-content">
                        <strong style="margin-bottom: 5px; display: block; border-bottom: 1px solid #eee; padding-bottom: 5px;"><?php echo htmlspecialchars($template['title']); ?></strong>
                        <p id="sms-text-<?php echo $template['id']; ?>">
                            <?php 
                                echo preg_replace('/(\[.*?\])/', '<span class="template-variable">$1</span>', nl2br(htmlspecialchars($message)));
                            ?>
                        </p>                        
                    </div>
                </div>
                <div class="template-buttons" style="display: flex; justify-content: center; gap: 5px; width: 100%; margin-top: 5px;">
                    <button type="button" class="btn btn-sm btn-secondary copy-btn" data-clipboard-target="#sms-text-<?php echo $template['id']; ?>">복사</button>
                    <button type="button" class="btn btn-sm btn-secondary edit-template-btn" data-template-id="<?php echo $template['id']; ?>" data-title="<?php echo htmlspecialchars($template['title']); ?>" data-text="<?php echo htmlspecialchars($template['template_text']); ?>">수정</button>
                    <form action="../process/sms_process.php" method="post" onsubmit="return confirm('이 템플릿을 삭제하시겠습니까?');" style="flex: 1; margin: 0; display: flex;">
                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                        <input type="hidden" name="contract_id" value="<?php echo $is_single_mode ? (int)$_GET['contract_id'] : ''; ?>">
                        <button type="submit" name="delete_template" class="btn btn-sm btn-danger">삭제</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="template-item-container" style="width: calc(25% - 12px);">
                 <div class="sms-template-box" style="height: auto; min-height: 150px; border-style: dashed;">
                    <form action="../process/sms_process.php" method="post">
                        <input type="hidden" name="contract_id" value="<?php echo $is_single_mode ? (int)$_GET['contract_id'] : ''; ?>">
                        <div class="input-group" style="margin:0; position: relative;">
                            <label for="new_template_title" style="margin-bottom: 5px;">새 템플릿 추가</label><br>
                            <input type="text" name="title" id="new_template_title" placeholder="템플릿 제목" class="form-control" style="margin-bottom: 8px;" required><br>
                            <textarea name="template_text" id="template_text" rows="5" class="form-control" required style="padding-bottom: 25px; overflow: auto; height: 120px; min-height: 120px;"></textarea>
                            <div id="new-template-char-counter" style="position: absolute; bottom: 55px; right: 10px; font-size: 12px; color: #6c757d;">0 / 2000자</div>
                            <p style="font-size: 11px; color: #6c757d; margin-top: 5px;">사용 가능 변수: [고객명], [약정일], [약정일이자], [이자납부계좌], [연락처], [회사연락처], [오늘이자], [오늘총이자], [오늘완납금액], [현재대출잔액], [최초대출원금], [유예약정금], [미수취비용], [대출일], [오늘날짜], [오늘납부내역]</p>
                        </div>
                        <button type="submit" name="add_template" class="btn btn-primary btn-sm" style="width: 100%; margin-top: 10px;">템플릿 추가</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</details>

<!-- Template Edit Modal -->
<div id="template-edit-modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>템플릿 수정</h3>
            <span class="close-button">&times;</span>
        </div>
        <form action="../process/sms_process.php" method="post" id="template-edit-form">
            <input type="hidden" name="template_id" id="modal-template-id">
            <input type="hidden" name="contract_id" value="<?php echo $is_single_mode ? (int)$_GET['contract_id'] : ''; ?>">
            <div class="form-col" style="margin-bottom: 15px;">
                <label for="modal-template-title">제목</label>
                <input type="text" name="title" id="modal-template-title" class="form-control" required>
            </div>
            <div class="form-col" style="margin-bottom: 15px; position: relative;">
                <label for="modal-template-text">템플릿 내용</label>
                <textarea name="template_text" id="modal-template-text" rows="8" class="form-control" required style="padding-bottom: 25px;"></textarea>
                <div id="modal-char-counter" style="position: absolute; bottom: 20px; right: 15px; font-size: 12px; color: #6c757d;">0 / 2000자</div>
            </div>
            <div class="form-buttons" style="text-align: right;">
                <button type="submit" name="update_template" class="btn btn-primary">저장</button>
                <button type="button" class="btn btn-secondary" id="modal-close-btn">취소</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isSingleMode = <?php echo json_encode($is_single_mode); ?>;
    if (isSingleMode) {
        document.getElementById('step1-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // --- Due Day Filter Logic ---
    const selectAllDueDays = document.getElementById('select-all-due-days');
    const dueDayCheckboxes = document.querySelectorAll('.due-day-checkbox');
    const isAllChecked = Array.from(dueDayCheckboxes).every(cb => cb.checked);

    if (selectAllDueDays) {
        selectAllDueDays.checked = isAllChecked;

        selectAllDueDays.addEventListener('change', function() {
            dueDayCheckboxes.forEach(cb => { cb.checked = this.checked; });
        });

        dueDayCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllDueDays.checked = false;
                } else if (Array.from(dueDayCheckboxes).every(innerCb => innerCb.checked)) {
                    selectAllDueDays.checked = true;
                }
            });
        });
    }

    new ClipboardJS('.copy-btn').on('success', function(e) {
        e.trigger.textContent = '복사 완료!';
        setTimeout(() => { e.trigger.textContent = '복사하기'; }, 1500);
        e.clearSelection();
    });

    var clipboardPhone = new ClipboardJS('.copy-phone');
    clipboardPhone.on('success', function(e) {
        var originalText = e.trigger.textContent;
        e.trigger.textContent = '복사 완료!';
        e.trigger.style.color = 'blue';
        setTimeout(function() {
            e.trigger.textContent = originalText;
            e.trigger.style.color = '';
        }, 1500);
        e.clearSelection();
    });

    const selectAllContracts = document.getElementById('select-all-contracts');
    if (selectAllContracts) {
        selectAllContracts.addEventListener('change', function() {
            document.querySelectorAll('.contract-checkbox').forEach(cb => { 
                cb.checked = this.checked; 
                cb.dispatchEvent(new Event('change')); // Trigger change event
            });
        });
    }

    // --- Step-by-step UI logic ---
    const step2Card = document.getElementById('step2-card');
    const step3Card = document.getElementById('step3-card');
    const step4Card = document.getElementById('step4-card');
    const sendBulkBtn = document.getElementById('send-bulk-btn');
    const manualMessageArea = document.getElementById('manual-message-area');
    const manualMessageInput = document.getElementById('manual-message-input');

    // --- Character Counter for Manual Textarea ---
    const charCounter = document.getElementById('char-counter');
    if (manualMessageInput && charCounter) {
        manualMessageInput.addEventListener('input', function() {
            const textLength = this.value.length;
            charCounter.textContent = `${textLength} / 2000자`;
            if (textLength > 90) {
                charCounter.style.color = '#dc3545'; // Red for LMS/MMS
            } else {
                charCounter.style.color = '#6c757d'; // Default color
            }
        });
    }
    function updateStepStatus() {
        const selectedContracts = document.querySelectorAll('.contract-checkbox:checked').length;
        const selectedTemplate = document.querySelector('.template-checkbox:checked');

        // Step 2 activation
        if (selectedContracts > 0) {
            step2Card.classList.add('active');
        } else {
            step2Card.classList.remove('active');
        }

        // Step 3 activation
        if (selectedContracts > 0 && selectedTemplate) {
            step3Card.classList.add('active');
            if (selectedTemplate.value === 'manual') {
                manualMessageArea.style.display = 'block';
            } else {
                manualMessageArea.style.display = 'none';
            }
        } else {
            step3Card.classList.remove('active');
            manualMessageArea.style.display = 'none';
        }
    }

    document.querySelectorAll('.contract-checkbox').forEach(cb => {
        cb.addEventListener('change', updateStepStatus);
    });

    document.querySelectorAll('.template-checkbox').forEach(radio => {
        radio.addEventListener('change', updateStepStatus);
    });

    // Initial check
    updateStepStatus();

    const generatePreviewBtn = document.getElementById('generate-preview-btn');
    if (generatePreviewBtn) {
        generatePreviewBtn.addEventListener('click', function() {
            const previewContainer = document.getElementById('preview-container');
            const selectedContracts = Array.from(document.querySelectorAll('.contract-checkbox:checked'));
            const recipientSummaryList = document.getElementById('recipient-list');
            const selectedTemplate = document.querySelector('.template-checkbox:checked');

            previewContainer.innerHTML = '';
            step4Card.classList.remove('active');
            sendBulkBtn.disabled = true;

            if (selectedContracts.length === 0 || !selectedTemplate) {
                alert('계약과 템플릿을 하나 이상 선택하세요.');
                return;
            }

            let bulkSmsData = [];
            let recipientHtml = '';
            const isManual = selectedTemplate.value === 'manual';
            const templateText = isManual ? manualMessageInput.value : selectedTemplate.dataset.text;

            if (isManual && !templateText.trim()) {
                alert('수기 작성 시 메시지 내용을 입력해주세요.');
                manualMessageInput.focus();
                return;
            }

            selectedContracts.forEach(contractCb => {
                const contractInfo = JSON.parse(contractCb.dataset.info);
                const customerAccount = (contractInfo.bank_name && contractInfo.account_number) ? `${contractInfo.bank_name} ${contractInfo.account_number}` : '<?php echo $company_deposit_account; ?>';
                const replacements = {
                    '[고객명]': contractInfo.customer_name,
                    '[약정일]': contractInfo.next_due_date,
                    '[약정일이자]': contractInfo.expected_interest,
                    '[이자납부계좌]': '<?php echo $company_deposit_account; ?>',
                    '[연락처]': '<?php echo $company_phone; ?>', // Use company phone
                    '[회사연락처]': '<?php echo $company_phone; ?>',
                    '[오늘이자]': contractInfo.interest_today,
                    '[오늘총이자]': contractInfo.total_interest_due_today, // 당일이자+연체이자+부족금
                    '[오늘완납금액]': contractInfo.total_due_today,
                    '[현재대출잔액]': contractInfo.outstanding_principal,
                    '[오늘날짜]': contractInfo.today_date,
                    '[최초대출원금]': new Intl.NumberFormat('ko-KR').format(contractInfo.loan_amount) + '원',
                    '[유예약정금]': new Intl.NumberFormat('ko-KR').format(contractInfo.deferred_agreement_amount || 0) + '원',
                    '[미수취비용]': contractInfo.unpaid_expenses,
                    '[대출일]': contractInfo.loan_date,
                    '[오늘납부내역]': contractInfo.today_payment_details
                };
                const message = templateText.replace(/\[(.*?)\]/g, (match, p1) => replacements[match] || match);
                
                // 데이터 준비
                bulkSmsData.push({
                    phone: contractInfo.customer_phone,
                    message: message,
                    contract_id: contractInfo.contract_id, // contract_id 추가
                    name: contractInfo.customer_name      // customer_name 추가
                });
                recipientHtml += `<span>${contractInfo.customer_name}(${contractInfo.customer_phone})</span>, `;

                // 미리보기 UI 생성
                const previewId = `preview-${contractInfo.contract_id}-${selectedTemplate.value}`;
                const box = document.createElement('div');
                box.className = 'sms-template-box';
                box.innerHTML = `<p style="font-size: 12px; color: #666; margin-bottom: 5px;"><b>To:</b> ${contractInfo.customer_name} (${contractInfo.customer_phone})</p><p id="${previewId}">${message}</p><button class="btn btn-secondary copy-btn btn-sm" data-clipboard-target="#${previewId}">복사하기</button>`;
                previewContainer.appendChild(box);
            });

            // 숨겨진 필드에 데이터 설정
            document.getElementById('bulk-sms-data-input').value = JSON.stringify(bulkSmsData);
            // 수신자 요약 업데이트
            document.getElementById('recipient-count').textContent = selectedContracts.length;
            recipientSummaryList.innerHTML = recipientHtml.slice(0, -2); // 마지막 콤마 제거

            // Activate Step 4
            step4Card.classList.add('active');
            sendBulkBtn.disabled = false;
        });
    }


    // --- Template Edit Modal Logic ---
    const modal = document.getElementById('template-edit-modal');
    const closeBtn = modal.querySelector('.close-button');
    const cancelBtn = document.getElementById('modal-close-btn');

    const closeModal = () => { modal.style.display = 'none'; };
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            closeModal();
        }
    });

    // --- New Template Character Counter ---
    const newTemplateTextarea = document.getElementById('template_text');
    const newTemplateCharCounter = document.getElementById('new-template-char-counter');

    if (newTemplateTextarea && newTemplateCharCounter) {
        newTemplateTextarea.addEventListener('input', function() {
            const textLength = this.value.length;
            newTemplateCharCounter.textContent = `${textLength} / 2000자`;
            if (textLength > 90) {
                newTemplateCharCounter.style.color = '#dc3545'; // Red for LMS/MMS
            } else {
                newTemplateCharCounter.style.color = '#6c757d'; // Default color
            }
        });
    }

    // --- Template Edit Modal Character Counter ---
    const modalTextarea = document.getElementById('modal-template-text');
    const modalCharCounter = document.getElementById('modal-char-counter');

    if (modalTextarea && modalCharCounter) {
        const updateModalCounter = () => {
            const textLength = modalTextarea.value.length;
            modalCharCounter.textContent = `${textLength} / 2000자`;
            if (textLength > 90) {
                modalCharCounter.style.color = '#dc3545'; // Red for LMS/MMS
            } else {
                modalCharCounter.style.color = '#6c757d'; // Default color
            }
        };
        modalTextarea.addEventListener('input', updateModalCounter);
        new MutationObserver(updateModalCounter).observe(modalTextarea, { attributes: true, attributeFilter: ['value'] });
    }

    document.querySelectorAll('.edit-template-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const templateId = this.dataset.templateId;
            const title = this.dataset.title;
            const text = this.dataset.text;

            document.getElementById('modal-template-id').value = templateId;
            document.getElementById('modal-template-title').value = title;
            document.getElementById('modal-template-text').value = text;
            modalTextarea.dispatchEvent(new Event('input')); // Trigger counter update when modal opens

            modal.style.display = 'block';
            document.getElementById('modal-template-title').focus();
        });
    });

    // --- Color Filter Logic ---
    document.querySelectorAll('.color-filter-checkbox').forEach(filterCb => {
        filterCb.addEventListener('change', function() {
            const targetCategory = this.dataset.target;
            const isChecked = this.checked;

            document.querySelectorAll(`tr[data-category="${targetCategory}"] .contract-checkbox`).forEach(rowCb => {
                rowCb.checked = isChecked;
                rowCb.dispatchEvent(new Event('change')); // Trigger updateStepStatus
            });
        });
    });

    // If in single mode, trigger change event to activate steps
    if (isSingleMode) {
        document.querySelectorAll('.contract-checkbox:checked').forEach(cb => cb.dispatchEvent(new Event('change')));
    }
});
</script>

<?php include_once('footer.php'); ?>