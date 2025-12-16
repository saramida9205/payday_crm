<?php
include('header.php');
include_once('../process/intranet_process.php');

$current_user_id = $_SESSION['id'];
$employees = getEmployeesForMessaging($link); // This function is defined in intranet_process.php
$contracts = getActiveContractsForDropdown($link); // For linking contracts

// --- Pagination ---
$page_recv = isset($_GET['page_recv']) ? (int)$_GET['page_recv'] : 1;
$page_sent = isset($_GET['page_sent']) ? (int)$_GET['page_sent'] : 1;
$limit = 10; // Messages per page

// Fetch all message data in one go
$message_data = getMessagesForUser($link, $current_user_id, $limit, $page_recv, $page_sent);
$received_messages = $message_data['received'];
$sent_messages = $message_data['sent'];
$total_recv = $message_data['total_recv'];
$total_sent = $message_data['total_sent'];

$total_pages_recv = ceil($total_recv / $limit);
$total_pages_sent = ceil($total_sent / $limit);

function render_pagination($total_pages, $current_page, $param_name)
{
    if ($total_pages <= 1) return;
    echo '<nav><ul class="pagination justify-content-center" style="margin-top: 20px;">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $url = http_build_query(array_merge($_GET, [$param_name => $i]));
        echo "<li class='page-item $active'><a class='page-link' href='?$url'>$i</a></li>";
    }
    echo '</ul></nav>';
}
?>
<style>
    .message-item {
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }

    .message-item:hover {
        background-color: #f8f9fa;
    }

    .pagination {
        margin-top: 15px;
    }

    /* 표 전체를 감싸는 컨테이너 스타일 */
    .grid-table {
        display: grid;
        /* 핵심: 1fr(비율)을 4번 반복하여 4열을 만듭니다 */
        grid-template-columns: repeat(4, 1fr);

        /* 선택 사항: 셀 사이의 간격 및 테두리 */
        gap: 1px;
        /* 셀 사이 간격 (테두리 효과를 위해) */
        background-color: #ccc;
        /* 테두리 색상 */
        border: 1px solid #ccc;
        /* 전체 외곽선 */
        width: 100%;
        /* 표 너비 */
        margin: 0 auto;

    }

    /* 각 셀(Cell) 스타일 */
    .grid-cell {
        background-color: #fff;
        /* 배경색 */
        padding: 5px;
        /* 내부 여백 */
        text-align: center;
        /* 텍스트 중앙 정렬 */
        font-size: 13px;
        font-weight: normal;
    }

    /* (선택 사항) 첫 번째 줄만 색상을 다르게 하고 싶다면 */
    .grid-cell:nth-child(-n+4) {
        background-color: #f0f8ff;
        /* 연한 파란색 */
    }
</style>

<h2>인트라넷</h2>
<!--
<div id="current-time-display" style="font-size: 16px; color: #555; text-align: right; margin-bottom: 15px;"></div>
-->
<?php
$company_info = get_all_company_info($link);
?>
<label for="company_name" style="margin-bottom: 15px; margin-top: 15px;"><strong>&nbsp;&nbsp;회사명: <?php echo htmlspecialchars($company_info['company_name'] ?? ''); ?></strong></label>
<div class="grid-table" style="margin-bottom: 0px;">
    <div class="grid-cell">대표자</div>
    <div class="grid-cell"><a href="/uploads/company/regcert.png" target="_blank">사업자번호</a></div>
    <div class="grid-cell"><a href="/uploads/company/loancert.png" target="_blank">대부업등록번호</a></div>
    <div class="grid-cell"><a href="tel:<?php echo htmlspecialchars($company_info['company_phone'] ?? '02-0000-0000'); ?>">대표번호</a></div>

    <div class="grid-cell"><?php echo htmlspecialchars($company_info['ceo_name'] ?? '홍길동'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['biz_reg_number'] ?? '000-00-00000'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['loan_biz_reg_number'] ?? '0000-서울-0000(대부업)'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['company_phone'] ?? '02-0000-0000'); ?></div>
