<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use App\Services\ElectionService as ES;
use App\Services\FormService;

/** चुनाव केंद्र: चुनाव, पार्टियाँ, सीटें/उम्मीदवार, काउंटिंग डेस्क, CSV/JSON */
final class ElectionController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all('SELECT e.*, l.name state, (SELECT COUNT(*) FROM {p}election_results r WHERE r.election_id = e.id) seats,
                (SELECT COUNT(*) FROM {p}election_candidates c WHERE c.election_id = e.id) candidates,
                (SELECT COUNT(*) FROM {p}election_results r WHERE r.election_id = e.id AND r.status = \'declared\') declared
            FROM {p}elections e LEFT JOIN {p}locations l ON l.id = e.state_id ORDER BY e.year DESC, e.id DESC');
        return $this->view('admin/elections/index', ['items' => $items]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/elections/form', ['e' => null] + $this->formData());
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = db()->insert('elections', $data + ['created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        AuditService::log('create', 'elections', $id, 'चुनाव बनाया: ' . $data['name']);
        return $this->toRoute('admin.elections.show', ['id' => $id])->with('success', 'चुनाव बन गया। अब सीटें और उम्मीदवार जोड़ें (एक-एक या CSV से)।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->view('admin/elections/form', ['e' => $this->find($id)] + $this->formData());
    }

    public function update(Request $request, int $id): Response
    {
        $e = $this->find($id);
        $data = $this->payload($request, $e);
        db()->update('elections', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        ES::flush($id);
        AuditService::log('update', 'elections', $id, 'चुनाव बदला: ' . $data['name'] . ($e['status'] !== $data['status'] ? ' · स्थिति: ' . ES::STATUSES[$data['status']][0] : ''));
        return $this->toRoute('admin.elections.show', ['id' => $id])->with('success', 'चुनाव सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $e = $this->find($id);
        db()->query('DELETE FROM {p}elections WHERE id = ?', [$id]);
        ES::flush($id);
        AuditService::log('delete', 'elections', $id, 'चुनाव हटाया: ' . $e['name']);
        return $this->toRoute('admin.elections.index')->with('success', 'चुनाव, उसके उम्मीदवार और नतीजे हट गए (सीटें बची रहीं, ताकि पिछले चुनाव ख़राब न हों)।');
    }

    /** काउंटिंग डेस्क: सीटों की सूची, टैली, जोड़ें/इम्पोर्ट */
    public function show(Request $request, int $id): Response
    {
        $e = $this->find($id);
        $f = ['q' => trim($request->str('q')), 'status' => $request->str('status'), 'party' => $request->int('party')];
        $seats = ES::seats($id, $f);
        $cands = [];
        foreach (db()->all('SELECT seat_id, COUNT(*) c FROM {p}election_candidates WHERE election_id = ? GROUP BY seat_id', [$id]) as $r) {
            $cands[(int) $r['seat_id']] = (int) $r['c'];
        }
        return $this->view('admin/elections/show', ['e' => $e, 'seats' => $seats, 'cands' => $cands, 'f' => $f, 'tally' => ES::tally($id), 'progress' => ES::progress($e),
            'parties' => db()->all('SELECT id, short_name, name FROM {p}election_parties ORDER BY sort_order, name'),
            'districts' => $e['state_id'] ? db()->all("SELECT id, name FROM {p}locations WHERE type = 'district' AND parent_id IN (SELECT id FROM {p}locations WHERE id = ? OR parent_id = ?) ORDER BY name", [$e['state_id'], $e['state_id']]) : []]);
    }

    /** एक सीट जोड़ें */
    public function addSeat(Request $request, int $id): Response
    {
        $e = $this->find($id);
        $v = $this->validate($request, ['name' => 'required|min:2|max:150', 'number' => 'nullable|integer|min:1|max:9999', 'reserved' => 'required|in:gen,sc,st', 'district_id' => 'nullable|integer'],
            ['name' => 'सीट का नाम', 'number' => 'सीट नंबर', 'reserved' => 'आरक्षण', 'district_id' => 'ज़िला']);
        $sid = ES::attachSeat($e, ['name' => trim(strip_tags((string) $v['name'])), 'number' => $v['number'] ? (int) $v['number'] : null, 'reserved' => $v['reserved'],
            'district_id' => $v['district_id'] && db()->value("SELECT id FROM {p}locations WHERE id = ? AND type = 'district'", [(int) $v['district_id']]) ? (int) $v['district_id'] : null]);
        ES::syncSeatCount($e);
        ES::flush($id);
        return $this->toRoute('admin.elections.seat', ['id' => $id, 'seat' => $sid])->with('success', 'सीट जुड़ गई। अब उम्मीदवार जोड़ें।');
    }

    public function removeSeat(Request $request, int $id, int $seat): Response
    {
        $this->find($id);
        db()->query('DELETE FROM {p}election_candidates WHERE election_id = ? AND seat_id = ?', [$id, $seat]);
        db()->query('DELETE FROM {p}election_results WHERE election_id = ? AND seat_id = ?', [$id, $seat]);
        ES::flush($id);
        AuditService::log('delete', 'elections', $id, 'सीट हटाई (इस चुनाव से): #' . $seat);
        return $this->toRoute('admin.elections.show', ['id' => $id])->with('success', 'सीट इस चुनाव से हट गई।');
    }

    /** सीट: उम्मीदवार + वोट (काउंटिंग के दिन यही स्क्रीन) */
    public function seat(Request $request, int $id, int $seat): Response
    {
        $e = $this->find($id);
        $s = db()->first('SELECT s.*, r.status, r.rounds, r.total_rounds, r.electors, r.margin, r.total_votes, r.updated_at result_at FROM {p}election_seats s JOIN {p}election_results r ON r.seat_id = s.id AND r.election_id = ? WHERE s.id = ?', [$id, $seat])
            ?? throw new HttpException(404);
        $prevNext = db()->all('SELECT s.id, s.name FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id WHERE r.election_id = ? ORDER BY s.number IS NULL, s.number, s.name', [$id]);
        $ids = array_column($prevNext, 'id');
        $pos = array_search($seat, array_map('intval', $ids), true);
        return $this->view('admin/elections/seat', ['e' => $e, 's' => $s, 'candidates' => ES::candidates($id, $seat), 'history' => ES::history($seat, $id),
            'parties' => db()->all('SELECT id, short_name, name FROM {p}election_parties ORDER BY sort_order, name'),
            'prev' => $pos !== false && $pos > 0 ? $prevNext[$pos - 1] : null, 'next' => $pos !== false && $pos < count($ids) - 1 ? $prevNext[$pos + 1] : null]);
    }

    public function saveSeat(Request $request, int $id, int $seat): Response
    {
        $e = $this->find($id);
        if (!db()->value('SELECT 1 FROM {p}election_results WHERE election_id = ? AND seat_id = ?', [$id, $seat])) {
            throw new HttpException(404);
        }
        $v = $this->validate($request, ['status' => 'required|in:' . implode(',', array_keys(ES::RESULT)), 'rounds' => 'nullable|integer|min:0|max:999', 'total_rounds' => 'nullable|integer|min:0|max:999',
            'electors' => 'nullable|integer|min:0|max:50000000'], ['status' => 'स्थिति', 'rounds' => 'राउंड', 'total_rounds' => 'कुल राउंड', 'electors' => 'कुल मतदाता']);
        $errors = [];
        $rows = [];
        foreach ((array) $request->input('c', []) as $k => $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = trim(strip_tags((string) ($c['name'] ?? '')));
            $cid = (int) ($c['id'] ?? 0);
            if ($name === '') {
                if ($cid && !empty($c['delete'])) {
                    $rows[] = ['delete' => $cid];
                }
                continue;
            }
            $votes = trim((string) ($c['votes'] ?? '0'));
            if ($votes === '') {
                $votes = '0';
            }
            if (!ctype_digit($votes) || (int) $votes > 50000000) {
                $errors['c'] = "“{$name}” के वोट 0 या उससे बड़ी पूर्ण संख्या हों।";
                continue;
            }
            $age = trim((string) ($c['age'] ?? ''));
            $party = (int) ($c['party_id'] ?? 0);
            $rows[] = ['id' => $cid, 'delete' => !empty($c['delete']) ? $cid : 0, 'data' => ['name' => mb_substr($name, 0, 150), 'votes' => (int) $votes,
                'party_id' => $party && db()->value('SELECT id FROM {p}election_parties WHERE id = ?', [$party]) ? $party : null,
                'age' => ctype_digit($age) && (int) $age >= 18 && (int) $age < 120 ? (int) $age : null,
                'gender' => in_array($c['gender'] ?? '', ['m', 'f', 'o'], true) ? $c['gender'] : null,
                'education' => mb_substr(trim(strip_tags((string) ($c['education'] ?? ''))), 0, 120) ?: null,
                'photo' => ($ph = trim((string) ($c['photo'] ?? ''))) !== '' && !str_contains($ph, '..') ? mb_substr($ph, 0, 255) : null,
                'is_incumbent' => !empty($c['is_incumbent']) ? 1 : 0, 'is_key' => !empty($c['is_key']) ? 1 : 0, 'withdrawn' => !empty($c['withdrawn']) ? 1 : 0]];
        }
        if ($v['status'] === 'declared' && !array_filter($rows, static fn($r) => isset($r['data']) && !$r['delete'] && !$r['data']['withdrawn'] && $r['data']['votes'] > 0)) {
            $errors['status'] = 'नतीजा घोषित करने से पहले वोट भरें।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        db()->transaction(function () use ($rows, $id, $seat) {
            foreach ($rows as $r) {
                if ($r['delete']) {
                    db()->query('DELETE FROM {p}election_candidates WHERE id = ? AND election_id = ? AND seat_id = ?', [$r['delete'], $id, $seat]);
                } elseif ($r['id'] && db()->value('SELECT id FROM {p}election_candidates WHERE id = ? AND election_id = ? AND seat_id = ?', [$r['id'], $id, $seat])) {
                    db()->update('election_candidates', $r['data'] + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
                } elseif (!$r['id']) {
                    db()->insert('election_candidates', $r['data'] + ['election_id' => $id, 'seat_id' => $seat]);
                }
            }
        });
        if ($v['electors'] !== null && $v['electors'] !== '') {
            db()->update('election_results', ['electors' => (int) $v['electors']], 'election_id = ? AND seat_id = ?', [$id, $seat]);
        }
        ES::recompute($id, $seat, $v['status'], [(int) ($v['rounds'] ?? 0), (int) ($v['total_rounds'] ?? 0)]);
        AuditService::log('update', 'elections', $id, 'सीट #' . $seat . ' के वोट/स्थिति अपडेट: ' . ES::RESULT[$v['status']]);
        $next = $request->input('go_next') ? (int) $request->input('go_next') : 0;
        return ($next ? $this->toRoute('admin.elections.seat', ['id' => $id, 'seat' => $next]) : $this->toRoute('admin.elections.seat', ['id' => $id, 'seat' => $seat]))
            ->with('success', 'सीट अपडेट हो गई; टैली अपने आप बदली।');
    }

    public function import(Request $request, int $id): Response
    {
        $e = $this->find($id);
        $csv = (string) $request->input('csv', '');
        $file = $request->file('csv_file');
        if ($file && ($file['error'] ?? 1) === UPLOAD_ERR_OK && ($file['size'] ?? 0) <= 2 * 1024 * 1024) {
            $csv = (string) file_get_contents($file['tmp_name']);
        }
        if (trim($csv) === '') {
            return $this->back()->with('danger', 'CSV चिपकाएँ या फ़ाइल चुनें।');
        }
        if (!mb_check_encoding($csv, 'UTF-8')) {
            return $this->back()->with('danger', 'फ़ाइल UTF-8 में सेव करें (Excel: "CSV UTF-8")।');
        }
        $r = ES::importCsv($e, $csv);
        AuditService::log('import', 'elections', $id, 'CSV: ' . $r['seats'] . ' सीटें, ' . $r['candidates'] . ' उम्मीदवार');
        return $this->toRoute('admin.elections.show', ['id' => $id])->with($r['errors'] ? 'warning' : 'success',
            'इम्पोर्ट: ' . $r['seats'] . ' नई सीटें, ' . $r['candidates'] . ' नए उम्मीदवार।' . ($r['errors'] ? ' दिक्कतें: ' . implode('; ', array_slice($r['errors'], 0, 5)) : ''));
    }

    /** JSON (एजेंसी की फ़ाइल) से वोट */
    public function importJson(Request $request, int $id): Response
    {
        $e = $this->find($id);
        $data = json_decode((string) $request->input('json', ''), true);
        if (!is_array($data) || !isset($data['seats'])) {
            return $this->back()->with('danger', 'JSON सही नहीं; {"seats":[…]} के रूप में होना चाहिए।');
        }
        $r = ES::applyResults($e, $data);
        AuditService::log('import', 'elections', $id, 'JSON नतीजे: ' . $r['updated'] . ' सीटें');
        return $this->toRoute('admin.elections.show', ['id' => $id])->with($r['errors'] ? 'warning' : 'success',
            $r['updated'] . ' सीटें अपडेट हुईं।' . ($r['errors'] ? ' दिक्कतें: ' . implode('; ', array_slice($r['errors'], 0, 5)) : ''));
    }

    // ---------- पार्टियाँ ----------
    public function parties(Request $request): Response
    {
        return $this->view('admin/elections/parties', ['items' => db()->all('SELECT p.*, (SELECT COUNT(*) FROM {p}election_candidates c WHERE c.party_id = p.id) used FROM {p}election_parties p ORDER BY p.sort_order, p.name')]);
    }

    public function saveParty(Request $request): Response
    {
        $v = $this->validate($request, ['id' => 'nullable|integer', 'name' => 'required|min:2|max:150', 'short_name' => 'required|max:20', 'color' => 'required|color', 'alliance' => 'nullable|max:60', 'sort_order' => 'nullable|integer'],
            ['name' => 'पार्टी का नाम', 'short_name' => 'छोटा नाम', 'color' => 'रंग', 'alliance' => 'गठबंधन', 'sort_order' => 'क्रम']);
        $id = (int) ($v['id'] ?? 0);
        $data = ['name' => trim(strip_tags((string) $v['name'])), 'short_name' => mb_strtoupper(trim(strip_tags((string) $v['short_name']))), 'color' => strtolower((string) $v['color']),
            'alliance' => ($a = trim(strip_tags((string) ($v['alliance'] ?? '')))) !== '' ? $a : null, 'sort_order' => (int) ($v['sort_order'] ?? 0),
            'symbol' => ($sy = trim((string) $request->input('symbol', ''))) !== '' && !str_contains($sy, '..') ? $sy : null];
        $dup = db()->value('SELECT id FROM {p}election_parties WHERE short_name = ? AND id <> ?', [$data['short_name'], $id]);
        if ($dup) {
            throw new ValidationException(['short_name' => 'यह छोटा नाम पहले से है।'], $request->post());
        }
        if ($id) {
            db()->update('election_parties', $data, 'id = ?', [$id]);
        } else {
            $data['slug'] = FormService::slug(strtolower($data['short_name']), $data['name'], 0, 'election_parties');
            $id = db()->insert('election_parties', $data);
        }
        cache()->flush('election');
        AuditService::log('update', 'elections', null, 'पार्टी: ' . $data['name']);
        return $this->toRoute('admin.elections.parties')->with('success', 'पार्टी सेव हो गई।');
    }

    public function deleteParty(Request $request, int $id): Response
    {
        if (db()->value('SELECT COUNT(*) FROM {p}election_candidates WHERE party_id = ?', [$id])) {
            return $this->back()->with('danger', 'इस पार्टी के उम्मीदवार हैं; पहले उन्हें दूसरी पार्टी में डालें।');
        }
        db()->query('DELETE FROM {p}election_parties WHERE id = ?', [$id]);
        return $this->toRoute('admin.elections.parties')->with('success', 'पार्टी हट गई।');
    }

    // ---------- सहायक ----------
    private function formData(): array
    {
        return ['states' => db()->all("SELECT id, name FROM {p}locations WHERE type = 'state' AND status = 'active' ORDER BY name"),
            'topics' => db()->all("SELECT id, name FROM {p}topics WHERE status = 'active' ORDER BY name")];
    }

    private function payload(Request $request, ?array $e): array
    {
        $v = $this->validate($request, ['name' => 'required|min:3|max:190', 'slug' => 'nullable|slug|max:150', 'type' => 'required|in:' . implode(',', array_keys(ES::TYPES)),
            'year' => 'required|integer|min:1950|max:2100', 'state_id' => 'nullable|integer', 'poll_dates' => 'nullable|max:190', 'counting_date' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(ES::STATUSES)), 'total_seats' => 'nullable|integer|min:0|max:5000', 'majority' => 'nullable|integer|min:0|max:5000',
            'topic_id' => 'nullable|integer', 'description' => 'nullable|max:2000', 'source_note' => 'nullable|max:190'],
            ['name' => 'नाम', 'slug' => 'पता', 'type' => 'प्रकार', 'year' => 'साल', 'state_id' => 'राज्य', 'poll_dates' => 'मतदान की तारीख़ें', 'counting_date' => 'मतगणना',
                'status' => 'स्थिति', 'total_seats' => 'कुल सीटें', 'majority' => 'बहुमत', 'topic_id' => 'टॉपिक', 'description' => 'विवरण', 'source_note' => 'डेटा स्रोत']);
        if ($v['type'] !== 'lok_sabha' && empty($v['state_id'])) {
            throw new ValidationException(['state_id' => 'विधानसभा/स्थानीय चुनाव के लिए राज्य चुनें।'], $request->post());
        }
        $state = (int) ($v['state_id'] ?? 0);
        $seats = (int) ($v['total_seats'] ?? 0);
        $t = static fn($x) => ($x = trim(strip_tags((string) $x))) !== '' ? $x : null;
        return ['name' => (string) $t($v['name']), 'slug' => FormService::slug((string) ($v['slug'] ?? ''), (string) $v['name'] . ' ' . $v['year'], (int) ($e['id'] ?? 0), 'elections'),
            'type' => $v['type'], 'year' => (int) $v['year'], 'state_id' => $state && db()->value("SELECT id FROM {p}locations WHERE id = ? AND type = 'state'", [$state]) ? $state : null,
            'poll_dates' => $t($v['poll_dates']), 'counting_date' => $v['counting_date'] ? date('Y-m-d', strtotime((string) $v['counting_date'])) : null, 'status' => $v['status'],
            'total_seats' => $seats, 'majority' => (int) ($v['majority'] ?? 0) ?: ($seats ? intdiv($seats, 2) + 1 : 0),
            'topic_id' => ($tp = (int) ($v['topic_id'] ?? 0)) && db()->value('SELECT id FROM {p}topics WHERE id = ?', [$tp]) ? $tp : null,
            'is_featured' => $request->input('is_featured') ? 1 : 0, 'description' => $t($v['description']), 'source_note' => $t($v['source_note'])];
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM {p}elections WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }
}
