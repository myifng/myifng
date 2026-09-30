<?php
declare(strict_types=1);

namespace App\Services;

/**
 * न्यूज़रूम प्रदर्शन (§63): ख़बरें भेजी/मंज़ूर/अस्वीकार/बाकी, मंज़ूरी का औसत समय, रिपोर्टर का आउटपुट,
 * एडिटर का वर्कलोड, ब्रेकिंग का प्रदर्शन, असाइनमेंट पूरे होने की दर।
 * स्रोत: news_remarks (हर स्थिति-बदलाव दर्ज), news, assignments, breaking_news, analytics_daily
 */
final class NewsroomReport
{
    public const REVIEW = ['submitted', 'review', 'fact_check'];
    public const APPROVED = ['approved', 'scheduled', 'published'];

    /** $userId दें तो सिर्फ़ उस रिपोर्टर की ख़बरें */
    public static function summary(string $from, string $to, ?int $userId = null): array
    {
        [$a, $b] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        [$uw, $up] = $userId ? [' AND n.reporter_id = ?', [$userId]] : ['', []];
        $moves = static fn(string $cond, array $p = []) => (int) db()->value("SELECT COUNT(DISTINCT r.news_id) FROM {p}news_remarks r JOIN {p}news n ON n.id = r.news_id
            WHERE r.type = 'status' AND r.created_at BETWEEN ? AND ? AND $cond$uw", [$a, $b, ...$p, ...$up]);
        $in = static fn(array $s) => "'" . implode("','", $s) . "'";
        $approval = self::approvalTimes($from, $to, $userId);
        return [
            'submitted' => $moves("r.to_status = 'submitted'"),
            'approved' => $moves('r.to_status IN (' . $in(self::APPROVED) . ') AND r.from_status IN (' . $in(self::REVIEW) . ')'),
            'rejected' => $moves("r.to_status = 'rejected'"),
            'published' => (int) db()->value("SELECT COUNT(*) FROM {p}news n WHERE n.status IN ('published','archived','disabled') AND n.deleted_at IS NULL AND n.published_at BETWEEN ? AND ?$uw", [$a, $b, ...$up]),
            'direct' => $moves("r.to_status = 'published' AND r.from_status = 'draft'"),
            'pending' => (int) db()->value('SELECT COUNT(*) FROM {p}news n WHERE n.deleted_at IS NULL AND n.status IN (' . $in(self::REVIEW) . ")$uw", $up),
            'pending_old' => (int) db()->value('SELECT COUNT(*) FROM {p}news n WHERE n.deleted_at IS NULL AND n.status IN (' . $in(self::REVIEW) . ") AND n.updated_at < NOW() - INTERVAL 24 HOUR$uw", $up),
            'avg_hours' => $approval ? round(array_sum($approval) / count($approval), 1) : null,
            'median_hours' => $approval ? self::median($approval) : null,
            'approval_n' => count($approval),
        ];
    }

    /** हर मंज़ूरी में लगा समय (घंटे): आख़िरी "भेजी गई" से मंज़ूर/प्रकाशित तक */
    public static function approvalTimes(string $from, string $to, ?int $userId = null, ?int $editorId = null): array
    {
        $in = "'" . implode("','", self::APPROVED) . "'";
        $rv = "'" . implode("','", self::REVIEW) . "'";
        $rows = db()->all("SELECT r.news_id, r.created_at done_at,
                (SELECT MAX(s.created_at) FROM {p}news_remarks s WHERE s.news_id = r.news_id AND s.type = 'status' AND s.to_status = 'submitted' AND s.created_at <= r.created_at) sent_at
            FROM {p}news_remarks r JOIN {p}news n ON n.id = r.news_id
            WHERE r.type = 'status' AND r.to_status IN ($in) AND r.from_status IN ($rv) AND r.created_at BETWEEN ? AND ?"
            . ($userId ? ' AND n.reporter_id = ?' : '') . ($editorId ? ' AND r.user_id = ?' : ''),
            [$from . ' 00:00:00', $to . ' 23:59:59', ...($userId ? [$userId] : []), ...($editorId ? [$editorId] : [])]);
        $out = [];
        foreach ($rows as $r) {
            if ($r['sent_at']) {
                $out[] = max(0, (strtotime($r['done_at']) - strtotime($r['sent_at'])) / 3600);
            }
        }
        return $out;
    }

    /** रोज़: भेजी और प्रकाशित */
    public static function daily(string $from, string $to): array
    {
        $sent = [];
        foreach (db()->all("SELECT DATE(created_at) d, COUNT(DISTINCT news_id) c FROM {p}news_remarks WHERE type = 'status' AND to_status = 'submitted' AND created_at BETWEEN ? AND ? GROUP BY d", [$from . ' 00:00:00', $to . ' 23:59:59']) as $r) {
            $sent[$r['d']] = (int) $r['c'];
        }
        $pub = [];
        foreach (db()->all("SELECT DATE(published_at) d, COUNT(*) c FROM {p}news WHERE deleted_at IS NULL AND status IN ('published','archived','disabled') AND published_at BETWEEN ? AND ? GROUP BY d", [$from . ' 00:00:00', $to . ' 23:59:59']) as $r) {
            $pub[$r['d']] = (int) $r['c'];
        }
        $out = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
            $d = date('Y-m-d', $t);
            $out[] = ['day' => $d, 'sent' => $sent[$d] ?? 0, 'published' => $pub[$d] ?? 0];
        }
        return $out;
    }