</div>
<div class="grid-table" style="margin-bottom: 30px;">
    <div class="grid-cell">팩스번호</div>
    <div class="grid-cell"><a href="mailto:<?php echo htmlspecialchars($company_info['company_email'] ?? 'test@test.com'); ?>">Email</a></div>
    <div class="grid-cell"><a href="/uploads/company/bank01.png" target="_blank">이자집금계좌</a></div>
    <div class="grid-cell"><a href="/uploads/company/bank02.png" target="_blank">경비계좌</a></div>

    <div class="grid-cell"><?php echo htmlspecialchars($company_info['company_fax'] ?? '02-0000-0000'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['company_email'] ?? 'test@test.com'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['interest_account'] ?? '은행명 0000-0000-0000'); ?></div>
    <div class="grid-cell"><?php echo htmlspecialchars($company_info['expense_account'] ?? '은행명 0000-0000-0000'); ?></div>
</div>

<!-- 공지사항 게시판 섹션 -->
<div class="notice-board-container">
    <div class="notice-header">
        <h3>📢 사내 공지사항</h3>
        <?php if ($_SESSION['permission_level'] == '0' || $_SESSION['permission_level'] == 'admin'): ?>
            <button class="btn btn-primary btn-sm" onclick="openNoticeWriteModal()">📝 글쓰기</button>
        <?php endif; ?>
    </div>
    <ul class="notice-list" id="notice-list-container">
        <li style="text-align: center; padding: 20px; color: #777;">공지사항을 불러오는 중...</li>
    </ul>
    <div style="text-align: center; margin-top: 15px; display: none;" id="notice-load-more-container">
        <!-- 더보기 기능은 추후 확장 가능 -->
    </div>
</div>

