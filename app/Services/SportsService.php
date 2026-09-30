<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Str;

/**
 * खेल केंद्र (§44): टूर्नामेंट, टीमें, खिलाड़ी, मैच (फ़िक्स्चर/नतीजे), लाइव कमेंट्री, पॉइंट्स टेबल।
 * शुरुआत में हाथ से; बाद में API (Api\DataController) से स्कोर/कमेंट्री।
 */
final class SportsService
{
    public const SPORTS = ['cricket' => ['क्रिकेट', 'fa-baseball-bat-ball'], 'football' => ['फ़ुटबॉल', 'fa-futbol'], 'hockey' => ['हॉकी', 'fa-hockey-puck'],
        'kabaddi' => ['कबड्डी', 'fa-people-pulling'], 'other' => ['अन्य', 'fa-trophy']];
    public const MATCH_STATUSES = ['scheduled' => ['आने वाला', 'info'], 'live' => ['लाइव', 'danger'], 'break' => ['ब्रेक', 'warning'], 'completed' => ['पूरा', 'success'],
        'abandoned' => ['रद्द', 'secondary'], 'postponed' => ['स्थगित', 'secondary']];
    public const TOUR_STATUSES = ['draft' => 'ड्राफ़्ट', 'upcoming' => 'आने वाला', 'live' => 'जारी', 'completed' => 'पूरा'];
    /** कमेंट्री के प्रकार => [लेबल, class] */
    public const COMM = ['info' => ['सामान्य', ''], 'four' => ['चौका', 'c-four'], 'six' => ['छक्का', 'c-six'], 'wicket' => ['विकेट', 'c-wicket'], 'goal' => ['गोल', 'c-goal'],
        'yellow' => ['पीला कार्ड', 'c-yellow'], 'red' => ['लाल कार्ड', 'c-red'], 'sub' => ['बदलाव', ''], 'highlight' => ['अहम', 'c-hl']];

    public const MATCH_COLS = 'm.*, t.name tournament, t.slug tournament_slug, a.name team1, a.short_name team1_short, a.logo team1_logo, a.color team1_color, a.slug team1_slug,
        b.name team2, b.short_name team2_short, b.logo team2_logo, b.color team2_color, b.slug team2_slug';
    public const MATCH_FROM = '{p}sports_matches m LEFT JOIN {p}sports_tournaments t ON t.id = m.tournament_id LEFT JOIN {p}sports_teams a ON a.id = m.team1_id LEFT JOIN {p}sports_teams b ON b.id = m.team2_id';
    /** वेबसाइट पर दिखने लायक: टूर्नामेंट ड्राफ़्ट न हो */
    public const VISIBLE = "(t.id IS NULL OR t.status <> 'draft')";

    public static function url(array $m): string
    {
        if (!array_key_exists('team1', $m)) {
            $m = self::match((int) $m['id']) ?? $m;
        }
        $slug = Str::slug(trim(($m['team1_short'] ?? $m['team1'] ?? '') . '-vs-' . ($m['team2_short'] ?? $m['team2'] ?? '')), 80) ?: 'match';
        return route('sports.match', ['id' => $m['id'], 'slug' => $slug]);
    }

    public static function match(int $id): ?array
    {
        return db()->first('SELECT ' . self::MATCH_COLS . ' FROM ' . self::MATCH_FROM . ' WHERE m.id = ?', [$id]);
    }

    public static function title(array $m): string
    {
        return trim(($m['team1'] ?? 'टीम 1') . ' बनाम ' . ($m['team2'] ?? 'टीम 2') . ($m['title'] ? ', ' . $m['title'] : ''));
    }

    /** लाइव + ब्रेक */
    public static function live(int $limit = 10, ?int $tournamentId = null): array
    {
        return self::list("m.status IN ('live','break')", [], 'm.start_at', $limit, $tournamentId);
    }

    public static function upcoming(int $limit = 10, ?int $tournamentId = null): array
    {
        return self::list("m.status IN ('scheduled','postponed') AND m.start_at >= NOW() - INTERVAL 6 HOUR", [], 'm.start_at', $limit, $tournamentId);
    }

    public static function recent(int $limit = 10, ?int $tournamentId = null): array
    {
        return self::list("m.status IN ('completed','abandoned')", [], 'm.start_at DESC', $limit, $tournamentId);
    }

