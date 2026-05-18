<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
redirect(BASE_URL . '/admin/login.php');