    /** रिपोर्टर का आउटपुट: भेजी, प्रकाशित, अस्वीकार, व्यू (इस अवधि के), औसत शब्द */
    public static function reporters(string $from, string $to, int $limit = 200): array
    {
        [$a, $b] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $rows = db()->all("SELECT u.id, u.name,
                (SELECT COUNT(DISTINCT r.news_id) FROM {p}news_remarks r JOIN {p}news n ON n.id = r.news_id WHERE n.reporter_id = u.id AND r.type = 'status' AND r.to_status = 'submitted' AND r.created_at BETWEEN ? AND ?) submitted,
                (SELECT COUNT(*) FROM {p}news n WHERE n.reporter_id = u.id AND n.deleted_at IS NULL AND n.status IN ('published','archived','disabled') AND n.published_at BETWEEN ? AND ?) published,
                (SELECT COUNT(DISTINCT r.news_id) FROM {p}news_remarks r JOIN {p}news n ON n.id = r.news_id WHERE n.reporter_id = u.id AND r.type = 'status' AND r.to_status = 'rejected' AND r.created_at BETWEEN ? AND ?) rejected,
                (SELECT ROUND(AVG(n.word_count)) FROM {p}news n WHERE n.reporter_id = u.id AND n.deleted_at IS NULL AND n.published_at BETWEEN ? AND ?) words,
                (SELECT COUNT(*) FROM {p}news n WHERE n.reporter_id = u.id AND n.deleted_at IS NULL AND n.status IN ('submitted','review','fact_check')) pending,
                (SELECT COALESCE(SUM(d.views), 0) FROM {p}analytics_daily d WHERE d.dim = 'reporter' AND d.dim_key = u.id AND d.day BETWEEN ? AND ?) views
            FROM {p}users u WHERE u.id IN (SELECT DISTINCT reporter_id FROM {p}news WHERE reporter_id IS NOT NULL AND deleted_at IS NULL)
            ORDER BY published DESC, submitted DESC, u.name LIMIT " . max(1, $limit), [$a, $b, $a, $b, $a, $b, $a, $b, $from, $to]);
        return array_values(array_filter($rows, static fn($r) => $r['submitted'] || $r['published'] || $r['rejected'] || $r['pending'] || $r['views']));
    }

    /** एडिटर का काम: किसने कितनी ख़बरें मंज़ूर/अस्वीकार/प्रकाशित कीं, औसत समय, अभी उनके पास बाकी */
    public static function editors(string $from, string $to): array
    {
        [$a, $b] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $in = "'" . implode("','", self::REVIEW) . "'";
        $rows = db()->all("SELECT u.id, u.name, COUNT(*) actions,
                COUNT(DISTINCT IF(r.to_status = 'approved', r.news_id, NULL)) approved,
                COUNT(DISTINCT IF(r.to_status = 'rejected', r.news_id, NULL)) rejected,
                COUNT(DISTINCT IF(r.to_status = 'published', r.news_id, NULL)) published,
                COUNT(DISTINCT IF(r.to_status IN ('review','fact_check'), r.news_id, NULL)) reviewing
            FROM {p}news_remarks r JOIN {p}users u ON u.id = r.user_id JOIN {p}news n ON n.id = r.news_id
            WHERE r.type = 'status' AND r.created_at BETWEEN ? AND ? AND (r.from_status IN ($in) OR r.to_status IN ('published','scheduled')) AND (n.reporter_id IS NULL OR n.reporter_id <> r.user_id)
            GROUP BY u.id ORDER BY actions DESC", [$a, $b]);
        foreach ($rows as &$r) {
            $t = self::approvalTimes($from, $to, null, (int) $r['id']);
            $r['avg_hours'] = $t ? round(array_sum($t) / count($t), 1) : null;
            $r['queue'] = (int) db()->value("SELECT COUNT(*) FROM {p}news WHERE editor_id = ? AND deleted_at IS NULL AND status IN ($in)", [$r['id']]);
        }
        unset($r);
        return $rows;
    }

    /** ब्रेकिंग: कितनी, पुश, जुड़ी ख़बर के व्यू/शेयर */
    public static function breaking(string $from, string $to): array
    {
        [$a, $b] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $items = db()->all("SELECT b.id, b.title, b.type, b.push, b.push_status, b.created_at, b.news_id, n.title news_title,
                (SELECT COALESCE(SUM(d.views), 0) FROM {p}analytics_daily d WHERE d.dim = 'news' AND d.dim_key = b.news_id AND d.day >= DATE(b.created_at)) views,
                n.views total_views, n.shares
            FROM {p}breaking_news b LEFT JOIN {p}news n ON n.id = b.news_id WHERE b.created_at BETWEEN ? AND ? ORDER BY b.created_at DESC LIMIT 100", [$a, $b]);
        $flagged = (int) db()->value("SELECT COUNT(*) FROM {p}news WHERE is_breaking = 1 AND deleted_at IS NULL AND published_at BETWEEN ? AND ?", [$a, $b]);
        $bv = db()->first("SELECT COALESCE(SUM(d.views),0) v, COUNT(DISTINCT n.id) c FROM {p}news n JOIN {p}analytics_daily d ON d.dim = 'news' AND d.dim_key = n.id AND d.day BETWEEN ? AND ?
            WHERE n.is_breaking = 1 AND n.published_at BETWEEN ? AND ?", [$from, $to, $a, $b]);
        $all = db()->first("SELECT COALESCE(SUM(d.views),0) v, COUNT(DISTINCT n.id) c FROM {p}news n JOIN {p}analytics_daily d ON d.dim = 'news' AND d.dim_key = n.id AND d.day BETWEEN ? AND ?
            WHERE n.published_at BETWEEN ? AND ?", [$from, $to, $a, $b]);
        return ['items' => $items, 'count' => count($items), 'pushed' => count(array_filter($items, static fn($i) => $i['push_status'] === 'sent')),
            'flagged' => $flagged, 'avg_views' => $bv['c'] ? round($bv['v'] / $bv['c']) : 0, 'avg_all' => $all['c'] ? round($all['v'] / $all['c']) : 0];
    }

    /** असाइनमेंट: बने, पूरे, रद्द, समय पर, देर से, बाकी/ओवरड्यू; रिपोर्टर के हिसाब से */
    public static function assignments(string $from, string $to, ?int $userId = null): array
    {
        [$a, $b] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        [$uw, $up] = $userId ? [' AND a.reporter_id = ?', [$userId]] : ['', []];
        $t = db()->first("SELECT COUNT(*) total, SUM(a.status = 'completed') completed, SUM(a.status = 'cancelled') cancelled,
                SUM(a.status NOT IN ('completed','cancelled')) open,
                SUM(a.status NOT IN ('completed','cancelled') AND a.deadline IS NOT NULL AND a.deadline < NOW()) overdue,
                SUM(a.status = 'completed' AND (a.deadline IS NULL OR a.updated_at <= a.deadline)) ontime
            FROM {p}assignments a WHERE a.created_at BETWEEN ? AND ?$uw", [$a, $b, ...$up]);
        $t = array_map('intval', $t ?? []);
        $den = max(0, ($t['total'] ?? 0) - ($t['cancelled'] ?? 0));
        $t['rate'] = $den ? round(($t['completed'] ?? 0) * 100 / $den) : null;
        $t['ontime_rate'] = !empty($t['completed']) ? round($t['ontime'] * 100 / $t['completed']) : null;
        $by = $userId ? [] : db()->all("SELECT u.id, u.name, COUNT(*) total, SUM(a.status = 'completed') completed,
                SUM(a.status NOT IN ('completed','cancelled') AND a.deadline IS NOT NULL AND a.deadline < NOW()) overdue, SUM(a.status NOT IN ('completed','cancelled')) open
            FROM {p}assignments a JOIN {p}users u ON u.id = a.reporter_id WHERE a.created_at BETWEEN ? AND ? GROUP BY u.id ORDER BY total DESC LIMIT 50", [$a, $b]);
        return $t + ['by' => $by];
    }

    /** अभी समीक्षा में अटकी ख़बरें (सबसे पुरानी पहले) */
    public static function queue(int $limit = 10): array
    {
        return db()->all("SELECT n.id, n.title, n.status, n.updated_at, u.name reporter, e.name editor FROM {p}news n LEFT JOIN {p}users u ON u.id = n.reporter_id LEFT JOIN {p}users e ON e.id = n.editor_id
            WHERE n.deleted_at IS NULL AND n.status IN ('submitted','review','fact_check') ORDER BY n.updated_at LIMIT " . max(1, $limit));
    }

    public static function hours(?float $h): string
    {
        if ($h === null) {
            return '—';
        }
        return $h < 1 ? round($h * 60) . ' मिनट' : ($h < 48 ? round($h, 1) . ' घंटे' : round($h / 24, 1) . ' दिन');
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2, 1);
    }
}
