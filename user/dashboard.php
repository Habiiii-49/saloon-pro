<?php
/**
 * Elegance Salon - Receptionist Dashboard entry
 * roleHome() points here; forwards to the full receptionist dashboard.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('receptionist');

redirect('user/index.php');