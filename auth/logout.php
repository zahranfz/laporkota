<?php
/**
 * LaporKota - Civic Complaint Platform (Purwokerto, Banyumas)
 * User Logout Handler
 */

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/helpers.php';

startSession();
logout();
flash('success', 'Berhasil keluar.');
redirect(base_url('auth/login.php'));
