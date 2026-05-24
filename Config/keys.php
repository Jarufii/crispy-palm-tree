<?php
// =============================================================
// UMDC — API Keys & Gateway Configuration  [TEST / LOCAL MODE]
// =============================================================
// ⚠️  KEEP THIS FILE OUT OF VERSION CONTROL
//     Add to .gitignore:  Config/keys.php
//
// To go live: replace sk_test_/pk_test_ with your live keys
// and set APP_ENV = 'production'
// =============================================================

// ── PayMongo (Sandbox) ────────────────────────────────────────
// Get test keys from: https://dashboard.paymongo.com/developers
// (flip the dashboard toggle to "Test Mode")
define('PAYMONGO_SECRET_KEY',  getenv('PAYMONGO_SECRET_KEY')  ?: 'sk_test_REPLACE_WITH_YOUR_TEST_KEY');
define('PAYMONGO_PUBLIC_KEY',  getenv('PAYMONGO_PUBLIC_KEY')  ?: 'pk_test_REPLACE_WITH_YOUR_TEST_KEY');
define('PAYMONGO_WEBHOOK_SIG', getenv('PAYMONGO_WEBHOOK_SIG') ?: '');   // Leave blank for local dev

// ── Semaphore SMS (Disabled for local dev) ────────────────────
// Leave SEMAPHORE_API_KEY blank → notifications.php will log
// instead of sending real SMS messages
define('SEMAPHORE_API_KEY',    getenv('SEMAPHORE_API_KEY') ?: '');
define('SEMAPHORE_SENDER',     'UMDC');

// ── App ───────────────────────────────────────────────────────
define('APP_URL',   getenv('APP_URL')   ?: 'http://localhost/umdc');
define('APP_ENV',   getenv('APP_ENV')   ?: 'development');  // development | production
