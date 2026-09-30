<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Notify\Channel;
use App\Services\Notify\EmailChannel;
use App\Services\Notify\GatewayChannel;
use App\Services\Notify\PushChannel;

/**
 * नोटिफ़िकेशन सेंटर: इवेंट → चैनल (सेटिंग से) → इन-ऐप तुरंत, बाकी कतार (outbox) में।
 * कतार हर मिनट शेड्यूलर से चलती है (cron ज़रूरी नहीं); ग़लती पर 3 बार दोबारा।
 */
final class NotificationService
{
    public const CHANNELS = ['inapp' => 'इन-ऐप (घंटी/खाता)', 'email' => 'ईमेल', 'push' => 'वेब पुश', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];

    /** इवेंट: [लेबल, किसे, संभव चैनल, डिफ़ॉल्ट चैनल] */
    public const EVENTS = [
        'breaking' => ['ब्रेकिंग न्यूज़', 'पाठक / पुश सब्सक्राइबर', ['push', 'inapp', 'email', 'sms', 'whatsapp'], ['push', 'inapp']],
        'epaper' => ['ई-पेपर प्रकाशित', 'पाठक / पुश सब्सक्राइबर', ['push', 'inapp', 'email'], ['push', 'inapp']],
        'followed' => ['फ़ॉलो की गई चीज़ की नई ख़बर', 'फ़ॉलो करने वाले पाठक', ['inapp', 'email'], ['inapp']],
        'news_approved' => ['ख़बर स्वीकृत / प्रकाशित', 'रिपोर्टर', ['inapp', 'email', 'sms', 'whatsapp'], ['inapp', 'email']],
        'news_returned' => ['ख़बर लौटाई / अस्वीकृत', 'रिपोर्टर', ['inapp', 'email', 'sms', 'whatsapp'], ['inapp', 'email']],
        'news_submitted' => ['नई ख़बर जाँच के लिए', 'डेस्क (स्वीकृति वाले)', ['inapp', 'email'], ['inapp']],
        'assignment' => ['नया असाइनमेंट', 'रिपोर्टर', ['inapp', 'email', 'sms', 'whatsapp'], ['inapp', 'email']],
        'reporter_approved' => ['रिपोर्टर आवेदन स्वीकृत', 'आवेदक', ['email', 'sms', 'whatsapp'], ['email']],
        'document_expiry' => ['रिपोर्टर की वैधता ख़त्म होने वाली', 'रिपोर्टर + एडमिन', ['inapp', 'email', 'sms', 'whatsapp'], ['inapp', 'email']],
        'comment' => ['नई टिप्पणी मॉडरेशन के लिए', 'मॉडरेटर', ['inapp'], ['inapp']],
        'comment_reply' => ['टिप्पणी का जवाब / स्वीकृति', 'पाठक', ['inapp', 'email'], ['inapp']],
        'manual' => ['हाथ से भेजी सूचना', 'चुने हुए', ['push', 'inapp', 'email'], ['push']],
    ];

    /** इस इवेंट के चालू चैनल */
    public static function channels(string $event): array
    {
        $def = self::EVENTS[$event] ?? null;
        if (!$def) {
            return [];
        }
        $saved = SettingService::all()['notify_' . $event] ?? null; // '' = एडमिन ने सब चैनल बंद किए
        $on = $saved === null ? $def[3] : array_filter(explode(',', (string) $saved));
        return array_values(array_intersect($def[2], $on));
    }

