<?php
require_once 'common.php';

// Mock getHolidayExceptions
function mock_getHolidayExceptions() {
    return ['holidays' => ['2025-12-25'], 'workdays' => []];
}

echo "Testing Overdue Logic...\n";

// Scenario:
// Agreement Day: 25
// Previous Due Date: 2025-11-25
// Payment Date: 2025-12-01
// Shortfall: 50000 (Overdue)

$base_date = new DateTime('2025-12-01');
$agreement_day = 25;
$exceptions = ['holidays' => ['2025-12-25'], 'workdays' => []];
$shortfall = 50000;

// Current Call (with previous due date)
$previous_due_date = new DateTime('2025-11-25');
$next_due = get_next_due_date($base_date, $agreement_day, $exceptions, $shortfall, $previous_due_date);

echo "Scenario: Overdue 11-25, Pay 12-01, Shortfall > 0\n";
echo "Payment Date: " . $base_date->format('Y-m-d') . "\n";
echo "Shortfall: " . $shortfall . "\n";
echo "Result: " . $next_due->format('Y-m-d') . "\n";
echo "Expected: 2025-11-25 (Should NOT advance)\n";

if ($next_due->format('Y-m-d') === '2025-11-25') {
    echo "PASS\n";
} else {
    echo "FAIL (Bug Reproduced)\n";
}

echo "\n---------------------------------------------------\n";

// Proposed Logic Test (Simulation)
$previous_due_date = new DateTime('2025-11-25');

function get_next_due_date_proposed($base_date, $agreement_day, $exceptions, $shortfall, $previous_due_date = null) {
    $next_due = clone $base_date;

    // ... (Existing Logic to calculate candidate) ...
    // 1. Check for "Early Payment" for the NEXT month first.
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

    if ($shortfall < 10000 && $base_date >= $next_month_grace_start) {
        $next_due->modify('first day of next month'); 
        $next_due->modify('first day of next month'); 
    } else {
        // 2. Standard Logic
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

    while (isHoliday($next_due->format('Y-m-d'), $exceptions)) {
        $next_due->modify('+1 day');
    }
    
    // --- NEW LOGIC ---
    if ($shortfall >= 10000 && $previous_due_date !== null) {
        if ($next_due > $previous_due_date) {
             return $previous_due_date;
        }
    }

    return $next_due;
}

$next_due_proposed = get_next_due_date_proposed($base_date, $agreement_day, $exceptions, $shortfall, $previous_due_date);
echo "Proposed Logic Result: " . $next_due_proposed->format('Y-m-d') . "\n";
echo ($next_due_proposed->format('Y-m-d') === '2025-11-25' ? "PASS" : "FAIL") . "\n";
