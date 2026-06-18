<?php
// ============================================================
//  EMOL - Live Production Configuration
//  Domain:emol.infinityfree.io
// ============================================================

// --- Database (Matched to your if0_41869377 account) ---
define('DB_HOST',     'sql213.infinityfree.com'); 
define('DB_USER',     'if0_42210335');           
define('DB_PASSWORD', 'OaezXl1vHNSCoyC');           
define('DB_NAME',     'if0_42210335_emol_db');   

// --- App URL (Updated for Wuaze domain) ---
define('APP_NAME',    'EMOL');
define('APP_URL',     'http://emol.infinityfree.io'); 
define('SESSION_TIMEOUT', 1800);

// --- Google OAuth (Updated Redirect URI) ---
define('GOOGLE_CLIENT_ID',     '948199772430-rfcmsd3c7s6cl8q52odn5tn18jvsh66o.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'http://emol.infinityfree.io/handlers/google_auth.php');

// --- Google Maps API ---
define('GOOGLE_MAPS_KEY',     ''); 

// --- OpenAI (Full Key Applied) ---
define('OPENAI_KEY',   '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');
define('OPENAI_MODEL', 'gpt-4o-mini');

// --- SMTP (Gmail) ---
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'muomaifeblaise@gmail.com');
define('SMTP_PASSWORD', 'ctan tcln zogg qbkl');
define('SMTP_FROM',     'muomaifeblaise@gmail.com');
define('SMTP_FROM_NAME','EMOL');

/**
 * Helper function to get a shared database connection
 */
function getDB() {
    static $db;
    if ($db) return $db;
    
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($db->connect_error) {
        error_log("Connection failed: " . $db->connect_error);
        die("Service temporarily unavailable.");
    }
    
    $db->set_charset("utf8mb4");
    return $db;
}