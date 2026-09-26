<?php
/**
 * Front Controller: वेबसाइट और एडमिन के सभी अनुरोध यहीं से शुरू होते हैं।
 */
declare(strict_types=1);

define('APP_START', microtime(true));
define('BASE_PATH', __DIR__);

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    exit('इस सॉफ़्टवेयर के लिए PHP 8.1 या नया वर्ज़न चाहिए। आपका वर्ज़न: ' . PHP_VERSION);
}

require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(BASE_PATH);

(new App\Core\App(BASE_PATH))->run();
