<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * चुनाव केंद्र (§43): सीट के नतीजे (आगे/पीछे/जीते अपने आप), पार्टी/गठबंधन टैली, वोट शेयर, पिछले नतीजे,
 * CSV और JSON (API) से डेटा। शुरुआत में हाथ से, बाद में किसी डेटा एजेंसी की API से भी।
 */
final class ElectionService
{
    public const TYPES = ['lok_sabha' => 'लोकसभा', 'vidhan_sabha' => 'विधानसभा', 'bypoll' => 'उपचुनाव', 'local' => 'स्थानीय निकाय'];
    public const STATUSES = ['draft' => ['ड्राफ़्ट', 'secondary'], 'upcoming' => ['आने वाला', 'info'], 'polling' => ['मतदान जारी', 'primary'], 'counting' => ['मतगणना जारी', 'warning'], 'declared' => ['नतीजे घोषित', 'success']];
    public const RESULT = ['awaited' => 'इंतज़ार', 'counting' => 'गिनती जारी', 'declared' => 'घोषित'];
    public const RESERVED = ['gen' => 'सामान्य', 'sc' => 'SC', 'st' => 'ST'];

    public static function url(array $e): string
    {
        return route('elections.show', ['slug' => $e['slug']]);
    }

    public static function house(string $type): string
    {
        return $type === 'lok_sabha' ? 'ls' : ($type === 'local' ? 'local' : 'vs');
    }

    /** किसी सीट के वोटों से नतीजा दोबारा बनाएँ: अगुआ, दूसरा, अंतर, कुल */
    public static function recompute(int $electionId, int $seatId, ?string $status = null, ?array $rounds = null): void
    {
        $c = db()->all('SELECT id, votes FROM {p}election_candidates WHERE election_id = ? AND seat_id = ? AND withdrawn = 0 ORDER BY votes DESC, id', [$electionId, $seatId]);
        $total = array_sum(array_map(static fn($x) => (int) $x['votes'], $c));
        $lead = $c[0] ?? null;
        $run = $c[1] ?? null;
        $hasVotes = $lead && (int) $lead['votes'] > 0;
        $cur = db()->first('SELECT * FROM {p}election_results WHERE election_id = ? AND seat_id = ?', [$electionId, $seatId]);
        $status ??= $cur['status'] ?? ($hasVotes ? 'counting' : 'awaited');
        if ($status === 'awaited' && $hasVotes) {
            $status = 'counting';
        }
        $row = ['status' => $status, 'total_votes' => $total, 'leader_id' => $hasVotes ? (int) $lead['id'] : null, 'runner_id' => $hasVotes && $run ? (int) $run['id'] : null,
            'margin' => $hasVotes ? (int) $lead['votes'] - (int) ($run['votes'] ?? 0) : 0,
            'declared_at' => $status === 'declared' ? ($cur['declared_at'] ?? date('Y-m-d H:i:s')) : null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($rounds !== null) {
            $row['rounds'] = max(0, (int) $rounds[0]);
            $row['total_rounds'] = max($row['rounds'], (int) $rounds[1]);
        }
        if ($cur) {
            db()->update('election_results', $row, 'election_id = ? AND seat_id = ?', [$electionId, $seatId]);
        } else {
            db()->insert('election_results', $row + ['election_id' => $electionId, 'seat_id' => $seatId]);
        }
        self::flush($electionId);
    }

    /** पार्टी टैली: जीते, आगे, कुल, वोट, वोट शेयर (सिर्फ़ जिनके पास सीट/वोट) */
    public static function tally(int $electionId): array
    {
        return cache()->remember('election.tally.' . $electionId, 15, static function () use ($electionId): array {
            $rows = db()->all("SELECT p.id, p.name, p.short_name, p.color, p.alliance,
                    SUM(r.status = 'declared') won, SUM(r.status = 'counting') `leading`
                FROM {p}election_results r JOIN {p}election_candidates c ON c.id = r.leader_id JOIN {p}election_parties p ON p.id = c.party_id
                WHERE r.election_id = ? GROUP BY p.id", [$electionId]);
            $votes = [];
            $all = 0;
            foreach (db()->all('SELECT c.party_id, SUM(c.votes) v FROM {p}election_candidates c WHERE c.election_id = ? AND c.withdrawn = 0 GROUP BY c.party_id', [$electionId]) as $v) {
                $votes[(int) $v['party_id']] = (int) $v['v'];
                $all += (int) $v['v'];
            }
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r['id']] = $r + ['total' => (int) $r['won'] + (int) $r['leading']];
            }
            // वोट हैं पर कोई सीट नहीं: वोट शेयर के लिए
            foreach ($votes as $pid => $v) {
                if ($pid && !isset($out[$pid]) && $v > 0 && ($p = db()->first('SELECT id, name, short_name, color, alliance FROM {p}election_parties WHERE id = ?', [$pid]))) {
                    $out[$pid] = $p + ['won' => 0, 'leading' => 0, 'total' => 0];
                }
            }
            foreach ($out as $pid => &$r) {
                $r['won'] = (int) $r['won'];
                $r['leading'] = (int) $r['leading'];
                $r['votes'] = $votes[$pid] ?? 0;
                $r['share'] = $all ? round(($votes[$pid] ?? 0) * 100 / $all, 2) : 0;
            }
            unset($r);
            usort($out, static fn($a, $b) => [$b['total'], $b['won'], $b['votes']] <=> [$a['total'], $a['won'], $a['votes']]);
            return $out;
        });
    }

