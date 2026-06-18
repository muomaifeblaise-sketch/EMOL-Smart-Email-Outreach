<?php
// ============================================================
//  ADD THESE LINES TO YOUR config.php
//  Place them right after the existing "--- App ---" block
// ============================================================

// --- Google OAuth ---
define('GOOGLE_CLIENT_ID',     '948199772430-rfcmsd3c7s6cl8q52odn5tn18jvsh66o.apps.googleusercontent.com');          // Paste your Client ID here
define('GOOGLE_CLIENT_SECRET', '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');          // Paste your Client Secret here

// This MUST exactly match what you register in Google Cloud Console
// For XAMPP: http://localhost/emol/public/pages/handlers/google_auth.php
// For live:  https://yourdomain.com/public/pages/handlers/google_auth.php
define('GOOGLE_REDIRECT_URI',  APP_URL . 'http://localhost/emol/handlers/google_auth.php');
