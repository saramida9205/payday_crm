<?php
include 'header.php';
require_once '../common.php';

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    echo "<h2>권한 없음</h2><p>이 페이지에 접근할 권한이 없습니다.</p>";
    include 'footer.php';
    exit;
}

global $link;

// 기존 백업 DB 목록 가져오기
$backup_dbs = [];
$source_db_name = DB_NAME;
$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE ?";
$stmt = mysqli_prepare($link, $sql);
$search_pattern = $source_db_name . '_%';
mysqli_stmt_bind_param($stmt, "s", $search_pattern);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $db_name = $row['SCHEMA_NAME'];
    $backup_type = '일반 백업';
    $date_part_str = str_replace($source_db_name . '_', '', $db_name);

    if (strpos($date_part_str, 'before_restore_') === 0) {
        $backup_type = '복원 전 안전 백업';
        $date_part_str = str_replace('before_restore_', '', $date_part_str);
    }
    
    $create_time = null;
    if (preg_match('/^(\d{8})_(\d{6})$/', $date_part_str, $matches)) {
        // YYYYMMDD_HHMMSS format
        $create_time = DateTime::createFromFormat('Ymd_His', $matches[0]);
    } elseif (preg_match('/^(\d{8})$/', $date_part_str, $matches)) {
        // YYYYMMDD format
        $create_time = DateTime::createFromFormat('Ymd', $matches[0]);
        if ($create_time) $create_time->setTime(0, 0, 0);
    }

    $backup_dbs[] = [
        'TYPE' => $backup_type,
        'SCHEMA_NAME' => $db_name,
        'CREATE_TIME' => $create_time ? $create_time->format('Y-m-d H:i:s') : 'N/A'
    ];
}
mysqli_stmt_close($stmt);
usort($backup_dbs, fn($a, $b) => strcmp($b['CREATE_TIME'], $a['CREATE_TIME']));
?>

