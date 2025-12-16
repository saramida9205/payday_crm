<?php
include 'header.php';
require_once '../common.php';
require_once '../process/contract_process.php';
require_once '../process/customer_process.php';

$contract_id = $_GET['contract_id'] ?? null;
$is_fully_paid = false;
$contract = null;
$customer = null;

if ($contract_id) {
    $contract = getContractById($link, $contract_id);
    if ($contract) {
        // Check if the contract is fully paid
        $outstanding_principal = calculateOutstandingPrincipal($link, $contract_id, $contract['loan_amount']);
        if ($outstanding_principal <= 0 && $contract['shortfall_amount'] <= 100) {
            $is_fully_paid = true;
        }
        $customer = getCustomerById($link, $contract['customer_id']);
    }
}

$company_info = get_all_company_info($link);
$certificate_templates = get_all_certificate_templates();

?>

<!-- Summernote 에디터용 CSS/JS 추가 -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-ko-KR.min.js"></script>

<h2>증명서 인쇄</h2>

<div class="print-container">
    <div class="print-controls">
        <h4>인쇄 설정</h4>
        <form id="print-settings-form" onsubmit="return false;">
            <div class="form-col" style="margin-bottom: 15px;">
                <label for="contract_search">1. 계약 검색</label>
                <input type="text" id="contract_search" placeholder="계약번호 또는 고객명으로 검색">
                <p>
                    <font color=green>2. ⬇️선택클릭</font>
                </p>
                <input type="hidden" name="contract_id" id="contract_id" value="<?php echo htmlspecialchars($contract_id ?? ''); ?>">
                <div id="search_results" class="search-results-box"></div>
            </div>

            <?php if ($contract && $customer): ?>
                <div id="selected_contract_info" style="margin-bottom: 20px; font-size: 14px;">
                    <p><strong>✔️선택된 계약:</strong><br>
                        계약번호: <?php echo htmlspecialchars($contract['id']); ?><br>
                        고객명: <?php echo htmlspecialchars($customer['name']); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="form-col" style="margin-bottom: 20px;">
                <label for="certificate_type">3. 증명서 종류</label>
                <select id="certificate_type" name="certificate_type" class="form-control">
                    <option value="">-- 증명서 선택 --</option>
                    <?php if ($is_fully_paid): ?>
                        <option value="payment_completion">완납증명서</option>
                    <?php endif; ?>
                    <option value="auction_notice">경매예정통보</option>
                    <option value="benefit_loss_notice">기한의 이익상실 통지서</option>
                    <option value="dunning_letter">독촉장</option>
                    <option value="legal_action_notice">법적절차 착수 최고 통보장</option>
                    <option value="claim_assignment_notice">채권 양도 통지서</option>
                    <option value="debt_repayment_cert">채무변제확인서</option>
                    <option value="transaction_history">거래내역서</option>
                    <option value="debt_confirmation">채무확인서</option>
                    <option value="deposit_receipt">입금영수증</option>
                    <option value="repayment_schedule">상환예정 스케줄표</option>
                </select>
            </div>

            <div id="transaction-history-options" style="display: none; margin-bottom: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                <h5 style="margin-top:0; margin-bottom: 10px; font-size: 14px;">거래내역서 옵션</h5>
                <div class="form-col" style="margin-bottom: 10px;">
                    <label for="th_start_date">조회 시작일</label>
                    <input type="date" id="th_start_date" name="th_start_date" class="form-control">
                </div>
                <div class="form-col">
                    <label for="th_end_date">조회 종료일</label>
                    <input type="date" id="th_end_date" name="th_end_date" class="form-control">
                </div>
            </div>
            <div class="form-buttons">
                <button type="button" id="preview_btn" class="btn btn-primary">미리보기</button>
                <button type="button" id="print_btn" class="btn btn-success">인쇄</button>
            </div>
        </form>

        <div class="form-container" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 4px;">
            <h5 style="margin-top:0;">증명서 기본 정보 편집</h5>
            <form id="company-info-form" onsubmit="return false;">
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">회사명</label><input type="text" name="company_name" value="<?php echo htmlspecialchars($company_info['company_name']); ?>"></div>
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">대표이사</label><input type="text" name="ceo_name" value="<?php echo htmlspecialchars($company_info['ceo_name']); ?>"></div>
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">회사주소</label><input type="text" name="company_address" value="<?php echo htmlspecialchars($company_info['company_address']); ?>"></div>
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">회사연락처</label><input type="text" name="company_phone" value="<?php echo htmlspecialchars($company_info['company_phone']); ?>"></div>
                <hr style="margin: 10px 0;">
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">담당자명</label><input type="text" name="manager_name" value="<?php echo htmlspecialchars($company_info['manager_name']); ?>"></div>
                <div class="form-col" style="margin-bottom: 8px;"><label style="font-size:12px;">담당자연락처</label><input type="text" name="manager_phone" value="<?php echo htmlspecialchars($company_info['manager_phone']); ?>"></div>
                <div class="form-buttons" style="margin-top: 15px;">
                    <hr style="margin: 10px 0;">
                    <div class="form-col" style="margin-bottom: 8px;">
                        <label style="font-size:12px;">법인 인감 (PNG 파일)</label>
                        <input type="file" name="company_seal_image" id="company_seal_image" accept="image/png">
                        <div id="seal-preview-container" style="margin-top: 10px;">
                            <small>현재 인감:</small><br>
                            <img id="seal-preview" src="../uploads/company/seal.png?t=<?php echo time(); ?>" alt="인감 이미지" style="max-width: 80px; max-height: 80px; border: 1px solid #ddd; margin-top: 5px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                            <span id="no-seal-text" style="display: none; font-size: 12px; color: #888;">없음</span>
                        </div>
                    </div>
                    <!--관리자 일때만 기본 정보 저장 버튼보이게 -->
                    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                        <button type="button" id="save-company-info-btn" class="btn btn-info">기본 정보 저장</button>
                    <?php endif; ?>
                </div>
                <div id="company-info-save-status" style="font-size: 12px; margin-top: 10px; text-align: center;"></div>
            </form>
        </div>
    </div>

    <div class="preview-area" id="preview_area" contenteditable="true">
        <p style="color: #888;">인쇄할 증명서를 선택하고 '미리보기'를 클릭하세요.</p>
    </div>
