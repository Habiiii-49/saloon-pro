<?php
/**
 * Logout – destroys session and redirects to login.
 */
require_once __DIR__ . '/includes/functions.php';

performLogout();

redirect('login.php');