<div class="messaging-container">
    <!-- Send Message Form -->
    <div class="form-container">
        <h3>내부 메시지 보내기</h3>
        <form id="send-message-form" action="../process/intranet_process.php" method="post">
            <input type="hidden" name="action" value="send_message">
            <div class="form-col" style="margin-bottom: 15px;">
                <label for="recipient_id">받는 사람</label>
                <select name="recipient_id" id="recipient_id" required>
                    <option value="">-- 직원을 선택하세요 --</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col" style="margin-bottom: 15px;">
                <label for="message_text">메시지 내용</label>
                <textarea name="message_text" id="message_text" rows="5" required></textarea>
            </div>
            <div class="form-col" style="margin-bottom: 20px;">
                <label for="contract_id">참고 계약 (선택)</label>
                <select name="contract_id" id="contract_id">
                    <option value="">-- 계약을 선택하세요 --</option>
                    <?php foreach ($contracts as $contract): ?>
                        <option value="<?php echo $contract['id']; ?>"><?php echo $contract['contract_info']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">보내기</button>
        </form>
    </div>

    <!-- Received Messages -->
    <div>
        <div class="tab-nav">
            <a href="#" class="tab-link active" data-tab="received">받은 메시지</a>
            <a href="#" class="tab-link" data-tab="sent">보낸 메시지</a>
        </div>

        <div id="received" class="tab-content active">
            <h3>받은 메시지</h3>
            <div class="form-check" style="margin-bottom: 10px;">
                <input type="checkbox" id="show-unread-only" class="form-check-input">
                <label for="show-unread-only" class="form-check-label" style="user-select: none; cursor: pointer;">읽지 않은 메시지만 보기</label>
            </div>
            <div class="message-list" id="received-message-list">
                <?php if (empty($received_messages)): ?>
                    <p>받은 메시지가 없습니다.</p>
                    <?php else: foreach ($received_messages as $msg):
                        $is_unread = is_null($msg['read_at']);
                        $contract_link_html = !empty($msg['contract_id']) ? '<br><a href="customer_detail.php?id=' . $msg['customer_id'] . '" target="_blank">관련 계약 보기 (계약번호: ' . $msg['contract_id'] . ')</a>' : '';
                    ?>
                        <div
                            class="message-item <?php echo $is_unread ? 'unread' : ''; ?>"
                            id="msg-item-<?php echo $msg['id']; ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#individualMessageModal"
                            data-message-id="<?php echo $msg['id']; ?>"
                            data-is-unread="<?php echo $is_unread ? '1' : '0'; ?>"
                            data-sender-id="<?php echo $msg['sender_id']; ?>"
                            data-contract-id="<?php echo $msg['contract_id']; ?>"
                            data-customer-id="<?php echo htmlspecialchars($msg['customer_id'] ?? ''); ?>"
                            data-type="received"
                            data-sender-name="<?php echo htmlspecialchars($msg['sender_name']); ?>"
                            data-time="<?php echo $msg['created_at']; ?>"
                            data-text="<?php echo htmlspecialchars(str_replace('\r\n', "\n", $msg['message_text'])); ?>">
                            <div class="message-meta"><strong>From:</strong> <?php echo htmlspecialchars($msg['sender_name']); ?> | <strong>Time:</strong> <?php echo $msg['created_at']; ?></div>
                            <div class="message-text"><?php echo nl2br(htmlspecialchars(mb_strimwidth($msg['message_text'], 0, 100, "..."))); ?></div>
                            <?php if (!empty($msg['contract_id'])): ?>
                                <div class="message-contract-link" style="font-size: 12px; color: #0d6efd;">관련 계약 있음</div>
                            <?php endif; ?>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
            <?php render_pagination($total_pages_recv, $page_recv, 'page_recv'); ?>
        </div>

        <div id="sent" class="tab-content">
            <h3>보낸 메시지</h3>
            <div class="message-list">
                <?php if (empty($sent_messages)): ?>
                    <p>보낸 메시지가 없습니다.</p>
                    <?php else: foreach ($sent_messages as $msg):
                        $contract_link_html = !empty($msg['contract_id']) ? '<br><a href="customer_detail.php?id=' . $msg['customer_id'] . '" target="_blank">관련 계약 보기 (계약번호: ' . $msg['contract_id'] . ')</a>' : '';
                    ?>
                        <div
                            class="message-item"
                            id="msg-item-<?php echo $msg['id']; ?>"
                            data-bs-toggle="modal"
                            data-type="sent"
                            data-message-id="<?php echo $msg['id']; ?>"
                            data-bs-target="#individualMessageModal"
                            data-recipient-name="<?php echo htmlspecialchars($msg['recipient_name']); ?>"
                            data-customer-id="<?php echo htmlspecialchars($msg['customer_id'] ?? ''); ?>"
                            data-time="<?php echo $msg['created_at']; ?>"
                            data-text="<?php echo htmlspecialchars(str_replace('\r\n', "\n", $msg['message_text'])); ?>">
                            <div class="message-meta"><strong>To:</strong> <?php echo htmlspecialchars($msg['recipient_name']); ?> | <strong>Time:</strong> <?php echo $msg['created_at']; ?></div>
                            <div class="message-text"><?php echo nl2br(htmlspecialchars(mb_strimwidth($msg['message_text'], 0, 100, "..."))); ?></div>
                            <?php if (!empty($msg['contract_id'])): ?><div class="message-contract-link" style="font-size: 12px; color: #0d6efd;">관련 계약 있음</div><?php endif; ?>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
            <?php render_pagination($total_pages_sent, $page_sent, 'page_sent'); ?>
        </div>
    </div>
</div>

<!-- New Message Notification Modal -->
<div id="message-modal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3>새 메시지 알림</h3>
        <div id="message-modal-content" style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;"></div>
        <div class="form-buttons" style="text-align: right; margin-top: 15px;">
            <button id="dismiss-today-btn" class="btn btn-secondary">오늘 하루 보지 않기</button>
            <button id="mark-all-read-btn" class="btn btn-primary">모두 읽음 & 닫기</button>
        </div>
    </div>
</div>