</div>

<!-- Template Edit Modal -->
<div id="template-edit-modal" class="modal" style="z-index: 1050;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="template-edit-modal-title">템플릿 수정</h3>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body">
            <div class="placeholder-list">
                <h5>사용 가능 치환자 (클릭하여 복사)</h5>
                <div id="placeholders">
                    <code>[고객명]</code> <code>[주민번호]</code> <code>[계약번호]</code> <code>[대출원금]</code> <code>[대출잔액]</code> <code>[대출일]</code> <code>[만기일]</code> <code>[약정일]</code> <code>[정상금리]</code> <code>[연체금리]</code> <code>[현재연체일수]</code> <code>[고객주소]</code> <code>[고객연락처]</code> <code>[회사명]</code> <code>[회사주소]</code> <code>[회사연락처]</code> <code>[회사대표]</code> <code>[담당자명]</code> <code>[담당자연락처]</code> <code>[오늘날짜]</code> <code>[완납일자]</code> <code>[최근거래일]</code> <code>[오늘이자금액]</code> <code>[완납금액]</code> <code>[법인인감]</code>
                </div>
            </div>
            <!-- Summernote 에디터가 적용될 textarea -->
            <textarea id="template-editor-textarea"></textarea>
        </div>
        <div class="modal-footer">
            <button type="button" id="modal-save-template-btn" class="btn btn-primary">저장</button>
            <button type="button" id="modal-close-btn" class="btn btn-secondary">닫기</button>
        </div>
    </div>
</div>
<hr style="margin: 40px 0;">

