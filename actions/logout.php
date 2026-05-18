<?php
require_once '../includes/db.php';
$_SESSION = array();
session_destroy();
header("Location: ../index.php");
exit;