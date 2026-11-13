<?php
// Copy to config.php and edit.
return [
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=epaper;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],
    // URL path where this folder is served, no trailing slash. WAMP: http://localhost/epaper -> '/epaper'
    'base_url'     => '/epaper',
    'site_name'    => 'Mathrubhumi ePaper',
    'storage_dir'  => __DIR__ . '/storage',   // must be writable by PHP
    'storage_url'  => '/epaper/storage',      // public URL of storage_dir
    'medium_width' => 1000,                   // Page List view image width
    'thumb_width'  => 240,                    // Thumb view image width
    'jpeg_quality' => 82,
    'crop_padding' => 6,                      // px around each article crop
    'timezone'     => 'Asia/Kolkata',
    // Secret shared with EpaperExport.jsx (apiKey) so InDesign can upload pages directly.
    // Use a long random value, e.g. from https://www.random.org/strings/ ; empty = switched off.
    'api_key'      => '',

    // ---- Reader subscriptions & paywall ----
    'paywall' => [
        'enabled'        => true,   // false = whole reader stays open; account/subscribe pages still work
        'free_latest'    => true,   // the newest published date per edition is free to everyone
        'free_downloads' => false,  // allow "Download page" on the free latest issue
        // What a signed-in but NOT-subscribed reader (i.e. inside the free trial) may do:
        'trial_archive'   => false, // false = they can open only the latest day; true = any past day too
        'trial_downloads' => false, // false = they cannot download article images; true = they can
    ],
    'trial_days' => 7,              // free days a reader gets from their first login (0 = no trial)
    // Razorpay keys: dashboard.razorpay.com -> Settings -> API Keys. Empty = payments disabled
    // (accounts and the admin still work; the Subscribe button shows a "not configured" notice).
    'razorpay' => [
        'key_id'         => '',   // rzp_test_... while testing, rzp_live_... in production
        'key_secret'     => '',
        'webhook_secret' => '',   // a secret you choose; set the same value in the Razorpay webhook
    ],
    // Outgoing e-mail for verification and password-reset links (PHP mail()).
    'mail' => [
        'from_email' => 'no-reply@localhost',
        'from_name'  => 'Mathrubhumi ePaper',
    ],
];
