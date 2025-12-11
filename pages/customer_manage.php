<?php
include('../process/customer_process.php');
include('header.php');

// --- Pagination and Search Setup ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (isset($_GET['limit'])) {
    $_SESSION['customer_limit'] = (int)$_GET['limit'];
} else if (!isset($_SESSION['customer_limit'])) {
    $_SESSION['customer_limit'] = 20;
}
$limit = $_SESSION['customer_limit'];
$limit_options = [5, 10, 15, 20, 50, 100];
$limit = in_array($limit, $limit_options) ? $limit : 20;

$customer_data = getCustomers($link, $search_term, $page, $limit);
$customers = $customer_data['data'];
$total_records = $customer_data['total'];
$total_pages = ceil($total_records / $limit);

function format_phone_number($phone)
{
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) == 11) return substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
    if (strlen($phone) == 10) return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    return $phone;
}

$banks = ['경남은행', '광주은행', 'KB국민은행', 'iM뱅크', 'NH농협은행', 'IBK기업은행', '대구은행', '부산은행', '산림조합중앙회', '상호저축은행', '저축은행중앙회', '새마을금고', '신용협동조합', '신한은행', '수협은행', 'SC제일은행', '우리은행', '우체국', '전북은행', '제주은행', '카카오뱅크', '케이뱅크', '토스뱅크', '하나은행', '한국산업은행', '한국수출입은행', '한국씨티은행', '미래에셋증권', '삼성증권', '신한투자증권', '키움증권', '한국투자증권', 'NH투자증권'];
sort($banks);
?>

<h2>고객관리</h2>