    /** गठबंधन टैली (पार्टियों का जोड़; बिना गठबंधन = "अन्य") */
    public static function alliances(array $tally): array
    {
        $out = [];
        foreach ($tally as $p) {
            $k = $p['alliance'] ?: 'अन्य';
            $out[$k] ??= ['name' => $k, 'won' => 0, 'leading' => 0, 'total' => 0, 'votes' => 0, 'share' => 0.0, 'color' => $p['alliance'] ? $p['color'] : '#8a8a8a'];
            foreach (['won', 'leading', 'total', 'votes'] as $f) {
                $out[$k][$f] += $p[$f];
            }
            $out[$k]['share'] = round($out[$k]['share'] + $p['share'], 2);
        }
        uasort($out, static fn($a, $b) => $b['total'] <=> $a['total']);
        return array_values($out);
    }

    /** गिनती का हाल: कुल सीटें, जिनके रुझान आए, घोषित */
    public static function progress(array $e): array
    {
        $r = db()->first("SELECT COUNT(*) seats, SUM(status = 'counting') counting, SUM(status = 'declared') declared, SUM(IF(electors > 0, total_votes, 0)) votes, SUM(IF(electors > 0, electors, 0)) electors FROM {p}election_results WHERE election_id = ?", [$e['id']]);
        $seats = max((int) $e['total_seats'], (int) $r['seats']);
        return ['seats' => $seats, 'counting' => (int) $r['counting'], 'declared' => (int) $r['declared'], 'trends' => (int) $r['counting'] + (int) $r['declared'],
            'turnout' => $r['electors'] ? round($r['votes'] * 100 / $r['electors'], 1) : null];
    }

