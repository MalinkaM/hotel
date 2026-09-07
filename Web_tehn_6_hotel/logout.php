<?php
require_once 'includes/auth.php';

$_SESSION = [];

session_destroy();
header('Location: index.php');
exit; ?>