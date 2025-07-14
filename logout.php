<?php
require_once 'functions.php';

// Destroy the session
session_destroy();

// Redirect to login page
redirect('index.php');
?>