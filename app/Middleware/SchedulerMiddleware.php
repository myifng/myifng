<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Services\NewsService;

/**
 * Shared hosting पर cron के बिना शेड्यूल: हर अनुरोध पर जाँच, पर एक मिनट में अधिकतम एक बार (फ़ाइल कैश लॉक)।
 * जो ख़बरें तय समय पर पहुँच गईं, वे प्रकाशित हो जाती हैं। कोई त्रुटि पेज को नहीं रोकती।
 */
final class SchedulerMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next, string ...$params): Response
    {
        try {
            if (cache()->get('scheduler.news') === null) {
                cache()->set('scheduler.news', time(), 60);
                if (NewsService::publishDue() > 0) {
                    cache()->flush('home');
                }
                if (cache()->get('scheduler.daily') === null) {
                    cache()->set('scheduler.daily', time(), 3600);
                    \App\Services\ReporterService::expireDue(); // वैधता ख़त्म हुए रिपोर्टर
                    \App\Services\OtpService::prune();
                }
            }
        } catch (\Throwable $e) {
            // टेबल अभी न हो (अपडेट बाकी) या DB की दिक्कत: पेज चलता रहे
            logger()->warning('Scheduler: ' . $e->getMessage());
        }
        return $next($request);
    }
}
