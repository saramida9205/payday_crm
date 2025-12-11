<?php
require_once __DIR__ . '/../common.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for admin permission
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    die('다운로드 권한이 없습니다.');
}

global $link;

$db_to_backup = $_GET['db_name'] ?? '';

if (empty($db_to_backup)) {
    die('백업할 데이터베이스 이름이 지정되지 않았습니다.');
}

// Security check: only allow downloading databases with the correct prefix
if (strpos($db_to_backup, DB_NAME . '_') !== 0) {
    die('잘못된 데이터베이스 이름 형식입니다. 다운로드할 수 없습니다.');
}

// Increase execution time and memory limit
set_time_limit(0);
ini_set('memory_limit', '1024M');

$filename = $db_to_backup . ".sql";

// Set headers to force download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Start of SQL dump
fwrite($output, "-- Payday CRM SQL Dump\n");
fwrite($output, "--\n");
fwrite($output, "-- Host: " . DB_SERVER . "\n");
fwrite($output, "-- Generation Time: " . date('Y-m-d H:i:s') . "\n");
fwrite($output, "-- Database: `{$db_to_backup}`\n");
fwrite($output, "-- ------------------------------------------------------\n\n");

fwrite($output, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($output, "SET AUTOCOMMIT = 0;\n");
fwrite($output, "START TRANSACTION;\n");
fwrite($output, "SET time_zone = \"+00:00\";\n\n");

fwrite($output, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($output, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
fwrite($output, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
fwrite($output, "/*!40101 SET NAMES utf8mb4 */;\n\n");

// Get all tables
$tables_result = mysqli_query($link, "SHOW TABLES FROM `{$db_to_backup}`");
if (!$tables_result) {
    die('Could not fetch tables from database: ' . mysqli_error($link));
}

$tables = [];
while ($row = mysqli_fetch_row($tables_result)) {
    $tables[] = $row[0];
}

// Loop through tables
foreach ($tables as $table) {
    fwrite($output, "--\n-- Table structure for table `{$table}`\n--\n\n");
    fwrite($output, "DROP TABLE IF EXISTS `{$table}`;\n");

    // Get CREATE TABLE statement
    $create_table_result = mysqli_query($link, "SHOW CREATE TABLE `{$db_to_backup}`.`{$table}`");
    $create_table_row = mysqli_fetch_row($create_table_result);
    fwrite($output, $create_table_row[1] . ";\n\n");
    mysqli_free_result($create_table_result);

    // Get table data
    $data_result = mysqli_query($link, "SELECT * FROM `{$db_to_backup}`.`{$table}`");
    $num_fields = mysqli_num_fields($data_result);
    $num_rows = mysqli_num_rows($data_result);

    if ($num_rows > 0) {
        fwrite($output, "--\n-- Dumping data for table `{$table}`\n--\n\n");
        fwrite($output, "LOCK TABLES `{$table}` WRITE;\n");
        fwrite($output, "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;\n");

        while ($row = mysqli_fetch_row($data_result)) {
            $values = [];
            for ($j = 0; $j < $num_fields; $j++) {
                if (is_null($row[$j])) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . mysqli_real_escape_string($link, $row[$j]) . "'";
                }
            }
            fwrite($output, "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n");
        }

        fwrite($output, "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;\n");
        fwrite($output, "UNLOCK TABLES;\n\n");
    }
    mysqli_free_result($data_result);
}

fwrite($output, "COMMIT;\n");

fclose($output);
exit;
?>