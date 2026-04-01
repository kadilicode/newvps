<?php
// ============================================
// KADILI NET - Configuration File
// Domain: kadilihotspot.online
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'kadili_net');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'KADILI NET');
define('SITE_URL', 'http://199.192.21.242');
define('SITE_LOGO', 'https://i.ibb.co/6JgKgFW1/grok-image-1771875696946-removebg-preview.webp');

// PalmPesa
define('PALMPESA_API_KEY', 'lBdPSHSGWkO4qSChKVr40EjVaGQuIPjOmWHj1o7Cs3GaQaLYSzkhZy5jXVm9');
define('PALMPESA_ENDPOINT', 'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate');

// Beem SMS
define('BEEM_SMS_KEY', 'f8d1f94c2e0c105e');
define('BEEM_SMS_SECRET', 'ODJmYTVkMWI5N2U3OTZmZWEzZWE3NjZlOWQ4OTBmOTExYjRiM2E0NGE2ZjA5ZGNkZWRiNjBmNDYxNmQxMmYwNA==');
define('BEEM_OTP_KEY', 'e7290c98483303bf');
define('BEEM_OTP_SECRET', 'NjFhNjNmMWVkNTFhODc0MTQzMzg5ZjdiY2FmZDY0MWQyMTljN2IyOWYxOTM3YmE1MzBmNDkzYWI2YzQ0ZDk2OQ==');
define('BEEM_SENDER_ID', 'KADILINET');

// Business settings
define('SETUP_FEE', 150000);
define('MONTHLY_FEE', 10000);
define('WITHDRAWAL_MIN', 31500);
define('WITHDRAWAL_FEE_PERCENT', 5);

// Session
define('SESSION_LIFETIME', 3600 * 8);
define('TIMEZONE', 'Africa/Dar_es_Salaam');

date_default_timezone_set(TIMEZONE);
session_start();
