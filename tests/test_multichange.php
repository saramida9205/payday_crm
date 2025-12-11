<?php
require_once __DIR__ . '/../common.php';

// Mock data for testing
$contract = [
    'id' => 99999, // Mock ID
    'loan_amount' => 10000000,
    'loan_date' => '2024-01-01',
    'interest_rate' => 10.0, // Initial rate
    'overdue_interest_rate' => 20.0,
    'maturity_date' => '2024-12-31',
    'rate_change_date' => null, // Will be simulated
    'new_interest_rate' => null,
    'new_overdue_rate' => null
];

// Mock condition changes history
// This simulates what would be in the condition_changes table
$mock_history = [
    [
        'change_date' => '2024-02-01',
        'new_interest_rate' => 20.0,
        'new_overdue_rate' => 23.0
    ],
    [
        'change_date' => '2024-03-01',
        'new_interest_rate' => 30.0,
        'new_overdue_rate' => 33.0
    ]
];

// We need to mock the database interaction for get_interest_rate_history
// Since we can't easily mock the DB connection in this script without a real DB,
// we will temporarily define a mock function or modify the test to use a local version of the logic 
// to verify the ALGORITHM first.

function test_interest_calculation_logic($contract, $history, $start_date, $end_date) {
    echo "Testing Period: $start_date to $end_date\n";
    
    // 1. Build Timeline
    $timeline = [];
    $timeline[] = [
        'start_date' => $contract['loan_date'],
        'interest_rate' => $contract['interest_rate'],
        'overdue_rate' => $contract['overdue_interest_rate']
    ];

    foreach ($history as $change) {
        $timeline[] = [
            'start_date' => $change['change_date'],
            'interest_rate' => $change['new_interest_rate'],
            'overdue_rate' => $change['new_overdue_rate']
        ];
    }

    // Sort by date
    usort($timeline, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });

    // 2. Calculate Interest
    $total_interest = 0;
    $calc_start = new DateTime($start_date);
    $calc_end = new DateTime($end_date);
    
    // Iterate day by day for simplicity in this test script (production uses sub-periods)
    $current = clone $calc_start;
    while ($current < $calc_end) {
        $current_date_str = $current->format('Y-m-d');
        
        // Find applicable rate
        $applied_rate = $contract['interest_rate']; // Default
        foreach ($timeline as $period) {
            if ($current_date_str >= $period['start_date']) {
                $applied_rate = $period['interest_rate'];
            }
        }

        $days_in_year = (date('L', strtotime($current_date_str)) == 1) ? 366 : 365;
        $daily_interest = ($contract['loan_amount'] * ($applied_rate / 100)) / $days_in_year;
        $total_interest += $daily_interest;

        // echo "Date: $current_date_str, Rate: $applied_rate%, Interest: $daily_interest\n";

        $current->modify('+1 day');
    }

    return floor($total_interest);
}

// Scenario: Calculate interest for Jan, Feb, Mar separately
$jan_interest = test_interest_calculation_logic($contract, $mock_history, '2024-01-01', '2024-02-01');
$feb_interest = test_interest_calculation_logic($contract, $mock_history, '2024-02-01', '2024-03-01');
$mar_interest = test_interest_calculation_logic($contract, $mock_history, '2024-03-01', '2024-04-01');

echo "\n--- Results ---\n";
echo "Jan (10%): " . number_format($jan_interest) . " (Expected approx " . number_format(10000000 * 0.10 / 366 * 31) . ")\n";
echo "Feb (20%): " . number_format($feb_interest) . " (Expected approx " . number_format(10000000 * 0.20 / 366 * 29) . ")\n";
echo "Mar (30%): " . number_format($mar_interest) . " (Expected approx " . number_format(10000000 * 0.30 / 366 * 31) . ")\n";

?>
