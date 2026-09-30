<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;

/** न्यूज़लेटर: सब्सक्राइब (डबल ऑप्ट-इन), अनसब्सक्राइब, कैंपेन बनाना/भेजना (कतार) */
final class NewsletterService
{
    public static function enabled(): bool
    {
        return setting('newsletter_enabled', '1') === '1';
    }

    /**
     * सब्सक्राइब। $confirmed = पहले से सत्यापित ईमेल (पाठक खाता)। लौटाए: 'pending' | 'subscribed'
     */
    public static function subscribe(string $email, ?string $name, string $source, ?int $readerId = null, bool $confirmed = false, array $listIds = []): string
    {
        $email = mb_strtolower(trim($email));
        $double = setting('newsletter_double_optin', '1') === '1' && !$confirmed;
        $s = db()->first('SELECT * FROM {p}newsletter_subscribers WHERE email = ?', [$email]);
        $status = $double ? 'pending' : 'subscribed';
        if ($s) {
            if ($s['status'] === 'subscribed') {
                $status = 'subscribed';
            } else {
                NewsletterSubscriber::update((int) $s['id'], ['status' => $status, 'confirmed_at' => $status === 'subscribed' ? date('Y-m-d H:i:s') : null, 'unsubscribed_at' => null,
                    'reader_id' => $readerId ?? $s['reader_id'], 'name' => $name ?: $s['name']]);
            }
            $id = (int) $s['id'];
            $token = $s['token'];
        } else {
            $token = sha1(random_bytes(20));
            $id = NewsletterSubscriber::create(['email' => $email, 'name' => $name ? mb_substr(strip_tags($name), 0, 120) : null, 'status' => $status, 'token' => $token,
                'source' => mb_substr($source, 0, 40), 'reader_id' => $readerId, 'confirmed_at' => $status === 'subscribed' ? date('Y-m-d H:i:s') : null]);
        }
        $lists = $listIds ?: array_map('intval', array_column(db()->all('SELECT id FROM {p}newsletter_lists WHERE is_default = 1'), 'id'));
        foreach ($lists as $l) {
            db()->query('INSERT IGNORE INTO {p}newsletter_list_subscribers (list_id, subscriber_id) VALUES (?, ?)', [$l, $id]);
        }
        if ($status === 'pending' && (!$s || $s['status'] !== 'subscribed')) {
            $link = route('newsletter.confirm', ['token' => $token]);
            NotificationService::mail($email, 'न्यूज़लेटर की पुष्टि करें', '<p>' . e((string) setting('site_name')) . ' का न्यूज़लेटर पाने के लिए पुष्टि करें:</p>'
                . '<p><a href="' . e($link) . '" style="background:#d71920;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;display:inline-block">हाँ, मुझे न्यूज़लेटर भेजें</a></p>'
                . '<p style="font-size:12px;color:#666">अगर आपने यह नहीं माँगा तो इस ईमेल को अनदेखा करें; आपको कुछ नहीं भेजा जाएगा।</p>');
        }
        return $status;
    }

    public static function unsubscribe(int $id): void
    {
        NewsletterSubscriber::update($id, ['status' => 'unsubscribed', 'unsubscribed_at' => date('Y-m-d H:i:s')]);
        db()->query("DELETE FROM {p}newsletter_sends WHERE subscriber_id = ? AND status = 'queued'", [$id]);
    }

    /** आज की टॉप ख़बरें (पिछले 24 घंटे, सबसे ज़्यादा पढ़ी; कम हों तो ताज़ा) */
    public static function topNews(int $n): array
    {
        if ($n < 1) {
            return [];
        }
        $rows = NewsQuery::list('n.published_at >= NOW() - INTERVAL 1 DAY', [], $n, 0, 'n.views DESC, n.published_at DESC');
        return $rows ?: NewsQuery::latest($n);
    }

    /** टेम्पलेट में सामग्री भरें; $sub = null → प्रीव्यू/टेस्ट */
    public static function render(array $c, ?array $sub): string
    {
        $tpl = $c['template_id'] ? db()->value('SELECT html FROM {p}newsletter_templates WHERE id = ?', [$c['template_id']]) : null;
        $tpl = $tpl ?: (string) db()->value('SELECT html FROM {p}newsletter_templates ORDER BY is_default DESC, id LIMIT 1');
        $top = '';
        if ((int) $c['include_top'] > 0) {
            $top = '<h3 style="font-size:18px;margin:8px 0 10px;border-bottom:2px solid #eee;padding-bottom:6px">आज की बड़ी ख़बरें</h3>';
            foreach (self::topNews((int) $c['include_top']) as $n) {
                $img = $n['featured_image'] ? '<img src="' . e(media_url($n['featured_image'], 'thumb')) . '" width="96" alt="" style="border-radius:4px;display:block">' : '';
                $top .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px"><tr>' . ($img ? '<td width="104" valign="top">' . $img . '</td>' : '')
                    . '<td valign="top" style="font-size:15px;line-height:1.4"><a href="' . e(NewsService::url($n)) . '" style="color:#1c1b1d;font-weight:bold;text-decoration:none">' . e($n['title']) . '</a></td></tr></table>';
            }
        }
        $brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? (string) setting('primary_color') : '#d71920';
        $logo = setting('logo') ? '<img src="' . e(upload_url((string) setting('logo'))) . '" alt="' . e((string) setting('site_name')) . '" height="40" style="display:block;max-height:40px">' : e((string) setting('site_name'));
        $unsub = $sub ? route('newsletter.unsubscribe', ['token' => $sub['token']]) : url();
        return strtr($tpl, [
            '{{subject}}' => e((string) $c['subject']), '{{preheader}}' => e((string) $c['preheader']), '{{content}}' => (string) $c['content'], '{{top_news}}' => $top,
            '{{unsubscribe_url}}' => e($unsub), '{{site_name}}' => e((string) setting('site_name')), '{{site_url}}' => e(url()), '{{brand_color}}' => $brand, '{{logo}}' => $logo,
            '{{name}}' => e((string) ($sub['name'] ?? 'पाठक')),
        ]);
    }

