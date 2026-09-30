<?php
declare(strict_types=1);

namespace App\Services;

/**
 * कहाँ क्या हुआ → किसे सूचना (NotificationService से)। मौजूदा कोड में हर जगह बस एक लाइन।
 * कोई ग़लती ख़बर/सेव को नहीं रोकती।
 */
final class NotifyEvents
{
    private static function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            logger()->warning('NotifyEvents: ' . $e->getMessage());
        }
    }

    /** ख़बर की स्थिति बदली (NewsService::transition / publishDue) */
    public static function newsStatus(array $news, string $to, string $remark = ''): void
    {
        self::safe(static function () use ($news, $to, $remark) {
            $reporter = (int) ($news['reporter_id'] ?? 0);
            $editUrl = route('admin.news.edit', ['id' => $news['id']]);
            $mine = $reporter && $reporter !== (int) auth()->id();
            if ($to === 'submitted') {
                $desk = array_values(array_diff(NotificationService::usersWith('news.approve'), [(int) auth()->id()]));
                NotificationService::notify('news_submitted', ['users' => $desk], ['title' => 'जाँच के लिए नई ख़बर', 'body' => (string) $news['title'], 'url' => $editUrl]);
            } elseif (in_array($to, ['published', 'scheduled', 'approved'], true) && $mine) {
                $label = ['published' => 'प्रकाशित हो गई', 'scheduled' => 'प्रकाशन के लिए शेड्यूल हुई', 'approved' => 'स्वीकृत हुई'][$to];
                NotificationService::notify('news_approved', ['users' => [$reporter], 'sms' => self::mobile($reporter), 'whatsapp' => self::mobile($reporter)],
                    ['title' => 'आपकी ख़बर ' . $label, 'body' => (string) $news['title'], 'url' => $to === 'published' ? NewsService::url($news) : $editUrl]);
            } elseif (in_array($to, ['rejected', 'draft'], true) && $mine && !in_array($news['status'], ['draft', 'rejected'], true)) {
                NotificationService::notify('news_returned', ['users' => [$reporter], 'sms' => self::mobile($reporter), 'whatsapp' => self::mobile($reporter)],
                    ['title' => $to === 'rejected' ? 'आपकी ख़बर अस्वीकृत' : 'आपकी ख़बर सुधार के लिए लौटाई गई', 'body' => $news['title'] . ($remark !== '' ? ' — ' . mb_substr($remark, 0, 200) : ''), 'url' => $editUrl]);
            }
            if ($to === 'published') {
                self::followers($news);
            }
        });
    }

    /** फ़ॉलो करने वाले पाठकों को (खाते की घंटी) */
    public static function followers(array $news): void
    {
        self::safe(static function () use ($news) {
            $conds = [];
            $p = [];
            foreach (['category' => 'category_id', 'reporter' => 'reporter_id', 'location' => 'location_id'] as $type => $col) {
                if (!empty($news[$col])) {
                    $conds[] = '(f.type = ? AND f.target_id = ?)';
                    array_push($p, $type, (int) $news[$col]);
                }
            }
            if ($news['category_id'] && ($parent = db()->value('SELECT parent_id FROM {p}categories WHERE id = ?', [$news['category_id']]))) {
                $conds[] = "(f.type = 'category' AND f.target_id = ?)";
                $p[] = (int) $parent;
            }
            foreach (db()->all('SELECT topic_id FROM {p}news_topics WHERE news_id = ?', [$news['id']]) as $t) {
                $conds[] = "(f.type = 'topic' AND f.target_id = ?)";
                $p[] = (int) $t['topic_id'];
            }
            if (!$conds) {
                return;
            }
            $ids = [];
            foreach (db()->all("SELECT DISTINCT r.id, r.prefs FROM {p}reader_follows f JOIN {p}readers r ON r.id = f.reader_id AND r.status = 'active' WHERE " . implode(' OR ', $conds) . ' LIMIT 5000', $p) as $r) {
                if (ReaderService::prefs($r)['followed']) {
                    $ids[] = (int) $r['id'];
                }
            }
            if ($ids) {
                NotificationService::notify('followed', ['readers' => $ids], ['title' => (string) $news['title'], 'body' => (string) ($news['summary'] ?? ''), 'url' => NewsService::url($news)]);
            }
        });
    }

    public static function assignment(array $a): void
    {
        self::safe(static function () use ($a) {
            $rid = (int) ($a['reporter_id'] ?? 0);
            if (!$rid || $rid === (int) auth()->id()) {
                return;
            }
            NotificationService::notify('assignment', ['users' => [$rid], 'sms' => self::mobile($rid), 'whatsapp' => self::mobile($rid)],
                ['title' => 'नया असाइनमेंट: ' . $a['title'], 'body' => !empty($a['deadline']) ? 'समय-सीमा: ' . hindi_date((string) $a['deadline'], true) : '', 'url' => route('admin.assignments.index')]);
        });
    }

    /** रिपोर्टर बना (ईमेल पहले से जाता है; यहाँ SMS/WhatsApp) */
    public static function reporterApproved(string $name, ?string $mobile, string $code): void
    {
        self::safe(static fn() => NotificationService::notify('reporter_approved', ['sms' => [$mobile], 'whatsapp' => [$mobile]],
            ['title' => 'बधाई ' . $name . '! आप ' . setting('site_name') . ' के रिपोर्टर बने', 'body' => 'रिपोर्टर ID: ' . $code, 'url' => route('reporter.login')]));
    }

    private static function mobile(int $userId): array
    {
        $m = db()->value('SELECT COALESCE(NULLIF(r.mobile, \'\'), u.mobile) FROM {p}users u LEFT JOIN {p}reporters r ON r.user_id = u.id WHERE u.id = ?', [$userId]);
        return $m ? [(string) $m] : [];
    }

    /** पाठक जिन्होंने यह सूचना चुनी है */
    private static function readersWanting(string $pref): array
    {
        $out = [];
        foreach (db()->all("SELECT id, prefs FROM {p}readers WHERE status = 'active' LIMIT 20000") as $r) {
            if (ReaderService::prefs($r)[$pref]) {
                $out[] = (int) $r['id'];
            }
        }
        return $out;
    }

    /** हर मिनट (शेड्यूलर): ब्रेकिंग पुश, प्रकाशित ई-पेपर, दस्तावेज़ की वैधता (दिन में एक बार) */
    public static function tick(): void
    {
        self::safe(static function () {
            foreach (db()->all("SELECT * FROM {p}breaking_news WHERE push_status = 'queued' LIMIT 5") as $b) {
                db()->query("UPDATE {p}breaking_news SET push_status = 'sent' WHERE id = ?", [$b['id']]);
                $url = $b['url'] ?: ($b['news_id'] ? NewsService::url(['slug' => (string) db()->value('SELECT slug FROM {p}news WHERE id = ?', [$b['news_id']])]) : url());
                NotificationService::notify('breaking', ['push' => 'topic:breaking', 'readers' => self::readersWanting('breaking')],
                    ['title' => 'ब्रेकिंग: ' . $b['title'], 'body' => (string) setting('site_name'), 'url' => $url, 'urgency' => 'high', 'tag' => 'breaking-' . $b['id']]);
            }
        });
        self::safe(static function () {
            foreach (db()->all("SELECT i.id, i.issue_date, e.name, e.slug FROM {p}epaper_issues i JOIN {p}epaper_editions e ON e.id = i.edition_id
                    WHERE i.status = 'published' AND i.publish_at <= NOW() AND i.publish_at > NOW() - INTERVAL 1 DAY") as $i) {
                if (cache()->get('scheduler.ep' . $i['id'])) {
                    continue;
                }
                cache()->set('scheduler.ep' . $i['id'], 1, 3 * 86400);
                NotificationService::notify('epaper', ['push' => 'topic:epaper', 'readers' => self::readersWanting('epaper')],
                    ['title' => 'आज का ई-पेपर: ' . $i['name'], 'body' => hindi_date((string) $i['issue_date']), 'url' => route('epaper.issue', ['edition' => $i['slug'], 'date' => $i['issue_date']])]);
            }
        });
        self::safe(static function () {
            $key = 'scheduler.expiry.' . date('Y-m-d');
            if (cache()->get($key)) {
                return;
            }
            cache()->set($key, 1, 86400);
            $admins = NotificationService::usersWith('reporters.manage');
            foreach (db()->all("SELECT r.user_id, r.reporter_code, r.valid_until, r.mobile, u.name FROM {p}reporters r JOIN {p}users u ON u.id = r.user_id
                    WHERE r.status = 'active' AND r.valid_until IN (CURDATE() + INTERVAL 7 DAY, CURDATE() + INTERVAL 1 DAY)") as $r) {
                NotificationService::notify('document_expiry', ['users' => array_values(array_unique([...$admins, (int) $r['user_id']])), 'sms' => [$r['mobile']], 'whatsapp' => [$r['mobile']]],
                    ['title' => 'रिपोर्टर ID ' . $r['reporter_code'] . ' (' . $r['name'] . ') की वैधता ' . hindi_date((string) $r['valid_until']) . ' को ख़त्म होगी', 'body' => 'नवीनीकरण करें।', 'url' => route('admin.reporters.index')]);
            }
        });
    }
}