<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
    <span>총 고객: <?php echo number_format($total_records); ?>명</span>
    <button type="button" class="btn btn-primary" onclick="toggleCustomerForm()">고객 등록</button>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div class="msg"><?php echo $_SESSION['message'];
                        unset($_SESSION['message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="msg error-msg"><?php echo $_SESSION['error_message'];
                                unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<!-- Search and Limit Form -->
<div class="search-form-container">
    <form action="customer_manage.php" method="get" class="search-form-flex">
        <div class="form-col" style="flex: 3;">
            <label>고객 검색</label>
            <input type="text" name="search" placeholder="이름 또는 연락처로 검색" value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <div class="form-col">
            <label>페이지당 표시 수</label>
            <select name="limit" id="limit" onchange="this.form.submit()">
                <?php foreach ($limit_options as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php if ($limit == $opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-col">
            <button type="submit" class="btn btn-primary">검색</button>
        </div>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>고객번호</th>
                <th>이름</th>
                <th>주민번호</th>
                <th>연락처</th>
                <th>등본상 주소<br><span style="color: #0257f5ff;">실거주 주소(또는 담보권 주소)</span></th>
                <th>메모</th>
                <th colspan="4">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $row) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><a href="customer_detail.php?id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                    <td><?php echo htmlspecialchars($row['resident_id_partial']); ?></td>
                    <td><?php echo htmlspecialchars(format_phone_number($row['phone'])); ?></td>
                    <td><?php echo htmlspecialchars($row['address_registered']); ?><br><span style="color: #0257f5ff; font-size: 12px;"><?php echo htmlspecialchars($row['address_actual']); ?></span></td>
                    <td class="memo-cell" onclick="showMemo(this)">
                        <span class="memo-short"><?php echo htmlspecialchars(mb_strimwidth($row['memo'], 0, 20, "...")); ?></span>
                        <div class="memo-full" style="display:none;"><?php echo nl2br(htmlspecialchars($row['memo'])); ?></div>
                    </td>
                    <td><a href="contract_manage.php?customer_id=<?php echo $row['id']; ?>#selected_customer_info" class="btn btn-sm add_btn">계약추가</a></td>
                    <td><a href="customer_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm view_btn">상세</a></td>
                    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                        <td><a href="customer_manage.php?edit=<?php echo $row['id']; ?>#customer_form" class="btn btn-sm edit_btn">수정</a></td>
                        <td><a href="../process/customer_process.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm del_btn" onclick="return confirm('정말 이 고객을 삭제하시겠습니까?');">삭제</a></td>
                    <?php endif; ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<hr style="margin: 30px 0;">
<div class="pagination">
    <?php
    $range = 2;
    $query_params = $_GET;
    unset($query_params['page']);
    $query_string = http_build_query($query_params);
    echo "<a href='?page=1&$query_string'>&lt;&lt;</a> ";
    $prev_page = max(1, $page - 1);
    echo "<a href='?page=$prev_page&$query_string'>&lt;</a> ";
    for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) {
        echo ($i == $page) ? "<strong>$i</strong> " : "<a href='?page=$i&$query_string'>$i</a> ";
    }
    $next_page = min($total_pages, $page + 1);
    echo "<a href='?page=$next_page&$query_string'>&gt;</a> ";
    echo "<a href='?page=$total_pages&$query_string'>&gt;&gt;</a>";
    ?>
</div>

<hr style="margin: 30px 0;">

<!-- Add/Edit Customer Form -->
<div id="customer_form" class="form-container" style="display: <?php echo $update ? 'block' : 'none'; ?>;">
    <h3><?php echo $update ? '고객 정보 수정' : '신규 고객 추가'; ?></h3>
    <form method="post" action="../process/customer_process.php">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="form-grid customer-form-grid">
            <div class="form-col">
                <label>이름</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>
            <div class="form-col">
                <label>주민번호 (앞 6자리-뒤 1자리)</label>
                <input type="text" name="resident_id_partial" value="<?php echo htmlspecialchars($resident_id_partial); ?>" placeholder="예: 740125-1" required>
            </div>
            <div class="form-col">
                <label>휴대폰 연락처</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>
            <div class="form-col">
                <label>신청거래처</label>
                <input type="text" name="application_source" value="<?php echo htmlspecialchars($application_source ?? ''); ?>">
            </div>
            <div class="form-col">
                <label>대출신청금액</label>
                <input type="number" name="requested_loan_amount" value="<?php echo htmlspecialchars($requested_loan_amount ?? ''); ?>">
            </div>
            <div class="form-col">
                <label>대출신청일</label>
                <input type="date" name="loan_application_date" value="<?php echo htmlspecialchars($loan_application_date ?? ''); ?>">
            </div>
            <div class="form-col">
                <label>입금은행</label>
                <input style="height: 38px;" list="banks" name="bank_name" value="<?php echo htmlspecialchars($bank_name ?? ''); ?>">
                <datalist id="banks"><?php foreach ($banks as $bank): ?><option value="<?php echo $bank; ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="form-col">
                <label>입금계좌</label>
                <input type="text" name="account_number" value="<?php echo htmlspecialchars($account_number ?? ''); ?>">
            </div>
            <div class="form-col grid-full-width">
                <label>등본상 주소</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="address_registered" name="address_registered" value="<?php echo htmlspecialchars($address_registered); ?>" style="flex-grow: 1;" placeholder="주소 검색 버튼을 클릭하세요">
                    <button type="button" onclick="openDaumPostcode('address_registered')" class="btn btn-secondary">주소 검색</button>
                </div>
            </div>
            <div class="form-col grid-full-width">
                <label>실거주 주소(또는 담보권주소)</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="address_actual" name="address_actual" value="<?php echo htmlspecialchars($address_actual); ?>" style="flex-grow: 1;" placeholder="주소 검색 버튼을 클릭하세요">
                    <button type="button" onclick="openDaumPostcode('address_actual')" class="btn btn-secondary">주소 검색</button>
                </div>
                <div style="margin-top: 8px;">
                    <button type="button" onclick="copyAddress()" class="btn btn-secondary" style="width: fit-content;">등본상 주소와 동일</button>
                </div>
            </div>
            <div class="form-col">
                <label>담당자</label>
                <input type="text" name="manager" value="<?php echo htmlspecialchars($manager ?? ''); ?>">
            </div>
            <div class="form-col grid-full-width">
                <label>메모</label>
                <textarea name="memo" rows="4"><?php echo htmlspecialchars($memo ?? ''); ?></textarea>
            </div>
        </div>
        <div class="form-buttons" style="margin-top: 20px; text-align: right;">
            <?php if ($update == true): ?>
                <button class="btn btn-primary" type="submit" name="update">수정</button>
            <?php else: ?>
                <button class="btn btn-primary" type="submit" name="save">저장</button>
            <?php endif; ?>
            <a href="customer_manage.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<!-- Memo Modal -->
<div id="memoModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <p id="memoModalContent"></p>
    </div>
</div>

<!-- Daum 우편번호 서비스 API 스크립트 -->
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
    function openDaumPostcode(targetId) {
        new daum.Postcode({
            oncomplete: function(data) {
                // 팝업에서 검색결과 항목을 클릭했을때 실행할 코드를 작성하는 부분.
                var addr = ''; // 주소 변수

                //사용자가 선택한 주소 타입에 따라 해당 주소 값을 가져온다.
                if (data.userSelectedType === 'R') { // 사용자가 도로명 주소를 선택했을 경우
                    addr = data.roadAddress;
                } else { // 사용자가 지번 주소를 선택했을 경우(J)
                    addr = data.jibunAddress;
                }
                // 선택된 주소를 해당 input에 넣는다.
                document.getElementById(targetId).value = addr;
            }
        }).open();
    }

    function copyAddress() {
        document.getElementById('address_actual').value = document.getElementById('address_registered').value;
    }

    var modal = document.getElementById("memoModal");
    var span = document.getElementsByClassName("close-btn")[0];
    if (span) {
        span.onclick = function() {
            modal.style.display = "none";
        }
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function showMemo(cell) {
        var fullMemo = cell.querySelector('.memo-full').innerHTML;
        document.getElementById('memoModalContent').innerHTML = fullMemo;
        modal.style.display = "block";
    }

    function toggleCustomerForm() {
        var form = document.getElementById('customer_form');
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            form.scrollIntoView({
                behavior: 'smooth'
            });
        } else {
            form.style.display = 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>