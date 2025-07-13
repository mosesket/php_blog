<?php
require_once 'config/database.php';

// Destroy session and redirect
session_destroy();
redirect('index.php');
?>