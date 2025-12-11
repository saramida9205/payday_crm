<?php
require_once 'common.php';

// Mock getHolidayExceptions since we don't want to hit the DB
function mock_getHolidayExceptions() {
    return ['holidays' => ['2026-01-01'], 'workdays' => []];
}

echo "File MTime of common.php: " . date("Y-m-d H:i:s", filemtime('common.php')) . "\n";
echo "Current Time: " . date("Y-m-d H:i:s") . "\n\n";

echo "Testing Current Logic (from common.php)...\n";

// Case 1: User's Example
$base_date = new DateTime('2025-11-28');
$agreement_day = 1;
$exceptions = ['holidays' => ['2026-01-01'], 'workdays' => []];
$shortfall = 0;

$next_due = get_next_due_date($base_date, $agreement_day, $exceptions, $shortfall);
echo "Case 1 (Early Payment):\n";
echo "Payment Date: " . $base_date->format('Y-m-d') . "\n";
echo "Result: " . $next_due->format('Y-m-d') . "\n";
echo "Expected: 2026-01-02\n";
echo ($next_due->format('Y-m-d') === '2026-01-02' ? "PASS" : "FAIL") . "\n\n";

// --- Isolated Test ---
function get_next_due_date_v2($base_date, $agreement_day, $exceptions, $shortfall = 0) {
    $next_due = clone $base_date;

    // 1. Check for "Early Payment" for the NEXT month first.
    // Calculate the agreement date for the NEXT month relative to the base date
    $next_month_date = clone $base_date;
    $next_month_date->modify('first day of next month');
    $next_month_agreement_date = clone $next_month_date;
    $next_month_agreement_date->setDate(
        (int)$next_month_date->format('Y'),
        (int)$next_month_date->format('m'),
        min($agreement_day, (int)$next_month_date->format('t'))
    );

    $next_month_grace_start = clone $next_month_agreement_date;
    $next_month_grace_start->modify('-10 days');

    // If payment is within the window for the NEXT month, jump to the month AFTER next.
    if ($shortfall < 10000 && $base_date >= $next_month_grace_start) {
        $next_due->modify('first day of next month'); // Move to next month
        $next_due->modify('first day of next month'); // Move to month after next
    } else {
        // 2. Standard Logic (Current Month)
        $current_month_agreement_date = clone $next_due;
        $current_month_agreement_date->setDate(
            (int)$next_due->format('Y'),
            (int)$next_due->format('m'),
            min($agreement_day, (int)$next_due->format('t'))
        );

        $grace_period_start = clone $current_month_agreement_date;
        $grace_period_start->modify('-10 days');

        if ($shortfall < 10000) {
            if ($base_date >= $grace_period_start) {
                $next_due->modify('first day of next month');
            }
        }
    }

    $year = (int)$next_due->format('Y');
    $month = (int)$next_due->format('m');
    $day_to_set = min($agreement_day, (int)date('t', mktime(0, 0, 0, $month, 1, $year)));
    $next_due->setDate($year, $month, $day_to_set);

    // Skip holidays (using isHoliday logic)
    // We use the global isHoliday from common.php, assuming it hasn't changed or is simple enough
    while (isHoliday($next_due->format('Y-m-d'), $exceptions)) {
        $next_due->modify('+1 day');
    }
    return $next_due;
}

echo "Testing Isolated Logic (v2)...\n";
$next_due_v2 = get_next_due_date_v2($base_date, $agreement_day, $exceptions, $shortfall);
echo "Case 1 (Early Payment) v2:\n";
echo "Result: " . $next_due_v2->format('Y-m-d') . "\n";
echo ($next_due_v2->format('Y-m-d') === '2026-01-02' ? "PASS" : "FAIL") . "\n";

