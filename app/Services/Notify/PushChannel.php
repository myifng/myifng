<?php
declare(strict_types=1);

namespace App\Services\Notify;

/** वेब पुश: recipient = push_subscriptions.id */
final class PushChannel implements Channel
{
    public function send(string $recipient, array $message): bool|string
    {
        $sub = db()->first('SELECT * FROM {p}push_subscriptions WHERE id = ?', [(int) $recipient]);
        if (!$sub) {
            return 'gone';
        }
        $icon = setting('favicon') ? upload_url((string) setting('favicon')) : (setting('logo') ? upload_url((string) setting('logo')) : null);
        $r = WebPush::send($sub, array_filter(['title' => $message['title'], 'body' => $message['body'], 'url' => $message['url'] ?: url(), 'icon' => $icon,
            'image' => $message['image'] ?? null, 'tag' => $message['tag'] ?? null]), $message['urgency'] ?? 'normal');
        if ($r === true) {
            db()->query('UPDATE {p}push_subscriptions SET fails = 0, last_sent_at = NOW() WHERE id = ?', [$sub['id']]);
        } elseif ($r === 'gone' || (int) $sub['fails'] >= 4) {
            db()->query('DELETE FROM {p}push_subscriptions WHERE id = ?', [$sub['id']]);
            return 'gone';
        } else {
            db()->query('UPDATE {p}push_subscriptions SET fails = fails + 1 WHERE id = ?', [$sub['id']]);
        }
        return $r;
    }
}