<?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
    <div id="template-list-container" class="template-management-container">
        <h3>증명서 템플릿 관리</h3>
        <p>각 증명서의 기본 양식을 수정할 수 있습니다. 템플릿이 비어있거나 잘못된 경우, 초기화 버튼을 눌러 기본값으로 되돌릴 수 있습니다.</p>
        <div id="template-list" style="margin-top: 20px;">
            <?php foreach ($certificate_templates as $template): ?>
                <div class="form-container" style="margin-bottom: 15px;">
                    <strong><?php echo htmlspecialchars($template['title']); ?> (<code><?php echo htmlspecialchars($template['template_key']); ?>.html</code>)</strong>
                    <div style="float: right; display: flex; gap: 5px;">
                        <button type="button" class="btn btn-sm btn-info save-as-default-btn" data-template-key="<?php echo $template['template_key']; ?>">현재값으로 기본값 저장</button>
                        <button type="button" class="btn btn-sm btn-warning initialize-single-btn" data-template-key="<?php echo $template['template_key']; ?>">초기화</button>
                        <button type="button" class="btn btn-sm btn-secondary edit-template-btn" data-template-key="<?php echo $template['template_key']; ?>" data-template-title="<?php echo htmlspecialchars($template['title']); ?>">수정</button>
                    </div>
                    <textarea class="template-content-storage" style="display:none;"><?php echo htmlspecialchars($template['content']); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('contract_search');
        const resultsDiv = document.getElementById('search_results');
        const contractIdInput = document.getElementById('contract_id');
        const previewBtn = document.getElementById('preview_btn');
        const previewArea = document.getElementById('preview_area');
        const certificateTypeSelect = document.getElementById('certificate_type');
        const thOptions = document.getElementById('transaction-history-options');
        const saveCompanyInfoBtn = document.getElementById('save-company-info-btn');

        // --- 계약 검색 ---
        searchInput.addEventListener('keyup', function() {
            const term = this.value;
            if (term.length < 2) {
                resultsDiv.innerHTML = '';
                resultsDiv.style.display = 'none';
                return;
            }

            fetch(`../process/search_contracts.php?term=${term}`)
                .then(response => response.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(contract => {
                            const div = document.createElement('div');
                            div.innerHTML = `<strong>${contract.customer_name}</strong> (계약번호: ${contract.contract_id}, 대출일: ${contract.loan_date})`;
                            div.classList.add('search-result-item');
                            div.addEventListener('click', function() {
                                window.location.href = `certificate_print.php?contract_id=${contract.contract_id}`;
                            });
                            resultsDiv.appendChild(div);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.style.display = 'none';
                    }
                });
        });

        // --- 증명서 종류 선택 시 옵션 표시 ---
        certificateTypeSelect.addEventListener('change', function() {
            thOptions.style.display = (this.value === 'transaction_history') ? 'block' : 'none';
        });
        // 페이지 로드 시 초기 상태 확인
        thOptions.style.display = (certificateTypeSelect.value === 'transaction_history') ? 'block' : 'none';

        // --- 미리보기 생성 ---
        previewBtn.addEventListener('click', function() {
            const contractId = contractIdInput.value;
            const certificateType = certificateTypeSelect.value;

            if (!contractId) {
                alert('먼저 계약을 검색하고 선택해주세요.');
                return;
            }
            if (!certificateType) {
                alert('증명서 종류를 선택해주세요.');
                return;
            }

            previewArea.innerHTML = '<p>증명서 내용을 불러오는 중입니다...</p>';

            let fetchUrl = `../process/certificate_process.php?contract_id=${contractId}&type=${certificateType}`;

            if (certificateType === 'transaction_history') {
                const startDate = document.getElementById('th_start_date').value;
                const endDate = document.getElementById('th_end_date').value;
                fetchUrl += `&start_date=${startDate}&end_date=${endDate}`;
            }

            fetch(fetchUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewArea.innerHTML = data.html;
                    } else {
                        previewArea.innerHTML = `<p style="color: red;">오류: ${data.message}</p>`;
                    }
                })
                .catch(error => {
                    previewArea.innerHTML = `<p style="color: red;">미리보기를 불러오는 중 오류가 발생했습니다.</p>`;
                    console.error('Error:', error);
                });
        });

        const printBtn = document.getElementById('print_btn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }

        // --- 치환자 복사 ---
        const placeholders = document.getElementById('placeholders');
        if (placeholders) {
            placeholders.addEventListener('click', function(e) {
                if (e.target.tagName === 'CODE') {
                    navigator.clipboard.writeText(e.target.textContent).then(() => {
                        const originalText = e.target.textContent;
                        e.target.textContent = '복사됨!';
                        setTimeout(() => {
                            e.target.textContent = originalText;
                        }, 1000);
                    });
                }
            });
        }

        // --- 회사 정보 저장 ---
        if (saveCompanyInfoBtn) {
            saveCompanyInfoBtn.addEventListener('click', function() {
                const form = document.getElementById('company-info-form');
                const formData = new FormData(form);
                const statusDiv = document.getElementById('company-info-save-status');
                statusDiv.textContent = '저장 중...';

                fetch('../process/company_info_process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            statusDiv.style.color = 'green';
                            statusDiv.textContent = '저장되었습니다.';
                        } else {
                            statusDiv.style.color = 'red';
                            statusDiv.textContent = '저장 실패: ' + data.message;
                        }
                        setTimeout(() => {
                            statusDiv.textContent = '';
                        }, 3000);
                    });
            });
        }
        // --- 템플릿 관리 ---
        const templateEditModal = document.getElementById('template-edit-modal');
        const templateEditorTextarea = document.getElementById('template-editor-textarea');
        const templateLivePreview = document.getElementById('template-live-preview');
        const modalSaveBtn = document.getElementById('modal-save-template-btn');
        let currentEditingTemplateKey = null;

        // Summernote 에디터 초기화
        $('#template-editor-textarea').summernote({
            lang: 'ko-KR',
            height: 400,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });

        // --- 템플릿 관리 ---
        const templateList = document.getElementById('template-list');
        if (templateList) {
            templateList.addEventListener('click', function(e) {
                // 수정 버튼
                if (e.target.classList.contains('edit-template-btn')) {
                    currentEditingTemplateKey = e.target.dataset.templateKey; // Use template_key
                    const title = e.target.dataset.templateTitle; // Get title from data attribute
                    const content = e.target.closest('.form-container').querySelector('.template-content-storage').value; // Get content from hidden textarea

                    document.getElementById('template-edit-modal-title').textContent = `템플릿 수정: ${title}`;
                    // Summernote 에디터에 내용 설정
                    $('#template-editor-textarea').summernote('code', content);
                    templateEditModal.style.display = 'block';
                }

                // 개별 초기화 버튼
                if (e.target.classList.contains('initialize-single-btn')) {
                    const key = e.target.dataset.templateKey;
                    if (confirm(`'${key}' 템플릿을 기본값으로 되돌리시겠습니까?`)) {
                        const formData = new FormData();
                        formData.append('action', 'initialize_single');
                        formData.append('template_key', key);
                        fetch('../process/certificate_template_process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                alert(data.message);
                                if (data.success) window.location.reload();
                            });
                    }
                }

                // 기본값으로 저장 버튼
                if (e.target.classList.contains('save-as-default-btn')) {
                    const key = e.target.dataset.templateKey;
                    const content = e.target.closest('.form-container').querySelector('.template-content-storage').value;
                    if (confirm(`'${key}' 템플릿의 현재 내용을 새로운 기본값으로 저장하시겠습니까?`)) {
                        const formData = new FormData();
                        formData.append('action', 'save_as_default');
                        formData.append('template_key', key);
                        formData.append('content', content);
                        fetch('../process/certificate_template_process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                alert(data.message);
                            });
                    }
                }
            });
        }

        // 모달 닫기
        const closeModal = function() {
            templateEditModal.style.display = 'none';
            currentEditingTemplateKey = null; // Reset key
        };
        templateEditModal.querySelector('.close-button').addEventListener('click', closeModal);
        const modalCloseBtn = document.getElementById('modal-close-btn');
        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', closeModal);
        }

        // 모달 저장 버튼
        modalSaveBtn.addEventListener('click', function() {
            if (!currentEditingTemplateKey) return; // Check for key

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('template_key', currentEditingTemplateKey); // Send key
            formData.append('content', $('#template-editor-textarea').summernote('code')); // Summernote 에디터에서 내용 가져오기

            fetch('../process/certificate_template_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        // 성공 시 숨겨진 textarea 내용도 업데이트
                        const originalTextarea = document.querySelector(`.edit-template-btn[data-template-key="${currentEditingTemplateKey}"]`).closest('.form-container').querySelector('.template-content-storage');
                        if (originalTextarea) originalTextarea.value = $('#template-editor-textarea').summernote('code');
                        templateEditModal.style.display = 'none';
                        currentEditingTemplateKey = null; // Reset key
                    }
                });
        });
    });
</script>

<?php include 'footer.php'; ?>