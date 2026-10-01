<?php
declare(strict_types=1);

namespace App\Services\Notify;

use App\Services\NotificationService;

final class EmailChannel implements Channel
{
    public function send(string $recipient, array $message): bool|string
    {
        $html = \App\Services\MailTemplate::title((string) $message['title'], '🔔')
            . ($message['body'] !== '' ? \App\Services\MailTemplate::p(nl2br(e((string) $message['body']))) : '')
            . ($message['url'] ? \App\Services\MailTemplate::button((string) $message['url'], 'पूरा देखें') : '');
        return NotificationService::mail($recipient, $message['title'], $html) ?: 'ईमेल नहीं गया (mail())';
    }
}
