<?php
include('header.php');
include_once(__DIR__ . '/../common.php');
?>

<h2>은행거래내역 가져오기 (입금 자동화)</h2>

<div class="form-container">
    <p>우리은행 엑셀 파일을 업로드하여 입금 내역을 자동으로 매칭하고 일괄 처리합니다.</p>
    <p class="text-muted">지원 파일: .xlsx (우리은행 거래내역조회 엑셀 양식)</p>

    <div id="upload-section">
        <form id="deposit-upload-form" enctype="multipart/form-data">
            <div class="input-group">
                <label for="excel_file">엑셀 파일 선택:</label>
                <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary" id="upload-btn">파일 분석 및 매칭</button>
        </form>
    </div>
</div>

<div id="preview-section" style="display:none; margin-top: 30px;">
    <h3>매칭 결과 확인</h3>
    <p>시스템이 자동으로 매칭한 결과를 확인하고, 처리할 항목을 선택하세요.</p>

    <div class="form-buttons" style="margin-bottom: 10px;">
        <button type="button" id="process-selected-btn" class="btn btn-success">선택 항목 입금 반영</button>
        <button type="reset" class="btn btn-secondary" onclick="location.reload()">초기화</button>
    </div>

    <div class="table-container">
        <table id="preview-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>상태</th>
                    <th>거래일자</th>
                    <th>입금자명</th>
                    <th>입금액</th>
                    <th>매칭 고객</th>
                    <th>매칭 계약</th>
                    <th>메모</th>
                </tr>
            </thead>
            <tbody id="preview-tbody">
                <!-- Data will be populated here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Contract Selection Modal for "Multiple" or "No Match" -->
<div id="contract-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>계약 선택 / 검색</h3>
            <span class="close-button">&times;</span>
        </div>
        <p style="color: #fc5c00ff; font-weight: bold;"> ※ [계약번호] 또는 [고객명] 입력후 엔터로 검색후 선택하여 변경한다</p>

        <div class="modal-body">
            <input type="text" id="modal-search" placeholder="고객명 검색..." style="width: 100%; padding: 8px; margin-bottom: 10px;">
            <div id="modal-results" style="max-height: 300px; overflow-y: auto;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary close-button-btn">닫기</button>
        </div>
    </div>
</div>

