<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use App\Services\FormService;
use App\Services\SportsService as SS;

/** खेल केंद्र: मैच (लाइव कंसोल), टूर्नामेंट (पॉइंट्स टेबल), टीमें/खिलाड़ी */
final class SportsController extends Controller
{
    // ---------- मैच ----------
    public function index(Request $request): Response
    {
        $tab = in_array($request->str('tab'), ['live', 'upcoming', 'recent', 'all'], true) ? $request->str('tab') : 'live';
        $tour = $request->int('tournament') ?: null;
        $cols = SS::MATCH_COLS;
        $items = match ($tab) {
            'live' => db()->all("SELECT $cols FROM " . SS::MATCH_FROM . " WHERE m.status IN ('live','break')" . ($tour ? ' AND m.tournament_id = ' . $tour : '') . ' ORDER BY m.start_at'),
            'upcoming' => db()->all("SELECT $cols FROM " . SS::MATCH_FROM . " WHERE m.status IN ('scheduled','postponed')" . ($tour ? ' AND m.tournament_id = ' . $tour : '') . ' ORDER BY m.start_at LIMIT 100'),
            'recent' => db()->all("SELECT $cols FROM " . SS::MATCH_FROM . " WHERE m.status IN ('completed','abandoned')" . ($tour ? ' AND m.tournament_id = ' . $tour : '') . ' ORDER BY m.start_at DESC LIMIT 100'),
            default => db()->all("SELECT $cols FROM " . SS::MATCH_FROM . ($tour ? ' WHERE m.tournament_id = ' . $tour : '') . ' ORDER BY m.start_at DESC LIMIT 300'),
        };
        $counts = db()->first("SELECT SUM(status IN ('live','break')) live, SUM(status IN ('scheduled','postponed')) upcoming, SUM(status IN ('completed','abandoned')) recent, COUNT(*) total FROM {p}sports_matches");
        return $this->view('admin/sports/index', ['items' => $items, 'tab' => $tab, 'tour' => $tour, 'counts' => array_map('intval', $counts ?? []),
            'tournaments' => db()->all('SELECT id, name FROM {p}sports_tournaments ORDER BY start_date DESC, id DESC')]);
    }

    public function createMatch(Request $request): Response
    {
        return $this->view('admin/sports/match-form', ['m' => null, 'prefill' => ['tournament_id' => $request->int('tournament') ?: null]] + $this->matchData());
    }