    /** सीटों की सूची: अगुआ और दूसरे नंबर का उम्मीदवार, पार्टी, अंतर */
    public static function seats(int $electionId, array $f = [], int $limit = 1000): array
    {
        $w = ['r.election_id = ?'];
        $p = [$electionId];
        if (!empty($f['q'])) {
            $w[] = '(s.name LIKE ? OR lc.name LIKE ? OR rc.name LIKE ?)';
            array_push($p, '%' . $f['q'] . '%', '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        if (!empty($f['party'])) {
            $w[] = 'lc.party_id = ?';
            $p[] = (int) $f['party'];
        }
        if (!empty($f['district'])) {
            $w[] = 's.district_id = ?';
            $p[] = (int) $f['district'];
        }
        if (!empty($f['status']) && isset(self::RESULT[$f['status']])) {
            $w[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        return db()->all('SELECT s.id seat_id, s.name seat, s.slug, s.number, s.reserved, d.name district, r.status, r.margin, r.total_votes, r.rounds, r.total_rounds, r.updated_at,
                lc.name leader, lc.votes leader_votes, lp.short_name leader_party, lp.color leader_color,
                rc.name runner, rp.short_name runner_party, rp.color runner_color
            FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id LEFT JOIN {p}locations d ON d.id = s.district_id
            LEFT JOIN {p}election_candidates lc ON lc.id = r.leader_id LEFT JOIN {p}election_parties lp ON lp.id = lc.party_id
            LEFT JOIN {p}election_candidates rc ON rc.id = r.runner_id LEFT JOIN {p}election_parties rp ON rp.id = rc.party_id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY s.number IS NULL, s.number, s.name LIMIT ' . max(1, $limit), $p);
    }

    /** एक सीट के उम्मीदवार, वोट शेयर के साथ */
    public static function candidates(int $electionId, int $seatId): array
    {
        $rows = db()->all('SELECT c.*, p.name party, p.short_name party_short, p.color party_color, p.symbol party_symbol FROM {p}election_candidates c
            LEFT JOIN {p}election_parties p ON p.id = c.party_id WHERE c.election_id = ? AND c.seat_id = ? ORDER BY c.withdrawn, c.votes DESC, c.sort_order, c.name', [$electionId, $seatId]);
        $total = array_sum(array_map(static fn($c) => $c['withdrawn'] ? 0 : (int) $c['votes'], $rows));
        foreach ($rows as &$c) {
            $c['share'] = $total ? round($c['votes'] * 100 / $total, 2) : 0;
        }
        unset($c);
        return $rows;
    }

    /** उसी सीट के पिछले चुनाव (घोषित) */
    public static function history(int $seatId, int $exceptElection): array
    {
        return db()->all("SELECT e.name election, e.slug, e.year, r.margin, r.total_votes, lc.name winner, lp.short_name winner_party, lp.color winner_color, lc.votes winner_votes,
                rc.name runner, rp.short_name runner_party
            FROM {p}election_results r JOIN {p}elections e ON e.id = r.election_id
            LEFT JOIN {p}election_candidates lc ON lc.id = r.leader_id LEFT JOIN {p}election_parties lp ON lp.id = lc.party_id
            LEFT JOIN {p}election_candidates rc ON rc.id = r.runner_id LEFT JOIN {p}election_parties rp ON rp.id = rc.party_id
            WHERE r.seat_id = ? AND r.election_id <> ? AND r.status = 'declared' AND e.status <> 'draft' ORDER BY e.year DESC LIMIT 10", [$seatId, $exceptElection]);
    }

    /** बड़े चेहरे */
    public static function keyCandidates(int $electionId, int $limit = 8): array
    {
        return db()->all('SELECT c.id, c.name, c.photo, c.votes, s.name seat, s.slug seat_slug, p.short_name party, p.color, r.status, r.leader_id, r.margin
            FROM {p}election_candidates c JOIN {p}election_seats s ON s.id = c.seat_id LEFT JOIN {p}election_parties p ON p.id = c.party_id
            LEFT JOIN {p}election_results r ON r.election_id = c.election_id AND r.seat_id = c.seat_id
            WHERE c.election_id = ? AND c.is_key = 1 ORDER BY c.sort_order, c.name LIMIT ' . max(1, $limit), [$electionId]);
    }

    /** सीट पक्की करें (ज़रूरत हो तो बनाएँ) और परिणाम-पंक्ति जोड़ें */
    public static function attachSeat(array $e, array $seat): int
    {
        $house = self::house($e['type']);
        $slug = \App\Helpers\Str::slug((string) ($seat['slug'] ?? '') ?: (string) $seat['name'], 120) ?: 'seat-' . (int) ($seat['number'] ?? 0);
        if ($slug === 'live') {
            $slug = 'live-seat'; // /elections/{slug}/live लाइव JSON का पता है
        }
        $id = (int) db()->value('SELECT id FROM {p}election_seats WHERE house = ? AND (state_id <=> ?) AND (slug = ? OR (number IS NOT NULL AND number = ?))',
            [$house, $e['state_id'], $slug, $seat['number'] ?? -1]);
        if (!$id) {
            $id = db()->insert('election_seats', ['name' => mb_substr((string) $seat['name'], 0, 150), 'slug' => $slug, 'number' => $seat['number'] ?? null, 'house' => $house,
                'state_id' => $e['state_id'], 'district_id' => $seat['district_id'] ?? null, 'reserved' => $seat['reserved'] ?? 'gen']);
        }
        if (!db()->value('SELECT 1 FROM {p}election_results WHERE election_id = ? AND seat_id = ?', [$e['id'], $id])) {
            db()->insert('election_results', ['election_id' => $e['id'], 'seat_id' => $id, 'status' => 'awaited', 'updated_at' => date('Y-m-d H:i:s')]);
        }
        return $id;
    }

    /** पार्टी को छोटे नाम/स्लग से */
    public static function partyId(?string $ref): ?int
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        return (int) db()->value('SELECT id FROM {p}election_parties WHERE short_name = ? OR slug = ? OR name = ? LIMIT 1', [$ref, strtolower($ref), $ref]) ?: null;
    }

    /**
     * CSV इम्पोर्ट: seat_no, seat, district, reserved, candidate, party, age, gender, education, incumbent, key
     * (एक पंक्ति = एक उम्मीदवार; सिर्फ़ सीट लिखें और candidate ख़ाली छोड़ें तो सिर्फ़ सीट बनेगी)
     */
    public static function importCsv(array $e, string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r?\n/', ltrim($csv, "\xEF\xBB\xBF")) ?: [], static fn($l) => trim($l) !== ''));
        $made = ['seats' => 0, 'candidates' => 0, 'errors' => []];
        if (!$lines) {
            return $made;
        }
        $head = array_map(static fn($h) => strtolower(trim($h)), str_getcsv($lines[0], ',', '"', '\\'));
        $hasHead = in_array('seat', $head, true) || in_array('candidate', $head, true);
        $cols = $hasHead ? $head : ['seat_no', 'seat', 'district', 'reserved', 'candidate', 'party', 'age', 'gender', 'education', 'incumbent', 'key'];
        $seatsBefore = (int) db()->value('SELECT COUNT(*) FROM {p}election_results WHERE election_id = ?', [$e['id']]);
        foreach (array_slice($lines, $hasHead ? 1 : 0, 5000) as $i => $line) {
            $row = array_combine($cols, array_pad(array_slice(str_getcsv($line, ',', '"', '\\'), 0, count($cols)), count($cols), ''));
            $row = array_map(static fn($v) => trim(strip_tags((string) $v)), $row);
            $n = $i + ($hasHead ? 2 : 1);
            if (($row['seat'] ?? '') === '') {
                $made['errors'][] = "पंक्ति $n: सीट का नाम नहीं";
                continue;
            }
            $district = ($row['district'] ?? '') !== '' ? (int) db()->value("SELECT id FROM {p}locations WHERE (name = ? OR name_en = ?) AND type = 'district' LIMIT 1", [$row['district'], $row['district']]) : 0;
            $seatId = self::attachSeat($e, ['name' => $row['seat'], 'number' => ctype_digit($row['seat_no'] ?? '') ? (int) $row['seat_no'] : null,
                'district_id' => $district ?: null, 'reserved' => in_array(strtolower($row['reserved'] ?? ''), ['sc', 'st'], true) ? strtolower($row['reserved']) : 'gen']);
            if (($row['candidate'] ?? '') === '') {
                continue;
            }
            $party = self::partyId($row['party'] ?? '');
            if (($row['party'] ?? '') !== '' && !$party) {
                $made['errors'][] = "पंक्ति $n: पार्टी “{$row['party']}” नहीं मिली (पहले पार्टी जोड़ें), उम्मीदवार निर्दलीय माना गया";
            }
            $exists = db()->value('SELECT id FROM {p}election_candidates WHERE election_id = ? AND seat_id = ? AND name = ?', [$e['id'], $seatId, $row['candidate']]);
            $data = ['party_id' => $party, 'age' => ctype_digit($row['age'] ?? '') && (int) $row['age'] >= 18 && (int) $row['age'] < 120 ? (int) $row['age'] : null,
                'gender' => in_array(strtolower($row['gender'] ?? ''), ['m', 'f', 'o'], true) ? strtolower($row['gender']) : null,
                'education' => mb_substr($row['education'] ?? '', 0, 120) ?: null, 'is_incumbent' => in_array(strtolower($row['incumbent'] ?? ''), ['1', 'yes', 'हाँ', 'y'], true) ? 1 : 0,
                'is_key' => in_array(strtolower($row['key'] ?? ''), ['1', 'yes', 'हाँ', 'y'], true) ? 1 : 0];
            if ($exists) {
                db()->update('election_candidates', $data, 'id = ?', [$exists]);
            } else {
                db()->insert('election_candidates', $data + ['election_id' => $e['id'], 'seat_id' => $seatId, 'name' => mb_substr($row['candidate'], 0, 150)]);
                $made['candidates']++;
            }
        }
        $made['seats'] = (int) db()->value('SELECT COUNT(*) FROM {p}election_results WHERE election_id = ?', [$e['id']]) - $seatsBefore;
        self::syncSeatCount($e);
        self::flush((int) $e['id']);
        return $made;
    }

    /**
     * JSON (API/अपलोड) से नतीजे:
     * {"seats":[{"seat":"12" | "gorakhpur-urban", "status":"counting|declared", "rounds":5, "total_rounds":20, "electors":350000,
     *            "candidates":[{"id":45, "votes":1234} | {"name":"…", "party":"BJP", "votes":1234}]}]}
     * @return array{updated:int, errors:list<string>}
     */
    public static function applyResults(array $e, array $payload): array
    {
        $out = ['updated' => 0, 'errors' => []];
        foreach (array_slice((array) ($payload['seats'] ?? []), 0, 2000) as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            $ref = trim((string) ($s['seat'] ?? ''));
            $seat = db()->first('SELECT s.id FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id WHERE r.election_id = ? AND (s.slug = ? OR (s.number IS NOT NULL AND s.number = ?))',
                [$e['id'], $ref, ctype_digit($ref) ? (int) $ref : -1]);
            if (!$seat) {
                $out['errors'][] = "seats[$i]: सीट “{$ref}” इस चुनाव में नहीं";
                continue;
            }
            $sid = (int) $seat['id'];
            foreach (array_slice((array) ($s['candidates'] ?? []), 0, 100) as $j => $c) {
                if (!is_array($c) || !isset($c['votes']) || filter_var($c['votes'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50000000]]) === false) {
                    $out['errors'][] = "seats[$i].candidates[$j]: votes 0 या उससे बड़ी पूर्ण संख्या हो";
                    continue;
                }
                $cid = isset($c['id']) ? (int) db()->value('SELECT id FROM {p}election_candidates WHERE id = ? AND election_id = ? AND seat_id = ?', [(int) $c['id'], $e['id'], $sid])
                    : (int) db()->value('SELECT id FROM {p}election_candidates WHERE election_id = ? AND seat_id = ? AND name = ?', [$e['id'], $sid, trim((string) ($c['name'] ?? ''))]);
                if (!$cid && !isset($c['id']) && trim((string) ($c['name'] ?? '')) !== '') {
                    $cid = db()->insert('election_candidates', ['election_id' => $e['id'], 'seat_id' => $sid, 'name' => mb_substr(trim(strip_tags((string) $c['name'])), 0, 150), 'party_id' => self::partyId($c['party'] ?? null)]);
                }
                if (!$cid) {
                    $out['errors'][] = "seats[$i].candidates[$j]: उम्मीदवार नहीं मिला";
                    continue;
                }
                db()->update('election_candidates', ['votes' => (int) $c['votes'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$cid]);
            }
            $status = isset($s['status']) && isset(self::RESULT[$s['status']]) ? (string) $s['status'] : null;
            if (isset($s['electors']) && ctype_digit((string) $s['electors'])) {
                db()->update('election_results', ['electors' => (int) $s['electors']], 'election_id = ? AND seat_id = ?', [$e['id'], $sid]);
            }
            $rounds = isset($s['rounds']) ? [(int) $s['rounds'], (int) ($s['total_rounds'] ?? $s['rounds'])] : null;
            self::recompute((int) $e['id'], $sid, $status, $rounds);
            $out['updated']++;
        }
        db()->update('elections', ['synced_at' => date('Y-m-d H:i:s')], 'id = ?', [$e['id']]);
        return $out;
    }

    public static function syncSeatCount(array $e): void
    {
        $n = (int) db()->value('SELECT COUNT(*) FROM {p}election_results WHERE election_id = ?', [$e['id']]);
        if ($n > (int) $e['total_seats']) {
            db()->update('elections', ['total_seats' => $n, 'majority' => $e['majority'] ?: intdiv($n, 2) + 1], 'id = ?', [$e['id']]);
        }
    }

    /** लाइव JSON (वेबसाइट का ऑटो-रिफ़्रेश और बाहर के विजेट) */
    public static function live(array $e): array
    {
        $tally = self::tally((int) $e['id']);
        return ['election' => ['name' => $e['name'], 'status' => $e['status'], 'majority' => (int) $e['majority']], 'progress' => self::progress($e),
            'parties' => array_map(static fn($p) => ['party' => $p['short_name'], 'name' => $p['name'], 'color' => $p['color'], 'won' => $p['won'], 'leading' => $p['leading'], 'total' => $p['total'], 'share' => $p['share']], $tally),
            'alliances' => self::alliances($tally), 'updated' => date('c')];
    }

    public static function flush(int $electionId): void
    {
        cache()->forget('election.tally.' . $electionId);
        cache()->flush('home');
    }

    public static function featured(): ?array
    {
        return db()->first("SELECT * FROM {p}elections WHERE status IN ('counting','declared','polling','upcoming') ORDER BY is_featured DESC, FIELD(status, 'counting', 'polling', 'declared', 'upcoming'), updated_at DESC LIMIT 1");
    }
}
