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
    // Faint tilted logo repeated over every page (applied when pages are imported)
    'watermark'    => [
        'enabled' => true,
        'logo'    => __DIR__ . '/assets/watermark.png', // logo on a transparent background, dark colour
        'opacity' => 0.07,   // 0.05 very faint ... 0.25 strong
        'size'    => 0.09,   // logo width as a share of the page width
        'angle'   => 30,     // degrees, rising to the right
        'gap_x'   => 0.15,   // distance between logos across, share of page width
        'gap_y'   => 0.10,   // distance between rows, share of page width
        'color'   => '#000000',
    ],
    // Secret shared with EpaperExport.jsx (apiKey) so InDesign can upload pages directly.
    // Use a long random value, e.g. from https://www.random.org/strings/ ; empty = switched off.
    'api_key'      => '442cccc1a38d29c40acf00fbce22482368ec6c002a93d5bb',

    // ---- Reader subscriptions & paywall ----
    'paywall' => [
        'enabled'        => true,   // false = whole reader stays open; account/subscribe pages still work
        'free_latest'    => true,   // the newest published date per edition is free to everyone
        'free_downloads' => false,  // allow "Download page" on the free latest issue
    ],
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
