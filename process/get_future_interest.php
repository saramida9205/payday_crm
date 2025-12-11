<?php
// process/get_future_interest.php
require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/contract_process.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['contract_ids']) || !is_array($input['contract_ids']) || !isset($input['future_date'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$contract_ids = $input['contract_ids'];
$future_date = $input['future_date'];

if (empty($future_date)) {
    echo json_encode(['success' => false, 'message' => 'Future date is required']);
    exit;
}

$results = [];

foreach ($contract_ids as $contract_id) {
    $contract = getContractById($link, $contract_id);
    if (!$contract) {
        continue;
    }

    // 1. Calculate Interest for Future Date
    // Use calculateAccruedInterest which internally handles the logic up to a target date
    // logic mirrored from expected_interest_view.php

    // We need outstanding principal and existing shortfall
    // calculateAccruedInterest fetches these internally if not provided in contract array, 
    // but getContractById should have them.

    // Note: calculateAccruedInterest uses $contract['next_due_date'] as current_due_date.

    $interest_data = calculateAccruedInterest($link, $contract, $future_date);
    $calculated_interest = $interest_data['total'];

    // 2. Unpaid Expenses
    $stmt_expenses = mysqli_prepare($link, "SELECT SUM(amount) as total FROM contract_expenses WHERE contract_id = ? AND is_processed = 0");
    mysqli_stmt_bind_param($stmt_expenses, "i", $contract_id);
    mysqli_stmt_execute($stmt_expenses);
    $unpaid_expenses = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_expenses))['total'];
    mysqli_stmt_close($stmt_expenses);

    // 3. Totals
    $existing_shortfall = (float)$contract['shortfall_amount'];
    $future_total_interest = $calculated_interest + $existing_shortfall; // Future Total Interest = Generated Interest + Existing Shortfall

    $outstanding_principal = (float)$contract['current_outstanding_principal'];
    $future_payoff_amount = $outstanding_principal + $future_total_interest + $unpaid_expenses;

    $results[$contract_id] = [
        'future_date' => $future_date,
        'future_total_interest' => $future_total_interest,
        'future_payoff_amount' => $future_payoff_amount
    ];
}

echo json_encode(['success' => true, 'data' => $results]);
