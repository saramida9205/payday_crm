// Common JavaScript functions will go here

document.addEventListener('DOMContentLoaded', function() {
    // 모든 날짜 입력 필드에 자동 포커스 이동 기능 추가
    const dateInputs = document.querySelectorAll('input[type="date"]');

    dateInputs.forEach(function(dateInput) {
        dateInput.addEventListener('keyup', function(e) {
            // 연도 4자리 입력 시 월 입력으로 이동
            if (this.value.length === 4) {
                // 브라우저 호환성을 위해 약간의 지연 후 포커스 이동
                setTimeout(() => {
                    this.focus(); // 포커스를 다시 현재 요소에 맞춘 후
                    // 오른쪽으로 이동하여 월 입력으로 넘어가도록 시도
                    // (실제로는 브라우저의 기본 동작에 따라 다를 수 있음)
                }, 10);
            }
        });
    });
});