    public static function list(string $where, array $params, string $order, int $limit, ?int $tournamentId = null, ?int $teamId = null): array
    {
        if ($tournamentId) {
            $where .= ' AND m.tournament_id = ?';
            $params[] = $tournamentId;
        }
        if ($teamId) {
            $where .= ' AND (m.team1_id = ? OR m.team2_id = ?)';
            array_push($params, $teamId, $teamId);
        }
        return db()->all('SELECT ' . self::MATCH_COLS . ' FROM ' . self::MATCH_FROM . ' WHERE ' . self::VISIBLE . " AND $where ORDER BY $order LIMIT " . max(1, min(200, $limit)), $params);
    }

    public static function standings(int $tournamentId): array
    {
        return db()->all('SELECT s.*, t.name, t.short_name, t.logo, t.color, t.slug FROM {p}sports_standings s JOIN {p}sports_teams t ON t.id = s.team_id
            WHERE s.tournament_id = ? ORDER BY s.group_name, s.points DESC, s.nrr IS NULL, s.nrr DESC, s.gd DESC, s.sort_order, t.name', [$tournamentId]);
    }

    /** पूरे हुए मैचों से खेले/जीते/हारे/ड्रॉ/बेनतीजा/अंक (NRR और गोल-अंतर हाथ से रहते हैं) */
    public static function recalcStandings(array $t): int
    {
        $teams = [];
        $bump = static function (int $team, string $group) use (&$teams): void {
            $teams[$team] ??= ['played' => 0, 'won' => 0, 'lost' => 0, 'drawn' => 0, 'no_result' => 0, 'group' => $group];
        };
        foreach (db()->all("SELECT * FROM {p}sports_matches WHERE tournament_id = ? AND status IN ('completed','abandoned') AND team1_id IS NOT NULL AND team2_id IS NOT NULL", [$t['id']]) as $m) {
            $g = (string) ($m['group_name'] ?? '');
            foreach ([(int) $m['team1_id'], (int) $m['team2_id']] as $tm) {
                $bump($tm, $g);
                $teams[$tm]['played']++;
                if ($m['status'] === 'abandoned') {
                    $teams[$tm]['no_result']++;
                } elseif ($m['is_draw']) {
                    $teams[$tm]['drawn']++;
                } elseif ((int) $m['winner_id'] === $tm) {
                    $teams[$tm]['won']++;
                } elseif ($m['winner_id']) {
                    $teams[$tm]['lost']++;
                }
            }
        }
        foreach ($teams as $tm => $r) {
            $pts = $r['won'] * (int) $t['points_win'] + ($r['drawn'] + $r['no_result']) * (int) $t['points_draw'];
            $data = ['played' => $r['played'], 'won' => $r['won'], 'lost' => $r['lost'], 'drawn' => $r['drawn'], 'no_result' => $r['no_result'], 'points' => $pts];
            if (db()->value('SELECT 1 FROM {p}sports_standings WHERE tournament_id = ? AND team_id = ?', [$t['id'], $tm])) {
                db()->update('sports_standings', $data, 'tournament_id = ? AND team_id = ?', [$t['id'], $tm]);
            } else {
                db()->insert('sports_standings', $data + ['tournament_id' => $t['id'], 'team_id' => $tm, 'group_name' => $r['group']]);
            }
        }
        return count($teams);
    }

    public static function commentary(int $matchId, int $afterId = 0, int $limit = 60): array
    {
        return db()->all('SELECT id, marker, type, text, created_at FROM {p}sports_commentary WHERE match_id = ? AND id > ? ORDER BY id DESC LIMIT ' . max(1, min(300, $limit)), [$matchId, $afterId]);
    }

    public static function addCommentary(int $matchId, string $text, string $type = 'info', ?string $marker = null, ?int $userId = null): ?int
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            return null;
        }
        return db()->insert('sports_commentary', ['match_id' => $matchId, 'text' => mb_substr($text, 0, 1000), 'type' => isset(self::COMM[$type]) ? $type : 'info',
            'marker' => ($marker = trim(strip_tags((string) $marker))) !== '' ? mb_substr($marker, 0, 12) : null, 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * स्कोर/स्थिति/कमेंट्री अपडेट (एडमिन कंसोल और API दोनों):
     * {"status":"live","score1":"186/4 (18.2)","score2":"","status_text":"…","result":"…","winner":"IND" | 3,"toss":"…","potm":"…",
     *  "commentary":[{"marker":"18.2","type":"four","text":"…"}]}
     */
    public static function applyUpdate(array $m, array $in, ?int $userId): array
    {
        $data = [];
        if (isset($in['status'])) {
            if (!isset(self::MATCH_STATUSES[$in['status']])) {
                return ['error' => 'status इनमें से हो: ' . implode(', ', array_keys(self::MATCH_STATUSES))];
            }
            $data['status'] = $in['status'];
        }
        foreach (['score1' => 60, 'score2' => 60, 'status_text' => 190, 'result' => 255, 'toss' => 190, 'potm' => 150] as $k => $max) {
            if (array_key_exists($k, $in)) {
                $v = trim(strip_tags((string) $in[$k]));
                $data[$k] = $v !== '' ? mb_substr($v, 0, $max) : null;
            }
        }
        if (array_key_exists('winner', $in)) {
            $w = $in['winner'];
            $wid = null;
            if ($w !== null && $w !== '' && $w !== 'draw') {
                foreach ([(int) $m['team1_id'], (int) $m['team2_id']] as $tid) {
                    $t = $tid ? db()->first('SELECT id, short_name, slug FROM {p}sports_teams WHERE id = ?', [$tid]) : null;
                    if ($t && ((string) $w === (string) $t['id'] || strcasecmp((string) $w, (string) $t['short_name']) === 0 || $w === $t['slug'])) {
                        $wid = (int) $t['id'];
                    }
                }
                if (!$wid) {
                    return ['error' => 'winner इस मैच की टीम (ID/छोटा नाम) या "draw" हो'];
                }
            }
            $data['winner_id'] = $wid;
            $data['is_draw'] = $w === 'draw' ? 1 : 0;
        }
        if ($data) {
            db()->update('sports_matches', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$m['id']]);
        }
        $added = 0;
        $list = $in['commentary'] ?? [];
        if (is_array($list) && isset($list['text'])) {
            $list = [$list];
        }
        foreach (array_slice(is_array($list) ? $list : [], 0, 50) as $c) {
            if (is_array($c) && self::addCommentary((int) $m['id'], (string) ($c['text'] ?? ''), (string) ($c['type'] ?? 'info'), isset($c['marker']) ? (string) $c['marker'] : null, $userId)) {
                $added++;
            }
        }
        if (($data['status'] ?? null) === 'completed' && $m['tournament_id'] && ($t = db()->first('SELECT * FROM {p}sports_tournaments WHERE id = ?', [$m['tournament_id']]))) {
            self::recalcStandings($t); // नतीजा आते ही पॉइंट्स टेबल
        }
        cache()->flush('home');
        return ['updated' => (bool) $data, 'commentary' => $added];
    }

    /** मैच का लाइव JSON */
    public static function liveJson(array $m, int $after): array
    {
        return ['id' => (int) $m['id'], 'status' => $m['status'], 'status_label' => self::MATCH_STATUSES[$m['status']][0], 'score1' => $m['score1'], 'score2' => $m['score2'],
            'status_text' => $m['status_text'], 'result' => $m['result'], 'commentary' => array_reverse(self::commentary((int) $m['id'], $after, 50)), 'updated' => $m['updated_at']];
    }

    public static function schema(array $m): string
    {
        $s = ['@context' => 'https://schema.org', '@type' => 'SportsEvent', 'name' => self::title($m), 'startDate' => date('c', strtotime((string) $m['start_at'])),
            'eventStatus' => 'https://schema.org/' . (['postponed' => 'EventPostponed', 'abandoned' => 'EventCancelled'][$m['status']] ?? 'EventScheduled'),
            'location' => $m['venue'] ? ['@type' => 'Place', 'name' => $m['venue']] : null, 'sport' => self::SPORTS[$m['sport']][0] ?? null,
            'competitor' => array_values(array_filter([$m['team1'] ? ['@type' => 'SportsTeam', 'name' => $m['team1']] : null, $m['team2'] ? ['@type' => 'SportsTeam', 'name' => $m['team2']] : null])),
            'superEvent' => $m['tournament'] ? ['@type' => 'SportsEvent', 'name' => $m['tournament']] : null];
        return SeoService::json(array_filter($s, static fn($v) => $v !== null));
    }
}
