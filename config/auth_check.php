<?php

if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800); 

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();
