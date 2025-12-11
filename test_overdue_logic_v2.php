<?php
require_once 'common.php';

// Mock getHolidayExceptions
function mock_getHolidayExceptions() {
    return ['holidays' => ['2025-12-25'], 'workdays' => []];
}

echo "Testing Overdue Logic (v2)...\n";
echo "File MTime of common.php: " . date("Y-m-d H:i:s", filemtime('common.php')) . "\n\n";

// Scenario:
// Agreement Day: 25
// Previous Due Date: 2025-11-25
// Payment Date: 2025-12-01
// Shortfall: 50000 (Overdue)

$base_date = new DateTime('2025-12-01');
$agreement_day = 25;
$exceptions = ['holidays' => ['2025-12-25'], 'workdays' => []];
$shortfall = 50000;
$previous_due_date = new DateTime('2025-11-25');

// Current Call (with previous due date)
$next_due = get_next_due_date($base_date, $agreement_day, $exceptions, $shortfall, $previous_due_date);

echo "Scenario: Overdue 11-25, Pay 12-01, Shortfall > 0\n";
echo "Payment Date: " . $base_date->format('Y-m-d') . "\n";
echo "Shortfall: " . $shortfall . "\n";
echo "Result: " . $next_due->format('Y-m-d') . "\n";
echo "Expected: 2025-11-25 (Should NOT advance)\n";

if ($next_due->format('Y-m-d') === '2025-11-25') {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// Additional Test: Normal Payment (Shortfall < 10000)
$shortfall_normal = 0;
$next_due_normal = get_next_due_date($base_date, $agreement_day, $exceptions, $shortfall_normal, $previous_due_date);
echo "\nScenario: Normal Payment (Shortfall 0)\n";
echo "Result: " . $next_due_normal->format('Y-m-d') . "\n";
echo "Expected: 2025-12-26 (Should advance)\n";
if ($next_due_normal->format('Y-m-d') === '2025-12-26') {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}
