<?php
declare(strict_types=1);

namespace App\Services\Notify;

use App\Services\NotificationService;

final class EmailChannel implements Channel
{
    public function send(string $recipient, array $message): bool|string
    {
        $html = '<h2 style="margin:0 0 8px;font-size:20px">' . e($message['title']) . '</h2>' . ($message['body'] !== '' ? '<p>' . nl2br(e($message['body'])) . '</p>' : '')
            . ($message['url'] ? '<p><a href="' . e($message['url']) . '" style="background:#d71920;color:#fff;padding:9px 16px;border-radius:4px;text-decoration:none;display:inline-block">पूरा पढ़ें / खोलें</a></p>' : '');
        return NotificationService::mail($recipient, $message['title'], $html) ?: 'ईमेल नहीं गया (mail())';
    }
}
