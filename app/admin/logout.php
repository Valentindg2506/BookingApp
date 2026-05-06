<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

session_name(SESSION_NAME);
session_start();
session_unset();
session_destroy();

redirect(APP_URL . '/admin/index.php');
