<?php
declare(strict_types=1);

namespace App\Core;

/**
 * सरल PSR-4 ऑटोलोडर: App\Controllers\Admin\UserController → app/Controllers/Admin/UserController.php
 * Composer की ज़रूरत नहीं।
 */
final class Autoloader
{
    public static function register(string $basePath): void
    {
        spl_autoload_register(static function (string $class) use ($basePath): void {
            if (!str_starts_with($class, 'App\\')) {
                return;
            }
            $file = $basePath . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        require_once $basePath . '/app/Helpers/helpers.php';
    }
}