<style>
    .badge { display: inline-block; padding: .25em .6em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
    .badge-primary { color: #fff; background-color: #007bff; }
    .badge-secondary { color: #fff; background-color: #6c757d; }
    .badge-success { color: #fff; background-color: #28a745; }
    .badge-danger { color: #fff; background-color: #dc3545; }
    .badge-warning { color: #212529; background-color: #ffc107; }
</style>

<h2>데이터베이스 백업 관리</h2>

<div class="form-container" style="margin-bottom: 30px;">
    <h3>신규 백업 생성</h3>
    <p>아래 버튼을 클릭하여 현재 데이터베이스의 전체 백업을 생성합니다. 백업은 `payday_db_YYYYMMDD` 형식의 이름으로 생성됩니다.</p>
    <button id="start-backup-btn" class="btn btn-primary">전체 DB 백업 시작</button>
    <div id="backup-progress-container" style="margin-top: 15px; display: none;">
        <div class="progress-bar-wrapper" style="background-color: #e9ecef; border-radius: .25rem; overflow: hidden;">
            <div id="progress-bar" style="width: 0%; height: 20px; background-color: #007bff; transition: width 0.4s ease;"></div>
        </div>
    </div>
    <div id="backup-progress-text" style="margin-top: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9; display: none;">
        <p id="backup-status">백업을 준비 중입니다...</p>
    </div>
</div>

<hr style="margin: 30px 0;">

<div class="form-container" style="margin-bottom: 30px;">
    <h3>SQL 파일로 복원</h3>
    <p>다운로드한 <code>.sql</code> 백업 파일을 업로드하여 새로운 데이터베이스로 복원합니다.</p>
    <form id="restore-from-sql-form" enctype="multipart/form-data">
        <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 15px; align-items: center;">
            <label for="new_db_name">새 DB 이름</label>
            <input type="text" id="new_db_name" name="new_db_name" placeholder="예: payday_db_20240101_restored" required>
            <label for="sql_file">SQL 파일</label>
            <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
        </div>
        <button type="submit" id="start-sql-restore-btn" class="btn btn-warning" style="margin-top: 15px;">SQL 파일 복원 시작</button>
    </form>
</div>

<div class="table-container">
    <h3>기존 백업 목록 (총 <?php echo count($backup_dbs); ?>개)</h3>
    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
        <button type="button" id="delete-old-backups-btn" class="btn btn-danger" style="margin-bottom: 15px;">30일 이상된 백업 전체 삭제</button>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>백업 데이터베이스 이름</th>
                <th style="width: 150px;">유형</th>
                <th style="width: 200px;">백업 시간</th>
                <th style="width: 240px; text-align: center;">작업</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($backup_dbs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">생성된 백업이 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php 
                $today = new DateTime();
                foreach ($backup_dbs as $db_name): 
                    $is_old = false;
                    if ($db_name['CREATE_TIME'] !== 'N/A') {
                        $create_date = new DateTime($db_name['CREATE_TIME']);
                        if ($today->diff($create_date)->days >= 30) {
                            $is_old = true;
                        }
                    }
                ?>
                <tr <?php if ($is_old) echo 'style="background-color: #f8f9fa;" data-is-old="true"'; ?> data-dbname="<?php echo htmlspecialchars($db_name['SCHEMA_NAME']); ?>">
                    <td><?php echo htmlspecialchars($db_name['SCHEMA_NAME']); ?></td>
                    <td>
                        <?php if ($db_name['TYPE'] === '복원 전 안전 백업'): ?>
                            <span class="badge badge-warning"><?php echo $db_name['TYPE']; ?></span>
                        <?php else: ?>
                            <span class="badge badge-primary"><?php echo $db_name['TYPE']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($db_name['CREATE_TIME']); ?></td>
                    <td style="display: flex; gap: 5px; justify-content: center;">
                        <button class="btn btn-warning btn-sm restore-backup-btn" data-dbname="<?php echo htmlspecialchars($db_name['SCHEMA_NAME']); ?>">복원</button>
                        <a href="../process/download_backup.php?db_name=<?php echo urlencode($db_name['SCHEMA_NAME']); ?>" class="btn btn-success btn-sm" target="_blank">다운로드</a>
                        <button class="btn btn-danger btn-sm delete-backup-btn" data-dbname="<?php echo htmlspecialchars($db_name['SCHEMA_NAME']); ?>">삭제</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const startBtn = document.getElementById('start-backup-btn');
    const progressContainer = document.getElementById('backup-progress-container');
    const progressTextDiv = document.getElementById('backup-progress-text');
    const statusP = document.getElementById('backup-status');
    const progressBar = document.getElementById('progress-bar');

    // --- Backup Start ---
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            if (!confirm('전체 데이터베이스 백업을 시작하시겠습니까? 이 작업은 시간이 걸릴 수 있으며, 작업 중에는 페이지를 이동하지 마세요.')) {
                return;
            }

            this.disabled = true;
            this.textContent = '백업 진행 중...';
            progressContainer.style.display = 'block';
            progressTextDiv.style.display = 'block';
            statusP.textContent = '백업을 시작합니다. 잠시만 기다려주세요...';
            statusP.style.color = 'blue';
            progressBar.style.backgroundColor = '#007bff';
            progressBar.style.width = '0%';

            // Start the backup process
            fetch('../process/backup_db.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('서버 응답 오류: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    statusP.textContent = '성공: ' + data.message;
                    progressBar.style.width = '100%';
                    statusP.style.color = 'green';
                    // 잠시 후 페이지 새로고침하여 목록 갱신
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    statusP.textContent = '실패: ' + data.message;
                    progressBar.style.backgroundColor = '#dc3545';
                    statusP.style.color = 'red';
                    startBtn.disabled = false;
                    startBtn.textContent = '전체 DB 백업 시작';
                }
            })
            .catch(error => {
                statusP.textContent = '치명적인 오류 발생: ' + error.message;
                progressBar.style.backgroundColor = '#dc3545';
                statusP.style.color = 'red';
                startBtn.disabled = false;
                startBtn.textContent = '전체 DB 백업 시작';
            });

            // Start polling for progress
            const progressInterval = setInterval(() => {
                fetch('../process/backup_manage_process.php?action=get_backup_progress')
                    .then(response => response.json())
                    .then(progressData => {
                        if (progressData.in_progress) {
                            const percent = progressData.progress || 0;
                            progressBar.style.width = percent + '%';
                            statusP.textContent = progressData.message || '백업 진행 중...';
                        } else {
                            // If process is finished (or not started), stop polling
                            clearInterval(progressInterval);
                        }
                    })
                    .catch(err => {
                        console.error('Progress polling error:', err);
                        clearInterval(progressInterval);
                    });
            }, 1000); // Poll every second
        });
    }

    // --- Delete Backup ---
    document.querySelectorAll('.delete-backup-btn').forEach(button => {
        button.addEventListener('click', function() {
            const dbName = this.dataset.dbname;
            if (!confirm(`'${dbName}' 백업을 정말로 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }

            // Disable all buttons during operation
            startBtn.disabled = true;
            document.querySelectorAll('.delete-backup-btn').forEach(btn => btn.disabled = true);
            this.textContent = '삭제 중...';

            // Show progress bar
            progressContainer.style.display = 'block';
            progressTextDiv.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.style.backgroundColor = '#dc3545'; // Use red for deletion
            statusP.textContent = `'${dbName}' 백업 삭제를 시작합니다...`;
            statusP.style.color = 'blue';

            setTimeout(() => { progressBar.style.width = '50%'; }, 200); // Visual feedback

            const formData = new FormData();
            formData.append('action', 'delete_backup');
            formData.append('db_name', dbName);

            fetch('../process/backup_manage_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                progressBar.style.width = '100%';
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    statusP.textContent = '성공: ' + data.message;
                    statusP.style.color = 'green';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    statusP.textContent = '실패: ' + data.message;
                    statusP.style.color = 'red';
                    startBtn.disabled = false;
                    document.querySelectorAll('.delete-backup-btn').forEach(btn => btn.disabled = false);
                    this.textContent = '삭제';
                }
            })
            .catch(error => {
                statusP.textContent = '치명적인 오류 발생: ' + error.message;
                statusP.style.color = 'red';
            });
        });
    });

    // --- Restore Backup ---
    document.querySelectorAll('.restore-backup-btn').forEach(button => {
        button.addEventListener('click', function() {
            const dbName = this.dataset.dbname;
            const confirmMessage = `'${dbName}' 백업을 운영 DB로 복원하시겠습니까?\n\n[경고] 이 작업은 현재 운영 DB의 모든 데이터를 선택한 백업 시점의 데이터로 덮어씁니다. 현재 운영 DB는 별도로 백업됩니다. 정말 진행하시겠습니까?`;
            if (!confirm(confirmMessage)) {
                return;
            }

            // Disable all buttons
            if(startBtn) startBtn.disabled = true;
            document.querySelectorAll('.delete-backup-btn, .restore-backup-btn').forEach(btn => btn.disabled = true);
            this.textContent = '복원 중...';

            // Show progress bar
            progressContainer.style.display = 'block';
            progressTextDiv.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.style.backgroundColor = '#ffc107'; // Yellow for restore
            statusP.textContent = `'${dbName}' 복원을 시작합니다...`;
            statusP.style.color = 'blue';

            const formData = new FormData();
            formData.append('action', 'restore_backup');
            formData.append('db_name', dbName);

            // Start polling for progress
            const progressInterval = setInterval(() => {
                fetch('../process/backup_manage_process.php?action=get_backup_progress')
                    .then(response => response.json())
                    .then(progressData => {
                        if (progressData.in_progress) {
                            progressBar.style.width = (progressData.progress || 0) + '%';
                            statusP.textContent = progressData.message || '복원 진행 중...';
                        } else {
                            clearInterval(progressInterval);
                        }
                    });
            }, 1000);

            // Start the restore process
            fetch('../process/backup_manage_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval); // Stop polling
                progressBar.style.width = '100%';
                statusP.textContent = (data.success ? '성공: ' : '실패: ') + data.message;
                statusP.style.color = data.success ? 'green' : 'red';
                if (data.success) {
                    setTimeout(() => window.location.reload(), 2000);
                }
            })
            .catch(error => statusP.textContent = '치명적인 오류 발생: ' + error.message);
        });
    });

    // --- Delete Old Backups ---
    const deleteOldBtn = document.getElementById('delete-old-backups-btn');
    if (deleteOldBtn) {
        deleteOldBtn.addEventListener('click', function() {
            const oldBackupRows = document.querySelectorAll('tr[data-is-old="true"]');
            const dbNamesToDelete = Array.from(oldBackupRows).map(row => row.dataset.dbname);

            if (dbNamesToDelete.length === 0) {
                alert('삭제할 30일 이상된 백업이 없습니다.');
                return;
            }

            if (!confirm(`30일 이상된 백업 ${dbNamesToDelete.length}개를 모두 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_old_backups');
            dbNamesToDelete.forEach(name => formData.append('db_names[]', name));

            fetch('../process/backup_manage_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                alert('오류가 발생했습니다: ' + error.message);
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>
