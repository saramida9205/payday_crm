<?php
/* Database credentials. Assuming you are running MariaDB
server with default setting (user 'root' with no password) */

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'yi0000');
define('DB_PASSWORD', 'Rr92!@#053026');
define('DB_NAME', 'payday_db');

// --- SMS API Settings (Test Sender Phone) ---
define('TEST_SENDER_PHONE', '16882200'); // 테스트용 발신번호

// --- Wideshot SMS API Settings ---
define('WIDESHOT_API_URL', 'https://apimsg.wideshot.co.kr'); // 와이드샷 운영 서버 URL
define('WIDESHOT_API_KEY', 'Y1praFFzU2hVYzF0dEwzUWRiYjh4Y1dNSVIzNllWZWhrdVJBOXROK2NRa1RjTGN1OGxobDNDVjI0Q1FCZ25pYw=='); // 와이드샷 API 키 (DB 설정 없을 시 fallback)


/* Attempt to connect to MariaDB database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>