    public function storeMatch(Request $request): Response
    {
        $data = $this->matchPayload($request);
        $id = db()->insert('sports_matches', $data + ['created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        AuditService::log('create', 'sports', $id, 'मैच बनाया');
        return $this->toRoute('admin.sports.console', ['id' => $id])->with('success', 'मैच बन गया। मैच के दिन इसी कंसोल से स्कोर और कमेंट्री डालें।');
    }

    public function editMatch(Request $request, int $id): Response
    {
        return $this->view('admin/sports/match-form', ['m' => $this->findMatch($id), 'prefill' => []] + $this->matchData());
    }

    public function updateMatch(Request $request, int $id): Response
    {
        $m = $this->findMatch($id);
        db()->update('sports_matches', $this->matchPayload($request) + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        AuditService::log('update', 'sports', $id, 'मैच बदला');
        cache()->flush('home');
        return $this->toRoute('admin.sports.console', ['id' => $id])->with('success', 'मैच सेव हो गया।');
    }

    public function destroyMatch(Request $request, int $id): Response
    {
        $this->findMatch($id);
        db()->query('DELETE FROM {p}sports_matches WHERE id = ?', [$id]);
        AuditService::log('delete', 'sports', $id, 'मैच हटाया');
        cache()->flush('home');
        return $this->toRoute('admin.sports.index')->with('success', 'मैच हट गया।');
    }

    /** लाइव कंसोल */
    public function console(Request $request, int $id): Response
    {
        $m = SS::match($id) ?? throw new HttpException(404);
        return $this->view('admin/sports/console', ['m' => $m, 'comments' => SS::commentary($id, 0, 200)]);
    }

    public function score(Request $request, int $id): Response
    {
        $m = $this->findMatch($id);
        $in = array_intersect_key($request->post(), array_flip(['status', 'score1', 'score2', 'status_text', 'result', 'toss', 'potm', 'winner']));
        $text = trim((string) $request->input('c_text', ''));
        if ($text !== '') {
            $in['commentary'] = [['text' => $text, 'type' => (string) $request->input('c_type', 'info'), 'marker' => (string) $request->input('c_marker', '')]];
        }
        if (($in['status'] ?? '') === 'completed' && ($in['winner'] ?? '') === '' && trim((string) ($in['result'] ?? '')) === '') {
            throw new ValidationException(['result' => 'मैच पूरा करने से पहले नतीजा लिखें (और जीतने वाली टीम या ड्रॉ चुनें)।'], $request->post());
        }
        $r = SS::applyUpdate($m, $in, auth()->id());
        if (isset($r['error'])) {
            throw new ValidationException(['status' => $r['error']], $request->post());
        }
        AuditService::log('update', 'sports', $id, 'स्कोर अपडेट' . ($r['commentary'] ? ' + कमेंट्री' : ''));
        if ($request->wantsJson()) {
            return $this->json(['ok' => true] + $r);
        }
        return $this->toRoute('admin.sports.console', ['id' => $id])->with('success', 'अपडेट हो गया।' . ($r['commentary'] ? ' कमेंट्री जुड़ी।' : ''));
    }

    /** सिर्फ़ कमेंट्री (JS से, पेज बिना रीलोड) */
    public function comment(Request $request, int $id): Response
    {
        $this->findMatch($id);
        $cid = SS::addCommentary($id, (string) $request->input('text', ''), (string) $request->input('type', 'info'), (string) $request->input('marker', ''), auth()->id());
        if (!$cid) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'message' => 'कमेंट्री ख़ाली है।'], 422) : $this->back()->with('danger', 'कमेंट्री ख़ाली है।');
        }
        db()->update('sports_matches', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $row = db()->first('SELECT id, marker, type, text, created_at FROM {p}sports_commentary WHERE id = ?', [$cid]);
        return $request->wantsJson() ? $this->json(['ok' => true, 'item' => $row + ['label' => SS::COMM[$row['type']][0]]]) : $this->back()->with('success', 'कमेंट्री जुड़ी।');
    }

    public function deleteComment(Request $request, int $id, int $cid): Response
    {
        db()->query('DELETE FROM {p}sports_commentary WHERE id = ? AND match_id = ?', [$cid, $id]);
        return $request->wantsJson() ? $this->json(['ok' => true]) : $this->back()->with('success', 'कमेंट्री हटी।');
    }

    // ---------- टूर्नामेंट ----------
    public function tournaments(Request $request): Response
    {
        return $this->view('admin/sports/tournaments', ['items' => db()->all('SELECT t.*, (SELECT COUNT(*) FROM {p}sports_matches m WHERE m.tournament_id = t.id) matches FROM {p}sports_tournaments t ORDER BY t.start_date DESC, t.id DESC')]);
    }

    public function createTournament(Request $request): Response
    {
        return $this->view('admin/sports/tournament-form', ['t' => null, 'topics' => $this->topics()]);
    }

    public function storeTournament(Request $request): Response
    {
        $data = $this->tourPayload($request, null);
        $id = db()->insert('sports_tournaments', $data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        AuditService::log('create', 'sports', $id, 'टूर्नामेंट: ' . $data['name']);
        return $this->toRoute('admin.sports.tournaments.edit', ['id' => $id])->with('success', 'टूर्नामेंट बन गया। अब इसके मैच जोड़ें।');
    }

    public function editTournament(Request $request, int $id): Response
    {
        $t = $this->findTour($id);
        return $this->view('admin/sports/tournament-form', ['t' => $t, 'topics' => $this->topics(), 'standings' => SS::standings($id),
            'teams' => db()->all('SELECT id, name, short_name FROM {p}sports_teams WHERE sport = ? ORDER BY name', [$t['sport']])]);
    }

    public function updateTournament(Request $request, int $id): Response
    {
        $t = $this->findTour($id);
        $data = $this->tourPayload($request, $t);
        db()->update('sports_tournaments', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        AuditService::log('update', 'sports', $id, 'टूर्नामेंट बदला: ' . $data['name']);
        cache()->flush('home');
        return $this->toRoute('admin.sports.tournaments.edit', ['id' => $id])->with('success', 'टूर्नामेंट सेव हो गया।');
    }

    public function destroyTournament(Request $request, int $id): Response
    {
        $t = $this->findTour($id);
        db()->query('DELETE FROM {p}sports_tournaments WHERE id = ?', [$id]);
        AuditService::log('delete', 'sports', $id, 'टूर्नामेंट हटाया: ' . $t['name']);
        return $this->toRoute('admin.sports.tournaments')->with('success', 'टूर्नामेंट हट गया (उसके मैच बचे रहे, बिना टूर्नामेंट के)।');
    }

    /** पॉइंट्स टेबल: हाथ से सेव / नतीजों से गिनें */
    public function standings(Request $request, int $id): Response
    {
        $t = $this->findTour($id);
        if ($request->input('recalc')) {
            $n = SS::recalcStandings($t);
            cache()->flush('home');
            return $this->toRoute('admin.sports.tournaments.edit', ['id' => $id])->with('success', $n . ' टीमों की पॉइंट्स टेबल नतीजों से बन गई (NRR/गोल-अंतर हाथ से भरें)।');
        }
        $add = (int) $request->input('add_team');
        if ($add && db()->value('SELECT id FROM {p}sports_teams WHERE id = ?', [$add]) && !db()->value('SELECT 1 FROM {p}sports_standings WHERE tournament_id = ? AND team_id = ?', [$id, $add])) {
            db()->insert('sports_standings', ['tournament_id' => $id, 'team_id' => $add, 'group_name' => mb_substr(trim(strip_tags((string) $request->input('add_group', ''))), 0, 30)]);
        }
        foreach ((array) $request->input('s', []) as $team => $r) {
            if (!is_array($r) || !ctype_digit((string) $team)) {
                continue;
            }
            if (!empty($r['remove'])) {
                db()->query('DELETE FROM {p}sports_standings WHERE tournament_id = ? AND team_id = ?', [$id, (int) $team]);
                continue;
            }
            $n = static fn($k, $min = 0, $max = 999) => max($min, min($max, (int) ($r[$k] ?? 0)));
            $nrr = trim((string) ($r['nrr'] ?? ''));
            $gd = trim((string) ($r['gd'] ?? ''));
            db()->update('sports_standings', ['group_name' => mb_substr(trim(strip_tags((string) ($r['group_name'] ?? ''))), 0, 30), 'played' => $n('played'), 'won' => $n('won'), 'lost' => $n('lost'),
                'drawn' => $n('drawn'), 'no_result' => $n('no_result'), 'points' => $n('points', -99), 'nrr' => is_numeric($nrr) ? max(-99, min(99, round((float) $nrr, 3))) : null,
                'gd' => is_numeric($gd) ? max(-999, min(999, (int) $gd)) : null, 'sort_order' => $n('sort_order', -99)], 'tournament_id = ? AND team_id = ?', [$id, (int) $team]);
        }
        cache()->flush('home');
        return $this->toRoute('admin.sports.tournaments.edit', ['id' => $id])->with('success', 'पॉइंट्स टेबल सेव हो गई।');
    }

    // ---------- टीमें / खिलाड़ी ----------
    public function teams(Request $request): Response
    {
        return $this->view('admin/sports/teams', ['items' => db()->all('SELECT t.*, (SELECT COUNT(*) FROM {p}sports_players p WHERE p.team_id = t.id) players FROM {p}sports_teams t ORDER BY t.sport, t.name')]);
    }

    public function saveTeam(Request $request): Response
    {
        $v = $this->validate($request, ['id' => 'nullable|integer', 'name' => 'required|min:2|max:150', 'short_name' => 'required|max:12', 'sport' => 'required|in:' . implode(',', array_keys(SS::SPORTS)),
            'color' => 'required|color', 'country' => 'nullable|max:60'], ['name' => 'टीम का नाम', 'short_name' => 'छोटा नाम', 'sport' => 'खेल', 'color' => 'रंग', 'country' => 'देश/शहर']);
        $id = (int) ($v['id'] ?? 0);
        $data = ['name' => trim(strip_tags((string) $v['name'])), 'short_name' => mb_strtoupper(trim(strip_tags((string) $v['short_name']))), 'sport' => $v['sport'], 'color' => strtolower((string) $v['color']),
            'country' => ($c = trim(strip_tags((string) ($v['country'] ?? '')))) !== '' ? $c : null, 'logo' => ($l = trim((string) $request->input('logo', ''))) !== '' && !str_contains($l, '..') ? $l : null];
        if ($id) {
            db()->update('sports_teams', $data, 'id = ?', [$id]);
        } else {
            $data['slug'] = FormService::slug('', $data['name'], 0, 'sports_teams');
            $id = db()->insert('sports_teams', $data);
        }
        AuditService::log('update', 'sports', $id, 'टीम: ' . $data['name']);
        return $this->toRoute('admin.sports.teams')->with('success', 'टीम सेव हो गई।');
    }

    public function deleteTeam(Request $request, int $id): Response
    {
        if (db()->value('SELECT COUNT(*) FROM {p}sports_matches WHERE team1_id = ? OR team2_id = ?', [$id, $id])) {
            return $this->back()->with('danger', 'इस टीम के मैच हैं; पहले मैच हटाएँ या बदलें।');
        }
        db()->query('DELETE FROM {p}sports_teams WHERE id = ?', [$id]);
        return $this->toRoute('admin.sports.teams')->with('success', 'टीम हट गई।');
    }

    public function team(Request $request, int $id): Response
    {
        $t = db()->first('SELECT * FROM {p}sports_teams WHERE id = ?', [$id]) ?? throw new HttpException(404);
        return $this->view('admin/sports/team', ['t' => $t, 'players' => db()->all('SELECT * FROM {p}sports_players WHERE team_id = ? ORDER BY is_captain DESC, name', [$id])]);
    }

    public function savePlayers(Request $request, int $id): Response
    {
        db()->first('SELECT id FROM {p}sports_teams WHERE id = ?', [$id]) ?? throw new HttpException(404);
        foreach ((array) $request->input('p', []) as $k => $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            $name = trim(strip_tags((string) ($p['name'] ?? '')));
            if ($pid && (!empty($p['delete']) || $name === '')) {
                db()->query('DELETE FROM {p}sports_players WHERE id = ? AND team_id = ?', [$pid, $id]);
                continue;
            }
            if ($name === '') {
                continue;
            }
            $data = ['name' => mb_substr($name, 0, 150), 'role' => mb_substr(trim(strip_tags((string) ($p['role'] ?? ''))), 0, 60) ?: null,
                'jersey' => mb_substr(trim(strip_tags((string) ($p['jersey'] ?? ''))), 0, 5) ?: null, 'is_captain' => !empty($p['is_captain']) ? 1 : 0];
            if ($pid) {
                db()->update('sports_players', $data, 'id = ? AND team_id = ?', [$pid, $id]);
            } else {
                db()->insert('sports_players', $data + ['team_id' => $id]);
            }
        }
        return $this->toRoute('admin.sports.team', ['id' => $id])->with('success', 'खिलाड़ी सेव हो गए।');
    }

    // ---------- सहायक ----------
    private function matchData(): array
    {
        return ['tournaments' => db()->all('SELECT id, name, sport FROM {p}sports_tournaments ORDER BY start_date DESC, id DESC'),
            'teams' => db()->all('SELECT id, name, short_name, sport FROM {p}sports_teams ORDER BY sport, name')];
    }

    private function matchPayload(Request $request): array
    {
        $v = $this->validate($request, ['tournament_id' => 'nullable|integer', 'sport' => 'required|in:' . implode(',', array_keys(SS::SPORTS)), 'team1_id' => 'required|integer', 'team2_id' => 'required|integer',
            'title' => 'nullable|max:190', 'stage' => 'nullable|max:60', 'group_name' => 'nullable|max:30', 'venue' => 'nullable|max:190', 'start_at' => 'required|date',
            'status' => 'required|in:' . implode(',', array_keys(SS::MATCH_STATUSES)), 'news_id' => 'nullable|integer'],
            ['tournament_id' => 'टूर्नामेंट', 'sport' => 'खेल', 'team1_id' => 'पहली टीम', 'team2_id' => 'दूसरी टीम', 'title' => 'मैच का नाम', 'stage' => 'चरण', 'group_name' => 'ग्रुप',
                'venue' => 'स्थान', 'start_at' => 'शुरू होने का समय', 'status' => 'स्थिति', 'news_id' => 'मैच रिपोर्ट (ख़बर ID)']);
        $errors = [];
        if ((int) $v['team1_id'] === (int) $v['team2_id']) {
            $errors['team2_id'] = 'दोनों टीमें अलग हों।';
        }
        foreach (['team1_id', 'team2_id'] as $k) {
            if (!db()->value('SELECT id FROM {p}sports_teams WHERE id = ?', [(int) $v[$k]])) {
                $errors[$k] = 'टीम नहीं मिली।';
            }
        }
        if ($v['news_id'] && !db()->value('SELECT id FROM {p}news WHERE id = ?', [(int) $v['news_id']])) {
            $errors['news_id'] = 'इस ID की ख़बर नहीं मिली।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $t = static fn($x) => ($x = trim(strip_tags((string) $x))) !== '' ? $x : null;
        $tour = (int) ($v['tournament_id'] ?? 0);
        return ['tournament_id' => $tour && db()->value('SELECT id FROM {p}sports_tournaments WHERE id = ?', [$tour]) ? $tour : null, 'sport' => $v['sport'],
            'team1_id' => (int) $v['team1_id'], 'team2_id' => (int) $v['team2_id'], 'title' => $t($v['title']), 'stage' => $t($v['stage']), 'group_name' => $t($v['group_name']),
            'venue' => $t($v['venue']), 'start_at' => date('Y-m-d H:i:s', strtotime((string) $v['start_at'])), 'status' => $v['status'],
            'news_id' => $v['news_id'] ? (int) $v['news_id'] : null, 'is_featured' => $request->input('is_featured') ? 1 : 0];
    }

    private function tourPayload(Request $request, ?array $t): array
    {
        $v = $this->validate($request, ['name' => 'required|min:3|max:190', 'slug' => 'nullable|slug|max:150', 'sport' => 'required|in:' . implode(',', array_keys(SS::SPORTS)), 'season' => 'nullable|max:40',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date', 'status' => 'required|in:' . implode(',', array_keys(SS::TOUR_STATUSES)), 'topic_id' => 'nullable|integer',
            'points_win' => 'required|integer|min:0|max:10', 'points_draw' => 'required|integer|min:0|max:10', 'description' => 'nullable|max:2000'],
            ['name' => 'नाम', 'slug' => 'पता', 'sport' => 'खेल', 'season' => 'सीज़न', 'start_date' => 'शुरू', 'end_date' => 'ख़त्म', 'status' => 'स्थिति', 'topic_id' => 'टॉपिक',
                'points_win' => 'जीत के अंक', 'points_draw' => 'ड्रॉ/बेनतीजा के अंक', 'description' => 'विवरण']);
        if ($v['start_date'] && $v['end_date'] && strtotime((string) $v['end_date']) < strtotime((string) $v['start_date'])) {
            throw new ValidationException(['end_date' => 'ख़त्म होने की तारीख़ शुरू से पहले नहीं हो सकती।'], $request->post());
        }
        $x = static fn($s) => ($s = trim(strip_tags((string) $s))) !== '' ? $s : null;
        return ['name' => (string) $x($v['name']), 'slug' => FormService::slug((string) ($v['slug'] ?? ''), (string) $v['name'], (int) ($t['id'] ?? 0), 'sports_tournaments'), 'sport' => $v['sport'],
            'season' => $x($v['season']), 'start_date' => $v['start_date'] ? date('Y-m-d', strtotime((string) $v['start_date'])) : null, 'end_date' => $v['end_date'] ? date('Y-m-d', strtotime((string) $v['end_date'])) : null,
            'status' => $v['status'], 'topic_id' => ($tp = (int) ($v['topic_id'] ?? 0)) && db()->value('SELECT id FROM {p}topics WHERE id = ?', [$tp]) ? $tp : null,
            'is_featured' => $request->input('is_featured') ? 1 : 0, 'points_win' => (int) $v['points_win'], 'points_draw' => (int) $v['points_draw'], 'description' => $x($v['description']),
            'logo' => ($l = trim((string) $request->input('logo', ''))) !== '' && !str_contains($l, '..') ? $l : null];
    }

    private function topics(): array
    {
        return db()->all("SELECT id, name FROM {p}topics WHERE status = 'active' ORDER BY name");
    }

    private function findMatch(int $id): array
    {
        return db()->first('SELECT * FROM {p}sports_matches WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    private function findTour(int $id): array
    {
        return db()->first('SELECT * FROM {p}sports_tournaments WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }
}