    /**
     * भेजें। $to: users => [id], readers => [id], emails => [..], push => 'all'|'topic:xyz'|[sub ids], sms/whatsapp => [नंबर]
     * $msg: title, body, url, image?, urgency?, tag?
     */
    public static function notify(string $event, array $to, array $msg, ?array $only = null): int
    {
        $msg = ['title' => mb_substr(trim((string) ($msg['title'] ?? '')), 0, 200), 'body' => mb_substr(trim((string) ($msg['body'] ?? '')), 0, 500),
            'url' => (string) ($msg['url'] ?? ''), 'image' => $msg['image'] ?? null, 'urgency' => $msg['urgency'] ?? 'normal', 'tag' => $msg['tag'] ?? $event];
        if ($msg['title'] === '') {
            return 0;
        }
        $ch = array_flip($only ?? self::channels($event)); // $only: हाथ से भेजते समय चुना चैनल
        $n = 0;
        try {
            $users = array_values(array_unique(array_filter(array_map('intval', $to['users'] ?? []))));
            $readers = array_values(array_unique(array_filter(array_map('intval', $to['readers'] ?? []))));
            if (isset($ch['inapp'])) {
                foreach ([['user', $users], ['reader', $readers]] as [$type, $ids]) {
                    foreach ($ids as $id) {
                        db()->insert('notifications', ['recipient_type' => $type, 'recipient_id' => $id, 'event' => $event, 'title' => $msg['title'],
                            'body' => $msg['body'] ?: null, 'url' => $msg['url'] ?: null, 'created_at' => date('Y-m-d H:i:s')]);
                        $n++;
                    }
                }
            }
            if (isset($ch['email'])) {
                $emails = $to['emails'] ?? [];
                if ($users) {
                    $emails = array_merge($emails, array_column(db()->all("SELECT email FROM {p}users WHERE status = 'active' AND deleted_at IS NULL AND id IN (" . implode(',', $users) . ')'), 'email'));
                }
                if ($readers) { // पाठक: सिर्फ़ जिन्होंने ईमेल चुना
                    foreach (db()->all("SELECT email, prefs FROM {p}readers WHERE status = 'active' AND id IN (" . implode(',', $readers) . ')') as $r) {
                        if (ReaderService::prefs($r)['email']) {
                            $emails[] = $r['email'];
                        }
                    }
                }
                foreach (array_unique(array_filter(array_map('mb_strtolower', $emails), static fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))) as $e) {
                    $n += self::queue('email', $event, $e, $msg);
                }
            }
            if (isset($ch['push']) && !empty($to['push']) && setting('push_enabled', '0') === '1') {
                $n += self::queuePush($event, $to['push'], $msg);
            }
            foreach (['sms', 'whatsapp'] as $g) {
                if (isset($ch[$g]) && setting($g . '_driver', 'off') !== 'off') {
                    foreach (array_unique(array_filter($to[$g] ?? [])) as $num) {
                        $n += self::queue($g, $event, (string) $num, $msg);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Notify ' . $event . ': ' . $e->getMessage());
        }
        return $n;
    }

    private static function queue(string $channel, string $event, string $recipient, array $msg): int
    {
        db()->insert('notification_outbox', ['channel' => $channel, 'event' => $event, 'recipient' => mb_substr($recipient, 0, 500),
            'payload' => json_encode($msg, JSON_UNESCAPED_UNICODE), 'created_at' => date('Y-m-d H:i:s'), 'send_after' => date('Y-m-d H:i:s')]);
        return 1;
    }

    /** पुश: सभी / किसी विषय वाले / चुने सब्सक्रिप्शन (एक SQL से कतार) */
    private static function queuePush(string $event, string|array $target, array $msg): int
    {
        $where = '1=1';
        $params = [];
        if (is_array($target)) {
            $ids = array_filter(array_map('intval', $target));
            if (!$ids) {
                return 0;
            }
            $where = 'id IN (' . implode(',', $ids) . ')';
        } elseif (str_starts_with($target, 'topic:')) {
            $where = 'FIND_IN_SET(?, topics)';
            $params[] = substr($target, 6);
        }
        return db()->query("INSERT INTO {p}notification_outbox (channel, event, recipient, payload, status, send_after, created_at)
            SELECT 'push', ?, id, ?, 'queued', NOW(), NOW() FROM {p}push_subscriptions WHERE $where", [$event, json_encode($msg, JSON_UNESCAPED_UNICODE), ...$params])->rowCount();
    }

    private static function driver(string $channel): ?Channel
    {
        return match ($channel) {
            'email' => new EmailChannel(),
            'push' => new PushChannel(),
            'sms', 'whatsapp' => new GatewayChannel($channel),
            default => null,
        };
    }

    /** कतार चलाएँ (शेड्यूलर हर मिनट; एडमिन "अभी भेजें") */
    public static function process(int $limit = 50, int $seconds = 20): array
    {
        $start = microtime(true);
        $done = ['sent' => 0, 'failed' => 0];
        $rows = db()->all("SELECT * FROM {p}notification_outbox WHERE status = 'queued' AND send_after <= NOW() ORDER BY channel = 'push' DESC, id LIMIT " . max(1, $limit));
        foreach ($rows as $r) {
            if (microtime(true) - $start > $seconds) {
                break;
            }
            // दोहरी प्रोसेसिंग से बचाव: पहले attempts बढ़ाकर "ले लो"
            if (!db()->query("UPDATE {p}notification_outbox SET attempts = attempts + 1, send_after = NOW() + INTERVAL 5 MINUTE WHERE id = ? AND status = 'queued' AND attempts = ?", [$r['id'], $r['attempts']])->rowCount()) {
                continue;
            }
            $drv = self::driver($r['channel']);
            $res = $drv ? $drv->send($r['recipient'], (array) json_decode($r['payload'], true) + ['title' => '', 'body' => '', 'url' => '']) : 'चैनल नहीं';
            if ($res === true) {
                db()->query("UPDATE {p}notification_outbox SET status = 'sent', sent_at = NOW(), error = NULL WHERE id = ?", [$r['id']]);
                $done['sent']++;
            } else {
                $final = $res === 'gone' || (int) $r['attempts'] + 1 >= 3;
                db()->query('UPDATE {p}notification_outbox SET status = ?, error = ?, send_after = NOW() + INTERVAL ? MINUTE WHERE id = ?',
                    [$final ? 'failed' : 'queued', mb_substr($res === 'gone' ? 'पता अब मान्य नहीं' : (string) $res, 0, 255), 5 * ((int) $r['attempts'] + 1), $r['id']]);
                $done['failed'] += $final ? 1 : 0;
            }
        }
        if (random_int(1, 100) === 1) {
            db()->query("DELETE FROM {p}notification_outbox WHERE status <> 'queued' AND created_at < NOW() - INTERVAL 30 DAY");
            db()->query('DELETE FROM {p}notifications WHERE read_at IS NOT NULL AND created_at < NOW() - INTERVAL 90 DAY');
        }
        return $done;
    }

    public static function unread(string $type, int $id): int
    {
        if (!$id) {
            return 0;
        }
        try {
            return (int) db()->value('SELECT COUNT(*) FROM {p}notifications WHERE recipient_type = ? AND recipient_id = ? AND read_at IS NULL', [$type, $id]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function latest(string $type, int $id, int $limit = 8): array
    {
        try {
            return db()->all('SELECT * FROM {p}notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY id DESC LIMIT ' . $limit, [$type, $id]);
        } catch (\Throwable) {
            return [];
        }
    }

    /** जिन स्टाफ़ यूज़र के पास यह अनुमति है (रोल या व्यक्तिगत) */
    public static function usersWith(string $permission): array
    {
        $pid = (int) db()->value('SELECT id FROM {p}permissions WHERE name = ?', [$permission]);
        return array_map('intval', array_column(db()->all("SELECT DISTINCT u.id FROM {p}users u JOIN {p}roles r ON r.id = u.role_id
            WHERE u.status = 'active' AND u.deleted_at IS NULL AND (r.slug = 'super-admin' OR EXISTS (SELECT 1 FROM {p}role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = ?)
            OR EXISTS (SELECT 1 FROM {p}user_permissions up WHERE up.user_id = u.id AND up.permission_id = ? AND up.allow = 1)) LIMIT 200", [$pid, $pid]), 'id'));
    }

    /** ब्रांड वाले साधारण HTML में ईमेल (तुरंत; लेन-देन वाले मेल जैसे सत्यापन) */
    public static function mail(string $to, string $subject, string $html): bool
    {
        $brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
        $site = e((string) setting('site_name'));
        $body = '<!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body style="margin:0;background:#f2f2f2;font-family:Arial,sans-serif;color:#1c1b1d">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:16px 8px"><table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:6px">'
            . '<tr><td style="background:' . $brand . ';color:#fff;padding:14px 18px;font-size:20px;font-weight:bold">' . $site . '</td></tr>'
            . '<tr><td style="padding:18px;font-size:15px;line-height:1.6">' . $html . '</td></tr>'
            . '<tr><td style="padding:12px 18px;font-size:12px;color:#777;background:#fafafa">' . $site . ' · <a href="' . e(url()) . '" style="color:#777">' . e(url()) . '</a></td></tr></table></td></tr></table></body></html>';
        return app('mailer')->send($to, $subject, $body);
    }
}
