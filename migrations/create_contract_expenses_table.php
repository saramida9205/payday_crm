<?php
require_once __DIR__ . '/../config.php';

// Attempt to connect to MariaDB database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

$sql = "CREATE TABLE IF NOT EXISTS contract_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    expense_date DATE NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    remarks TEXT,
    is_processed TINYINT(1) DEFAULT 0,
    processed_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($link, $sql)) {
    echo "Table 'contract_expenses' created successfully.";
} else {
    echo "ERROR: Could not create table 'contract_expenses'. " . mysqli_error($link);
}

mysqli_close($link);
?>