<!-- Individual Message Display Modal -->
<div class="modal fade" id="individualMessageModal" tabindex="-1" aria-labelledby="individualMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="individualMessageModalLabel">메시지 상세 보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 14px; color: #6c757d; margin-bottom: 15px;"><strong id="modal-header"></strong><br><span id="modal-time"></span></p>
                <p id="modal-text" style="white-space: pre-wrap; word-break: break-all;"></p>
                <div id="modal-contract-link"></div>
                <div id="reply-form-container" style="display: none; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <h5>답장하기</h5>
                    <textarea id="reply-message-text" class="form-control" rows="4" placeholder="답장 내용을 입력하세요..."></textarea>
                    <button id="send-reply-btn" class="btn btn-primary btn-sm" style="margin-top: 10px;">전송</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="reply-btn" class="btn btn-info" style="display: none;">답장하기</button>
                <button type="button" id="delete-btn" class="btn btn-danger">삭제</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Current Time Display ---
        function updateTime() {
            const timeDisplay = document.getElementById('current-time-display');
            if (timeDisplay) {
                const now = new Date();
                const options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                };
                timeDisplay.textContent = '현재 시간: ' + now.toLocaleString('ko-KR', options);
            }
        }
        updateTime();
        setInterval(updateTime, 1000);


        // Tab functionality
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');

        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.dataset.tab;

                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Unread messages filter functionality
        const showUnreadOnlyCheckbox = document.getElementById('show-unread-only');
        if (showUnreadOnlyCheckbox) {
            showUnreadOnlyCheckbox.addEventListener('change', function() {
                const messageList = document.getElementById('received-message-list');
                const allMessages = messageList.querySelectorAll('.message-item');

                if (this.checked) {
                    allMessages.forEach(message => {
                        // Hide read messages, show unread ones
                        message.style.display = message.classList.contains('unread') ? '' : 'none';
                    });
                } else {
                    // Show all messages
                    allMessages.forEach(message => {
                        message.style.display = '';
                    });
                }
            });
        }

        const modal = document.getElementById('message-modal');
        const modalContent = document.getElementById('message-modal-content');
        const closeBtn = modal.querySelector('.close-button');
        const markAllReadBtn = document.getElementById('mark-all-read-btn');
        const dismissTodayBtn = document.getElementById('dismiss-today-btn');

        function checkForNewMessages() {
            // '오늘 하루 보지 않기'를 눌렀는지 확인
            if (sessionStorage.getItem('dismiss_message_popup_today') === 'true') {
                return;
            }

            fetch('../process/intranet_process.php?action=check_new_messages')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        let content = '';
                        const messageIds = [];
                        data.messages.forEach(message => {
                            messageIds.push(message.id);
                            content += `
                            <div class="message-item" style="border-bottom: 1px solid #eee; padding: 10px 0;">
                                <p style="margin:0; font-size: 12px; color: #6c757d;">
                                    <strong>From:</strong> ${escapeHTML(message.sender_name)} | <strong>Time:</strong> ${escapeHTML(message.created_at)}
                                </p>
                                <p style="margin:5px 0;">${escapeHTML(message.message_text).replace(/\n/g, '<br>')}</p>
                        `;
                            if (message.contract_id) {
                                content += `<p style="margin:0;"><a href="customer_detail.php?id=${message.customer_id}" target="_blank">관련 계약 보기 (계약번호: ${message.contract_id})</a></p>`;
                            }
                            content += `</div>`;
                        });

                        modalContent.innerHTML = content;
                        modal.style.display = 'block';

                        // '모두 읽음' 버튼에 message ID들 저장
                        markAllReadBtn.dataset.messageIds = JSON.stringify(messageIds);
                        dismissTodayBtn.dataset.messageIds = JSON.stringify(messageIds);
                    }
                })
                .catch(error => console.error('Error checking for new messages:', error));
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString().replace(/[&<>"']/g, function(match) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [match];
            });
        }

        // Check for new messages once when the page loads
        checkForNewMessages();

        // Modal close logic
        function closeModal() {
            modal.style.display = 'none';
        }

        closeBtn.onclick = closeModal;
        window.onclick = (event) => {
            if (event.target == modal) {
                closeModal();
            }
        };

        markAllReadBtn.addEventListener('click', function() {
            const messageIds = JSON.parse(this.dataset.messageIds || '[]');
            if (messageIds.length > 0) {
                fetch(`../process/intranet_process.php?action=mark_as_read_bulk`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            message_ids: messageIds
                        })
                    }).then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload(); // 성공적으로 읽음 처리 후 페이지 새로고침
                        }
                    });
            }
            closeModal();
        });

        dismissTodayBtn.addEventListener('click', function() {
            sessionStorage.setItem('dismiss_message_popup_today', 'true');
            // '오늘 하루 보지 않기'를 눌러도 메시지는 읽음 처리합니다.
            markAllReadBtn.click();
        });

        // --- Individual Message Modal Logic ---
        const individualMessageModal = document.getElementById('individualMessageModal');
        if (individualMessageModal) {
            individualMessageModal.addEventListener('show.bs.modal', function(event) {
                // Reset reply form on show
                const replyForm = individualMessageModal.querySelector('#reply-form-container');
                const replyText = individualMessageModal.querySelector('#reply-message-text');
                replyForm.style.display = 'none';
                replyText.value = '';

                const triggerElement = event.relatedTarget;

                const messageType = triggerElement.dataset.type;
                const senderName = triggerElement.dataset.senderName;
                const recipientName = triggerElement.dataset.recipientName;
                const time = triggerElement.dataset.time;
                const text = triggerElement.dataset.text;
                const contractId = triggerElement.dataset.contractId;
                const customerId = triggerElement.dataset.customerId;

                // Populate modal with message data
                const modalHeader = individualMessageModal.querySelector('#modal-header');
                if (messageType === 'received') {
                    modalHeader.textContent = `From: ${senderName}`;
                } else {
                    modalHeader.textContent = `To: ${recipientName}`;
                }

                individualMessageModal.querySelector('#modal-time').textContent = time;
                individualMessageModal.querySelector('#modal-text').textContent = text;

                // Safely create contract link
                const contractLinkContainer = individualMessageModal.querySelector('#modal-contract-link');
                contractLinkContainer.innerHTML = ''; // Clear previous link
                if (contractId && customerId) {
                    const link = document.createElement('a');
                    link.href = `customer_detail.php?id=${customerId}`;
                    link.target = '_blank';
                    link.textContent = `관련 계약 보기 (계약번호: ${contractId})`;
                    contractLinkContainer.appendChild(document.createElement('br'));
                    contractLinkContainer.appendChild(link);
                }

                // Show/hide reply button based on message type
                const replyBtn = individualMessageModal.querySelector('#reply-btn');
                const sendReplyBtn = individualMessageModal.querySelector('#send-reply-btn');
                const deleteBtn = individualMessageModal.querySelector('#delete-btn');
                if (triggerElement.dataset.type === 'received') {
                    replyBtn.style.display = 'inline-block';
                    // Store recipient ID (original sender) and contract ID for the reply
                    sendReplyBtn.dataset.recipientId = triggerElement.dataset.senderId;
                    sendReplyBtn.dataset.contractId = triggerElement.dataset.contractId;
                } else {
                    replyBtn.style.display = 'none';
                }

                // Set message ID for the delete button
                deleteBtn.dataset.messageId = triggerElement.dataset.messageId;
                deleteBtn.dataset.elementId = triggerElement.id; // Store the unique ID of the element
                // If the message is unread, mark it as read
                const isUnread = triggerElement.dataset.isUnread === '1';
                const messageId = triggerElement.dataset.messageId;

                if (triggerElement.dataset.type === 'received' && isUnread && messageId) {
                    fetch(`../process/intranet_process.php?action=mark_as_read&message_id=${messageId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Visually update the item to 'read'
                                triggerElement.classList.remove('unread');
                                triggerElement.dataset.isUnread = '0';
                                // Update the unread filter if it's active
                                document.getElementById('show-unread-only')?.dispatchEvent(new Event('change'));
                            }
                        });
                }
            });

            // Toggle reply form
            const replyBtn = individualMessageModal.querySelector('#reply-btn');
            replyBtn.addEventListener('click', function() {
                const replyForm = individualMessageModal.querySelector('#reply-form-container');
                replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
                if (replyForm.style.display === 'block') {
                    individualMessageModal.querySelector('#reply-message-text').focus();
                }
            });

            // Send reply
            const sendReplyBtn = individualMessageModal.querySelector('#send-reply-btn');
            sendReplyBtn.addEventListener('click', function() {
                const recipientId = this.dataset.recipientId;
                const contractId = this.dataset.contractId || '';
                const messageText = individualMessageModal.querySelector('#reply-message-text').value;

                if (!messageText.trim()) {
                    alert('답장 내용을 입력하세요.');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('recipient_id', recipientId);
                formData.append('message_text', messageText);
                formData.append('contract_id', contractId);

                fetch('../process/intranet_process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            // 답장 성공 시 모달 닫고 보낸 메시지함 동적 업데이트
                            bootstrap.Modal.getInstance(individualMessageModal).hide();
                            const sentTab = document.getElementById('sent');
                            const sentList = sentTab.querySelector('.message-list');
                            const newSentMessage = `<div class="message-item" style="background-color: #fffbe6;"><div class="message-meta"><strong>To:</strong> ${data.recipient_name} | <strong>Time:</strong> 지금</div><div class="message-text">${escapeHTML(messageText)}</div></div>`;
                            sentList.insertAdjacentHTML('afterbegin', newSentMessage);
                            sentTab.querySelector('p')?.remove(); // '보낸 메시지 없음' 문구 제거
                        }
                    });
            });

            // Delete message
            const deleteBtn = individualMessageModal.querySelector('#delete-btn');
            deleteBtn.addEventListener('click', function() {
                const elementId = this.dataset.elementId;
                const messageId = this.dataset.messageId;

                if (!messageId) {
                    alert('삭제할 메시지를 찾을 수 없습니다.');
                    return;
                }

                if (!confirm('정말 이 메시지를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'delete_message');
                formData.append('message_id', messageId);

                fetch('../process/intranet_process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            bootstrap.Modal.getInstance(individualMessageModal).hide();
                            // Remove the message element from the DOM using its unique ID
                            const messageElement = document.getElementById(elementId);
                            if (messageElement) messageElement.remove();
                        }
                    });
            });
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


<!-- Notice View Modal -->
<div class="modal fade" id="notice-view-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title-text" id="notice-view-title">제목</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; font-size: 24px; font-weight: bold;">&times;</button>
            </div>
            <div class="modal-body">
                <div class="notice-info">
                    <span id="notice-view-author">작성자</span> |
                    <span id="notice-view-date">2025-01-01</span> |
                    조회 <span id="notice-view-count">0</span>
                </div>
                <div class="notice-content" id="notice-view-content"></div>
                <div id="notice-view-file-container" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; display: none;">
                    <strong>📎 첨부파일:</strong> <a href="#" id="notice-view-file-link" download target="_blank">filename.ext</a>
                </div>
            </div>
            <div class="modal-footer">
                <div id="notice-admin-buttons" style="display:none; margin-right: auto;">
                    <button type="button" class="btn btn-warning btn-sm" onclick="editNotice()">수정</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteNotice()">삭제</button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-top: 20px; align-self: center;">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- Notice Write/Edit Modal -->
<div class="modal fade" id="notice-write-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notice-write-title">공지사항 작성</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; font-size: 24px; font-weight: bold;">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="notice-id">
                <div class="mb-3">
                    <label for="notice-title" class="form-label">제목</label>
                    <input type="text" class="form-control" id="notice-title" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="notice-important">
                    <label class="form-check-label" for="notice-important">중요 공지 (상단 고정)</label>
                </div>
                <div class="mb-3">
                    <label for="notice-content" class="form-label">내용</label>
                    <textarea class="form-control" id="notice-content" rows="10" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="notice-attachment" class="form-label">첨부파일</label>
                    <input type="file" class="form-control" id="notice-attachment">
                    <div id="notice-current-file" style="margin-top: 5px; font-size: 0.9em; display: none;">
                        현재 파일: <span id="current-file-name"></span>
                        <div class="form-check" style="display: inline-block; margin-left: 10px;">
                            <input class="form-check-input" type="checkbox" id="delete-file-check">
                            <label class="form-check-label" for="delete-file-check" style="color: #dc3545;">파일 삭제</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveNotice()">저장</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Notice Board Logic
    let currentNoticeId = 0;

    document.addEventListener('DOMContentLoaded', function() {
        loadNotices();
    });

    function loadNotices() {
        console.log('Loading notices...');
        fetch('../process/notice_process.php?action=get_notices&limit=5')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.text(); // Use text() to handle potential non-JSON errors
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        renderNoticeList(data.notices);
                    } else {
                        console.error('Notice load error:', data.message);
                        const list = document.getElementById('notice-list-container');
                        list.innerHTML = `<li style="text-align: center; padding: 20px; color: #dc3545;">❌ 오류: ${data.message}</li>`;
                    }
                } catch (e) {
                    console.error('JSON Parse error:', e, text);
                    const list = document.getElementById('notice-list-container');
                    list.innerHTML = '<li style="text-align: center; padding: 20px; color: #dc3545;">⚠️ 데이터 처리 오류 (서버 응답 확인 필요)</li>';
                }
            })
            .catch(err => {
                console.error('Error loading notices:', err);
                const list = document.getElementById('notice-list-container');
                list.innerHTML = '<li style="text-align: center; padding: 20px; color: #dc3545;">⚠️ 서버 통신 오류</li>';
            });
    }

    function renderNoticeList(notices) {
        const list = document.getElementById('notice-list-container');
        list.innerHTML = '';

        if (notices.length === 0) {
            list.innerHTML = '<li style="text-align: center; padding: 20px; color: #777;">등록된 공지사항이 없습니다.</li>';
            return;
        }

        notices.forEach(notice => {
            const isImportant = notice.is_important == 1;
            const className = isImportant ? 'notice-item important' : 'notice-item';

            // 날짜 포맷 (YYYY-MM-DD)
            const date = notice.created_at.substring(0, 10);

            // 파일 아이콘
            const fileIcon = notice.file_path ? '<i class="fas fa-paperclip" style="color:#888; margin-right:5px;" title="첨부파일"></i>' : '';

            const html = `
            <li class="${className}" onclick="openNoticeView(${notice.id})">
                <div class="notice-title">
                    ${fileIcon}
                    ${escapeHTML_notice(notice.title)}
                </div>
                <div class="notice-meta">
                    <span>${notice.author_name}</span>
                    <span>${date}</span>
                </div>
            </li>
        `;
            list.insertAdjacentHTML('beforeend', html);
        });
    }

    function openNoticeView(id) {
        currentNoticeId = id;
        fetch(`../process/notice_process.php?action=get_notice&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const notice = data.notice;

                    document.getElementById('notice-view-title').textContent = notice.title;
                    document.getElementById('notice-view-author').textContent = notice.author_name;
                    document.getElementById('notice-view-date').textContent = notice.created_at;
                    document.getElementById('notice-view-count').textContent = notice.view_count;
                    document.getElementById('notice-view-content').textContent = notice.content;

                    // 파일 표시 로직
                    const fileContainer = document.getElementById('notice-view-file-container');
                    const fileLink = document.getElementById('notice-view-file-link');

                    if (notice.file_path && notice.file_name) {
                        let filePath = notice.file_path;
                        // Fix for legacy paths or mismatched server config
                        if (filePath.startsWith('/payday/')) {
                            filePath = filePath.replace('/payday', '');
                        }

                        fileLink.href = '..' + filePath; // 상대 경로 조정
                        fileLink.textContent = notice.file_name;
                        fileLink.download = notice.file_name;
                        fileContainer.style.display = 'block';
                    } else {
                        fileContainer.style.display = 'none';
                    }

                    // 관리 버튼 표시 여부
                    const adminBtns = document.getElementById('notice-admin-buttons');
                    adminBtns.style.display = notice.can_edit ? 'block' : 'none';

                    const modal = new bootstrap.Modal(document.getElementById('notice-view-modal'));
                    modal.show();
                } else {
                    alert('공지사항을 불러올 수 없습니다.');
                }
            });
    }

    function openNoticeWriteModal() {
        // Reset form
        document.getElementById('notice-id').value = '';
        document.getElementById('notice-title').value = '';
        document.getElementById('notice-content').value = '';
        document.getElementById('notice-important').checked = false;
        document.getElementById('notice-attachment').value = ''; // 파일 리셋
        document.getElementById('notice-current-file').style.display = 'none'; // 기존 파일 정보 숨김
        document.getElementById('delete-file-check').checked = false;

        document.getElementById('notice-write-title').textContent = '공지사항 작성';

        const modal = new bootstrap.Modal(document.getElementById('notice-write-modal'));
        modal.show();
    }

    function editNotice() {
        // Hide view modal
        const viewModalEl = document.getElementById('notice-view-modal');
        const viewModal = bootstrap.Modal.getInstance(viewModalEl);
        viewModal.hide();

        // Fetch data again (or use displayed data, but fetch is safer for content full version)
        fetch(`../process/notice_process.php?action=get_notice&id=${currentNoticeId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const notice = data.notice;
                    document.getElementById('notice-id').value = notice.id;
                    document.getElementById('notice-title').value = notice.title;
                    document.getElementById('notice-content').value = notice.content;
                    document.getElementById('notice-important').checked = (notice.is_important == 1);
                    document.getElementById('notice-attachment').value = ''; // 새 파일 업로드 초기화
                    document.getElementById('delete-file-check').checked = false;

                    // 기존 파일 있으면 표시
                    const currentFileDiv = document.getElementById('notice-current-file');
                    const currentFileNameSpan = document.getElementById('current-file-name');

                    if (notice.file_name) {
                        currentFileNameSpan.textContent = notice.file_name;
                        currentFileDiv.style.display = 'block';
                    } else {
                        currentFileDiv.style.display = 'none';
                    }

                    document.getElementById('notice-write-title').textContent = '공지사항 수정';

                    const writeModal = new bootstrap.Modal(document.getElementById('notice-write-modal'));
                    writeModal.show();
                }
            });
    }

    function saveNotice() {
        const id = document.getElementById('notice-id').value;
        const title = document.getElementById('notice-title').value;
        const content = document.getElementById('notice-content').value;
        const isImportant = document.getElementById('notice-important').checked ? 1 : 0;
        const fileInput = document.getElementById('notice-attachment');
        const deleteFile = document.getElementById('delete-file-check').checked ? 1 : 0;

        if (!title || !content) {
            alert('제목과 내용을 입력해주세요.');
            return;
        }

        const action = id ? 'update_notice' : 'create_notice';
        const formData = new FormData();
        formData.append('action', action);
        if (id) formData.append('id', id);
        formData.append('title', title);
        formData.append('content', content);
        formData.append('is_important', isImportant);

        if (fileInput.files.length > 0) {
            formData.append('attachment', fileInput.files[0]);
        }

        if (deleteFile) {
            formData.append('delete_file', '1');
        }

        fetch('../process/notice_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Close modal
                    const modalEl = document.getElementById('notice-write-modal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();

                    // Reload list
                    loadNotices();
                } else {
                    alert('오류: ' + data.message);
                }
            });
    }

    function deleteNotice() {
        if (!confirm('정말 삭제하시겠습니까?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_notice');
        formData.append('id', currentNoticeId);

        fetch('../process/notice_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Hide view modal
                    const viewModalEl = document.getElementById('notice-view-modal');
                    const viewModal = bootstrap.Modal.getInstance(viewModalEl);
                    viewModal.hide();

                    // Reload list
                    loadNotices();
                } else {
                    alert('오류: ' + data.message);
                }
            });
    }

    // Renamed to avoid partial conflict if needed, though scoped previously
    function escapeHTML_notice(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [match];
        });
    }
</script>

<?php include 'footer.php'; ?>