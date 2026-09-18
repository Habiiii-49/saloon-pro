<?php
/**
 * Redirect from the legacy dashboard URL to the new admin dashboard.
 * Kept so any old bookmarks / links continue to work.
 */
require_once __DIR__ . '/../includes/functions.php';

redirect('admin/index.php');