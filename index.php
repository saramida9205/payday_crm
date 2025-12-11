<?php
// The login page handles session checks and redirects.
// We can simply forward all requests to the root to login.php.
header("Location: login.php");
exit;
?>