<style>
    .status-ok {
        color: green;
        font-weight: bold;
    }

    .status-warn {
        color: orange;
        font-weight: bold;
    }

    .status-err {
        color: red;
        font-weight: bold;
    }

    .match-row.status-err {
        background-color: #fff0f0;
    }

    .match-row.status-warn {
        background-color: #fffbf0;
    }

    .match-row.status-ok {
        background-color: #f0fff4;
    }

    .status-duplicate {
        color: #d35400;
        /* Dark Orange */
        font-weight: bold;
    }

    .match-row.status-duplicate {
        background-color: #fff8e1;
        /* Light Orange/Yellow */
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 25px;
        border: 1px solid #888;
        width: 500px;
        border-radius: 8px;
    }

    .close-button {
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .contract-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }

    .contract-item:hover {
        background-color: #f9f9f9;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadForm = document.getElementById('deposit-upload-form');
        const previewSection = document.getElementById('preview-section');
        const previewTbody = document.getElementById('preview-tbody');
        const selectAllDetails = document.getElementById('select-all');
        let currentRowIndex = null; // For modal selection
        let parsedData = []; // Store full data

        // Upload Handler
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'upload');

            const btn = document.getElementById('upload-btn');
            const originalBtnText = btn.textContent;
            btn.textContent = '분석중...';
            btn.disabled = true;

            fetch('../process/deposit_upload_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewSection.style.display = 'block';
                        parsedData = data.data;
                        renderTable(parsedData);
                    } else {
                        alert('오류: ' + data.message);
                    }
                })
                .catch(err => alert('서버 통신 오류: ' + err))
                .finally(() => {
                    btn.textContent = originalBtnText;
                    btn.disabled = false;
                });
        });

        function renderTable(rows) {
            previewTbody.innerHTML = '';
            rows.forEach((row, index) => {
                const tr = document.createElement('tr');
                let rowClass = 'status-err';
                if (row.status_code === 'OK') rowClass = 'status-ok';
                else if (row.status_code === 'MULTIPLE') rowClass = 'status-warn';
                else if (row.status_code === 'DUPLICATE') rowClass = 'status-duplicate';

                tr.className = 'match-row ' + rowClass;

                let statusHtml = '';
                if (row.status_code === 'OK') statusHtml = '<span class="status-ok">완료</span>';
                else if (row.status_code === 'MULTIPLE') statusHtml = '<span class="status-warn">확인필요</span>';
                else if (row.status_code === 'DUPLICATE') statusHtml = '<span class="status-duplicate">중복의심</span>';
                else statusHtml = '<span class="status-err">매칭실패</span>';

                // Contract Selection HTML
                let contractHtml = '';
                if (row.contract_id) {
                    contractHtml = `<span>${row.contract_info}</span> <button type="button" class="btn btn-sm btn-secondary change-contract-btn" data-index="${index}">변경</button>`;
                } else {
                    contractHtml = `<button type="button" class="btn btn-sm btn-primary select-contract-btn" data-index="${index}">계약 찾기</button>`;
                }

                // Checkbox: Only check OK by default. DUPLICATE should be unchecked.
                const isChecked = row.status_code === 'OK' ? 'checked' : '';

                tr.innerHTML = `
                <td><input type="checkbox" class="row-check" data-index="${index}" ${isChecked}></td>
                <td>${statusHtml}</td>
                <td>${row.date}</td>
                <td>${row.depositor}</td>
                <td>${new Intl.NumberFormat().format(row.amount)}</td>
                <td>${row.customer_name || '-'}</td>
                <td id="contract-cell-${index}">${contractHtml}</td>
                <td><input type="text" class="form-control form-control-sm row-memo" value="${row.memo || ''}" style="width:100%;"></td>
            `;
                previewTbody.appendChild(tr);
            });

            // Re-attach event listeners
            document.querySelectorAll('.select-contract-btn, .change-contract-btn').forEach(btn => {
                btn.addEventListener('click', openContractModal);
            });
        }

        // Modal Logic
        const modal = document.getElementById('contract-modal');
        const modalSearch = document.getElementById('modal-search');
        const modalResults = document.getElementById('modal-results');
        const closeButtons = document.querySelectorAll('.close-button, .close-button-btn');

        function openContractModal(e) {
            currentRowIndex = e.target.dataset.index;
            modal.style.display = 'block';
            modalSearch.value = '';
            modalResults.innerHTML = '';
            modalSearch.focus();

            // Auto search if we have depositor name
            const depositor = parsedData[currentRowIndex].depositor;
            if (depositor) {
                modalSearch.value = depositor;
                searchContracts(depositor);
            }
        }

        modalSearch.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') searchContracts(this.value);
        });

        function searchContracts(keyword) {
            modalResults.innerHTML = '검색중...';
            fetch(`../process/contract_process.php?action=search&keyword=${encodeURIComponent(keyword)}`)
                .then(res => res.json())
                .then(data => {
                    modalResults.innerHTML = '';
                    if (data.length === 0) {
                        modalResults.innerHTML = '<p>검색 결과가 없습니다.</p>';
                        return;
                    }
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'contract-item';
                        div.innerHTML = `<strong>[${item.id}] ${item.customer_name}</strong> - ${item.product_name} (${new Intl.NumberFormat().format(item.loan_amount)}원) <br> <small>현재잔액: ${new Intl.NumberFormat().format(item.current_outstanding_principal)}원</small>`;
                        div.addEventListener('click', () => selectContract(item));
                        modalResults.appendChild(div);
                    });
                })
                .catch(err => modalResults.innerHTML = '오류: ' + err);
        }

        function selectContract(contract) {
            // Update local data
            parsedData[currentRowIndex].contract_id = contract.id;
            parsedData[currentRowIndex].contract_info = `[${contract.id}] ${contract.customer_name} (${contract.product_name})`;
            parsedData[currentRowIndex].customer_name = contract.customer_name;
            parsedData[currentRowIndex].status_code = 'OK'; // Manually selected is OK

            // Re-render specifically this row or whole table. Simpler to re-render whole table or update DOM.
            // Let's re-render table to update status colors etc.
            renderTable(parsedData);
            modal.style.display = 'none';
        }

        closeButtons.forEach(btn => btn.onclick = () => modal.style.display = 'none');
        window.onclick = (e) => {
            if (e.target == modal) modal.style.display = 'none';
        };

        // Process Button
        document.getElementById('process-selected-btn').addEventListener('click', function() {
            const selectedIndices = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.dataset.index);

            if (selectedIndices.length === 0) {
                alert('처리할 항목을 선택해주세요.');
                return;
            }

            // Validate selection
            const selectedData = selectedIndices.map(idx => {
                const row = parsedData[idx];
                // get memo from input
                const tr = previewTbody.children[idx];
                row.memo = tr.querySelector('.row-memo').value;
                return row;
            });

            // Check for missing contracts
            const missingContract = selectedData.find(d => !d.contract_id);
            if (missingContract) {
                alert('계약이 선택되지 않은 항목이 있습니다. 계약을 선택하거나 체크를 해제해주세요.');
                return;
            }

            if (!confirm(`${selectedData.length}건의 입금을 처리하시겠습니까?`)) return;

            const btn = this;
            btn.disabled = true;
            btn.textContent = '처리중...';

            fetch('../process/deposit_upload_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'process',
                        deposits: selectedData
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('처리 중 오류 발생: ' + data.message + (data.errors ? '\n' + data.errors.join('\n') : ''));
                    }
                })
                .catch(err => alert('통신 오류: ' + err))
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = '선택 항목 입금 반영';
                });
        });

        // Select All
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    });
</script>

<?php include 'footer.php'; ?>