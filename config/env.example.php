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
    // आपात स्थिति के लिए (काम होने के बाद हटा दें):
    // 'ADMIN_IP_BYPASS'   => 1,   // एडमिन IP allowlist से बाहर हो गए हों
    // 'TWO_FACTOR_BYPASS' => 1,   // ईमेल न जाने से OTP नहीं मिल रहा (दो-चरण लॉगिन अस्थायी रूप से बंद)
];
