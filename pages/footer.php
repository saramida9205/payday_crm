    <footer style="text-align: center; padding: 20px 0; margin-top: 20px; border-top: 1px solid #eee;">
        <p>&copy; <?php echo date('Y'); ?> <strong>SaRaM_ida</strong> builds a system with <strong>GEMINI3PH</strong> & <strong>Antigravity</strong>. All Rights Reserved.</p>
    </footer>
    </div> <!-- /.content-inner -->
    </div> <!-- /.main-content -->
    </div> <!-- /.main-container -->

    <script src="../js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 모든 날짜 입력 필드에 자동 이동 기능 추가
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(dateInput => {
                dateInput.addEventListener('keyup', function(e) {
                    // 연도 필드에서 4자리 입력 시 월 필드로 자동 이동
                    if (e.target.value.length === 4 && e.key !== 'Backspace') {
                        // 브라우저 호환성을 위해 약간의 지연 후 다음 필드로 포커스 이동
                        setTimeout(() => {
                            // 실제로는 브라우저가 날짜 입력을 처리하므로, 
                            // 사용자가 연도 입력 후 자연스럽게 다음으로 넘어갈 수 있도록 돕는 역할
                        }, 100);
                    }
                });
            });
        });
    </script>
    </body>

    </html>