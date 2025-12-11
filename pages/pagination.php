<?php
/**
 * 페이지네이션 HTML을 렌더링하는 함수
 *
 * @param int $total_pages 전체 페이지 수
 * @param int $current_page 현재 페이지 번호
 * @param int $range 현재 페이지 주변에 표시할 페이지 번호의 수
 */
function renderPagination($total_pages, $current_page, $range = 5) {
    if ($total_pages <= 1) {
        return;
    }

    $query_params = $_GET;
    unset($query_params['page']);
    $base_url = http_build_query($query_params);
    if (!empty($base_url)) {
        $base_url .= '&';
    }

    echo '<nav aria-label="Page navigation"><ul class="pagination">';

    // 이전 페이지 링크
    $prev_page = max(1, $current_page - 1);
    echo '<li class="page-item ' . ($current_page == 1 ? 'disabled' : '') . '"><a class="page-link" href="?' . $base_url . 'page=' . $prev_page . '">&laquo;</a></li>';

    // 페이지 번호 링크
    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
        echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?' . $base_url . 'page=' . $i . '">' . $i . '</a></li>';
    }

    // 다음 페이지 링크
    $next_page = min($total_pages, $current_page + 1);
    echo '<li class="page-item ' . ($current_page == $total_pages ? 'disabled' : '') . '"><a class="page-link" href="?' . $base_url . 'page=' . $next_page . '">&raquo;</a></li>';

    echo '</ul></nav>';
}
?>