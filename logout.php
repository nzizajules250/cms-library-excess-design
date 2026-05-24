<?php
require_once 'config.php';
session_unset();
session_destroy();
session_start();
setFlash('info', 'You have been signed out.');
redirect('login.php');
?>