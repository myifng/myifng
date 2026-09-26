<?php
/**
 * उदाहरण। असली env.php इंस्टॉलर अपने आप बनाता है।
 * हाथ से बनानी हो तो इस फ़ाइल को env.php नाम से कॉपी करके मान भरें, फिर storage/installed.lock बनाएँ।
 */
return [
    'APP_URL'    => 'https://example.com',
    'APP_KEY'    => 'यहाँ-64-अक्षर-की-रैंडम-कुंजी',
    'DEBUG'      => false,
    'TIMEZONE'   => 'Asia/Kolkata',
    'ADMIN_PATH' => 'admin',
    'CACHE'      => true,
    'DB_HOST'    => 'localhost',
    'DB_PORT'    => 3306,
    'DB_NAME'    => '',
    'DB_USER'    => '',
    'DB_PASS'    => '',
    'DB_PREFIX'  => 'np_',
];
