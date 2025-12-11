<?php
include('header.php');

// Fetch deleted collections
$deleted_collections = getCollections($link, '', '', '', '', true);

// Group collections by transaction_id for display
$grouped_collections = [];
foreach ($deleted_collections as $col) {
    $is_grouped = !empty($col['transaction_id']);
    $key = $is_grouped ? $col['transaction_id'] : 'manual_' . $col['id'];
    if (!isset($grouped_collections[$key])) {
        $grouped_collections[$key] = array_merge($col, [
            'interest' => 0,
            'principal' => 0,
            'expense' => 0,
            'ids' => []
        ]);
    }
    if ($col['collection_type'] == '이자') $grouped_collections[$key]['interest'] += $col['amount'];
    elseif ($col['collection_type'] == '원금') $grouped_collections[$key]['principal'] += $col['amount'];
    elseif ($col['collection_type'] == '경비') $grouped_collections[$key]['expense'] += $col['amount'];
    $grouped_collections[$key]['ids'][] = $col['id'];
}

?>

<h2>회수 내역 휴지통</h2>
<p>삭제된 회수 내역을 확인하고 복원할 수 있습니다.</p>

<div style="margin-bottom: 10px;">
    <button type="button" id="restore_selected_collections" class="btn btn-success">선택 항목 복원</button>
    <button type="button" id="delete_permanently_selected" class="btn btn-danger">선택 항목 영구 삭제</button>
    <a href="collection_manage.php" class="btn btn-secondary">회수 관리로 돌아가기</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select_all_collections"></th>
                <th>계약번호</th>
                <th>입금일자</th>
                <th>고객명</th>
                <th>이자</th>
                <th>원금</th>
                <th>경비</th>
                <th>입금합계</th>
                <th>삭제일</th>
                <th>삭제자</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($grouped_collections)): ?>
            <tr><td colspan="11" style="text-align: center;">삭제된 내역이 없습니다.</td></tr>
        <?php else: ?>
            <?php foreach ($grouped_collections as $key => $row): ?>
            <tr>
                <td><input type="checkbox" class="collection_checkbox" value="<?php echo implode(',', $row['ids']); ?>"></td>
                <td><?php echo htmlspecialchars($row['contract_id']); ?></td>
                <td><?php echo htmlspecialchars($row['collection_date']); ?></td>
                <td><a href="customer_detail.php?id=<?php echo $row['customer_id']; ?>"><?php echo htmlspecialchars($row['customer_name']); ?></a></td>
                <td style="text-align: right;"><?php echo number_format($row['interest']); ?></td>
                <td style="text-align: right;"><?php echo number_format($row['principal']); ?></td>
                <td style="text-align: right;"><?php echo number_format($row['expense']); ?></td>
                <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['interest'] + $row['principal'] + $row['expense']); ?></td>
                <td><?php echo htmlspecialchars($row['deleted_at']); ?></td>
                <td><?php echo htmlspecialchars($row['deleted_by']); ?></td>
                <td>
                    <button class="btn btn-sm btn-success restore_single_collection" data-ids="<?php echo implode(',', $row['ids']); ?>">복원</button>
                    <button class="btn btn-sm btn-danger delete_permanently_single" data-ids="<?php echo implode(',', $row['ids']); ?>">영구삭제</button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Checkbox Logic ---
    const selectAllCheckbox = document.getElementById('select_all_collections');
    const collectionCheckboxes = document.querySelectorAll('.collection_checkbox');

    selectAllCheckbox.addEventListener('change', function() {
        collectionCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
    });

    // --- Restore Logic ---
    function restoreCollections(ids) {
        if (!ids || ids.length === 0) {
            alert('복원할 항목을 선택해주세요.');
            return;
        }

        if (confirm('선택된 항목을 복원하시겠습니까?')) {
            fetch('../process/collection_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=restore_bulk&ids=' + ids
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('복원 실패: ' + data.message);
                }
            })
            .catch(error => alert('오류 발생: ' + error));
        }
    }

    function deletePermanently(ids) {
        if (!ids || ids.length === 0) {
            alert('영구 삭제할 항목을 선택해주세요.');
            return;
        }

        if (confirm('선택된 항목을 영구적으로 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
            fetch('../process/collection_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_permanently&ids=' + ids
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('영구 삭제 실패: ' + data.message);
                }
            })
            .catch(error => alert('오류 발생: ' + error));
        }
    }


    // Single restore
    document.querySelectorAll('.restore_single_collection').forEach(button => {
        button.addEventListener('click', function() {
            restoreCollections(this.dataset.ids);
        });
    });

    // Bulk restore
    document.getElementById('restore_selected_collections').addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.collection_checkbox:checked'))
                               .map(checkbox => checkbox.value)
                               .join(',');
        restoreCollections(selectedIds);
    });

    // Single permanent delete
    document.querySelectorAll('.delete_permanently_single').forEach(button => {
        button.addEventListener('click', function() {
            deletePermanently(this.dataset.ids);
        });
    });

    // Bulk permanent delete
    document.getElementById('delete_permanently_selected').addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.collection_checkbox:checked'))
                               .map(checkbox => checkbox.value)
                               .join(',');
        deletePermanently(selectedIds);
    });
});
</script>

<?php include 'footer.php'; ?>