    public static function sendOne(array $c, array $sub): bool
    {
        $unsub = route('newsletter.unsubscribe', ['token' => $sub['token']]);
        return app('mailer')->send($sub['email'], (string) $c['subject'], self::render($c, $sub), ['List-Unsubscribe: <' . $unsub . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
    }

    /** कैंपेन को कतार में डालें (सूचियों के सब्सक्राइब्ड लोग) */
    public static function start(int $id): int
    {
        $c = NewsletterCampaign::find($id);
        if (!$c || !in_array($c['status'], ['draft', 'scheduled'], true)) {
            return 0;
        }
        $lists = array_filter(array_map('intval', explode(',', (string) $c['list_ids'])));
        $where = $lists ? 'AND s.id IN (SELECT ls.subscriber_id FROM {p}newsletter_list_subscribers ls WHERE ls.list_id IN (' . implode(',', $lists) . '))' : '';
        $n = db()->query("INSERT IGNORE INTO {p}newsletter_sends (campaign_id, subscriber_id) SELECT ?, s.id FROM {p}newsletter_subscribers s WHERE s.status = 'subscribed' $where", [$id])->rowCount();
        NewsletterCampaign::update($id, ['status' => 'sending', 'started_at' => date('Y-m-d H:i:s'), 'recipients' => (int) db()->value('SELECT COUNT(*) FROM {p}newsletter_sends WHERE campaign_id = ?', [$id])]);
        return $n;
    }

    /** कतार: तय समय वाले शुरू करें, फिर कुछ ईमेल भेजें */
    public static function process(?int $limit = null, int $seconds = 20): int
    {
        foreach (db()->all("SELECT id FROM {p}newsletter_campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW() LIMIT 5") as $c) {
            self::start((int) $c['id']);
        }
        $limit ??= max(1, min(500, (int) setting('newsletter_batch', '30')));
        $start = microtime(true);
        $sent = 0;
        $campaigns = [];
        $rows = db()->all("SELECT d.id sid, d.campaign_id, s.* FROM {p}newsletter_sends d JOIN {p}newsletter_subscribers s ON s.id = d.subscriber_id
            JOIN {p}newsletter_campaigns c ON c.id = d.campaign_id AND c.status = 'sending' WHERE d.status = 'queued' ORDER BY d.id LIMIT $limit");
        foreach ($rows as $r) {
            if (microtime(true) - $start > $seconds) {
                break;
            }
            $cid = (int) $r['campaign_id'];
            $campaigns[$cid] ??= NewsletterCampaign::find($cid);
            if (!db()->query("UPDATE {p}newsletter_sends SET status = 'sent', sent_at = NOW() WHERE id = ? AND status = 'queued'", [$r['sid']])->rowCount()) {
                continue; // कोई और प्रोसेस ले चुका
            }
            $ok = $r['status'] === 'subscribed' && self::sendOne($campaigns[$cid], $r);
            if (!$ok) {
                db()->query("UPDATE {p}newsletter_sends SET status = 'failed', error = ? WHERE id = ?", [$r['status'] === 'subscribed' ? 'mail() विफल' : 'अनसब्सक्राइब', $r['sid']]);
            }
            $sent++;
        }
        foreach (array_keys($campaigns) as $cid) {
            self::recount($cid);
        }
        // जिन कैंपेन में कुछ बाकी नहीं
        foreach (db()->all("SELECT c.id FROM {p}newsletter_campaigns c WHERE c.status = 'sending' AND NOT EXISTS (SELECT 1 FROM {p}newsletter_sends d WHERE d.campaign_id = c.id AND d.status = 'queued')") as $c) {
            self::recount((int) $c['id']);
            NewsletterCampaign::update((int) $c['id'], ['status' => 'sent', 'finished_at' => date('Y-m-d H:i:s')]);
        }
        return $sent;
    }

    public static function recount(int $id): void
    {
        $r = db()->first("SELECT SUM(status = 'sent') s, SUM(status = 'failed') f, COUNT(*) t FROM {p}newsletter_sends WHERE campaign_id = ?", [$id]);
        NewsletterCampaign::update($id, ['sent' => (int) $r['s'], 'failed' => (int) $r['f'], 'recipients' => (int) $r['t']]);
    }
}
