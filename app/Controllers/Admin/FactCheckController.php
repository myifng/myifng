<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\FactCheckService as FC;
use App\Services\FormService;
use App\Services\HtmlSanitizer;

/** फ़ैक्ट चेक डेस्क */
final class FactCheckController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->str('status');
        $verdict = $request->str('verdict');
        $q = trim($request->str('q'));
        $w = ['1=1'];
        $p = [];
        if (isset(FC::STATUSES[$status])) {
            $w[] = 'f.status = ?';
            $p[] = $status;
        }
        if (isset(FC::VERDICTS[$verdict])) {
            $w[] = 'f.verdict = ?';
            $p[] = $verdict;
        }
        if ($q !== '') {
            $w[] = '(f.title LIKE ? OR f.claim LIKE ?)';
            array_push($p, "%$q%", "%$q%");
        }
        $where = implode(' AND ', $w);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}fact_checks f WHERE $where", $p);
        $items = db()->all("SELECT f.*, u.name author FROM {p}fact_checks f LEFT JOIN {p}users u ON u.id = f.author_id WHERE $where ORDER BY f.updated_at DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $p);
        $counts = [];
        foreach (db()->all('SELECT status, COUNT(*) c FROM {p}fact_checks GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }
        return $this->view('admin/fact-checks/index', ['items' => new Paginator($items, $total, 30, $page), 'status' => $status, 'verdict' => $verdict, 'q' => $q, 'counts' => $counts]);
    }

    public function create(Request $request): Response
    {
        $news = $request->int('news') ? db()->first('SELECT id, title, category_id, location_id FROM {p}news WHERE id = ?', [$request->int('news')]) : null;
        return $this->form(null, $news);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = db()->insert('fact_checks', $data + ['author_id' => auth()->id(), 'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        AuditService::log('create', 'fact_checks', $id, 'फ़ैक्ट चेक: ' . $data['title'] . ' (' . FC::verdict($data['verdict'])[0] . ')');
        $this->flush();
        return $this->toRoute('admin.fact_checks.edit', ['id' => $id])->with('success', $data['status'] === 'published' ? 'फ़ैक्ट चेक प्रकाशित हो गई।' : 'फ़ैक्ट चेक सेव हो गई।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id), null);
    }

    public function update(Request $request, int $id): Response
    {
        $fc = $this->find($id);
        $data = $this->payload($request, $fc);
        db()->update('fact_checks', $data + ['updated_by' => auth()->id(), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $what = $fc['verdict'] !== $data['verdict'] ? ' · फ़ैसला: ' . FC::verdict($fc['verdict'])[0] . ' → ' . FC::verdict($data['verdict'])[0] : '';
        AuditService::log('update', 'fact_checks', $id, 'फ़ैक्ट चेक बदली: ' . $data['title'] . $what . ($fc['status'] !== $data['status'] ? ' · स्थिति: ' . $data['status'] : ''));
        $this->flush();
        return $this->toRoute('admin.fact_checks.edit', ['id' => $id])->with('success', 'फ़ैक्ट चेक सेव हो गई।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $fc = $this->find($id);
        if ($fc['status'] === 'published' && !can('fact_checks.publish')) {
            throw new HttpException(403, 'प्रकाशित फ़ैक्ट चेक हटाने के लिए प्रकाशन की अनुमति चाहिए।');
        }
        db()->query('DELETE FROM {p}fact_checks WHERE id = ?', [$id]);
        AuditService::log('delete', 'fact_checks', $id, 'फ़ैक्ट चेक हटाई: ' . $fc['title']);
        $this->flush();
        return $this->toRoute('admin.fact_checks.index')->with('success', 'फ़ैक्ट चेक हट गई।');
    }

    private function form(?array $fc, ?array $news): Response
    {
        $loc = null;
        $locId = (int) ($fc['location_id'] ?? ($news['location_id'] ?? 0));
        if ($locId && ($loc = db()->first('SELECT * FROM {p}locations WHERE id = ?', [$locId]))) {
            $loc['chain'] = implode(' › ', array_column([...Location::ancestors($loc), $loc], 'name'));
        }
        $linked = ($id = (int) ($fc['news_id'] ?? ($news['id'] ?? 0))) ? db()->first('SELECT id, title FROM {p}news WHERE id = ?', [$id]) : null;
        return $this->view('admin/fact-checks/form', ['fc' => $fc, 'prefill' => $news, 'categories' => Category::options(), 'location' => $loc, 'linked' => $linked,
            'statuses' => $this->allowedStatuses($fc)]);
    }

    /** इस यूज़र के लिए कौन-सी स्थितियाँ */
    private function allowedStatuses(?array $fc): array
    {
        $s = ['draft' => 'ड्राफ़्ट', 'review' => 'समीक्षा के लिए भेजें'];
        if (can('fact_checks.publish')) {
            $s += ['published' => 'प्रकाशित', 'archived' => 'आर्काइव'];
        } elseif ($fc && in_array($fc['status'], ['published', 'archived'], true)) {
            $s = [$fc['status'] => FC::STATUSES[$fc['status']][0]]; // प्रकाशित को बिना अनुमति बदलना नहीं
        }
        return $s;
    }

    private function payload(Request $request, ?array $fc): array
    {
        $allowed = $this->allowedStatuses($fc);
        $v = $this->validate($request, [
            'title' => 'required|min:5|max:255', 'slug' => 'nullable|slug|max:190', 'claim' => 'required|min:5|max:3000', 'claim_by' => 'nullable|max:190',
            'claim_url' => 'nullable|url|max:500', 'claim_date' => 'nullable|date', 'claim_medium' => 'required|in:' . implode(',', array_keys(FC::MEDIUMS)),
            'verdict' => 'required|in:' . implode(',', array_keys(FC::VERDICTS)), 'summary' => 'nullable|max:500', 'sources' => 'nullable|max:8000',
            'status' => 'required|in:' . implode(',', array_keys($allowed)), 'update_note' => 'nullable|max:500', 'meta_title' => 'nullable|max:190', 'meta_description' => 'nullable|max:320',
            'category_id' => 'nullable|integer', 'location_id' => 'nullable|integer', 'news_id' => 'nullable|integer',
        ], ['title' => 'शीर्षक', 'slug' => 'पता', 'claim' => 'दावा', 'claim_by' => 'दावा किसने किया', 'claim_url' => 'दावे का लिंक', 'claim_date' => 'दावे की तारीख़',
            'claim_medium' => 'माध्यम', 'verdict' => 'फ़ैसला', 'summary' => 'एक लाइन में नतीजा', 'sources' => 'स्रोत', 'status' => 'स्थिति', 'update_note' => 'अपडेट नोट',
            'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'category_id' => 'श्रेणी', 'location_id' => 'लोकेशन', 'news_id' => 'जुड़ी ख़बर']);
        $errors = [];
        $clean = static fn($h) => trim(HtmlSanitizer::clean((string) $h, (string) config('app.url')));
        $evidence = $clean($request->input('evidence', ''));
        $explanation = $clean($request->input('explanation', ''));
        $sources = FC::parseSources((string) ($v['sources'] ?? ''));
        $note = trim(strip_tags((string) ($v['update_note'] ?? '')));
        if ($v['status'] === 'published') {
            if (trim(strip_tags($explanation)) === '') {
                $errors['explanation'] = 'प्रकाशन से पहले व्याख्या लिखें: दावा सच/झूठ क्यों है।';
            }
            if (!$sources) {
                $errors['sources'] = 'प्रकाशन के लिए कम से कम एक स्रोत ज़रूरी है।';
            }
        }
        $verdictChanged = $fc && $fc['published_at'] && $fc['verdict'] !== $v['verdict'];
        if ($verdictChanged && $note === '') {
            $errors['update_note'] = 'प्रकाशित फ़ैक्ट चेक का फ़ैसला बदला है: पाठकों के लिए अपडेट नोट लिखें (क्या बदला और क्यों)।';
        }
        $cat = (int) ($v['category_id'] ?? 0);
        $loc = (int) ($v['location_id'] ?? 0);
        $news = (int) ($v['news_id'] ?? 0);
        if ($news && !db()->value('SELECT id FROM {p}news WHERE id = ?', [$news])) {
            $errors['news_id'] = 'इस ID की ख़बर नहीं मिली।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $publishedAt = $fc['published_at'] ?? null;
        if ($v['status'] === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $t = static fn($x) => ($x = trim(strip_tags((string) $x))) !== '' ? $x : null;
        return [
            'title' => (string) $t($v['title']), 'slug' => FormService::slug((string) ($v['slug'] ?? ''), (string) $v['title'], (int) ($fc['id'] ?? 0), 'fact_checks'),
            'claim' => (string) $t($v['claim']), 'claim_by' => $t($v['claim_by']), 'claim_url' => $t($v['claim_url']),
            'claim_date' => $v['claim_date'] ? date('Y-m-d', strtotime((string) $v['claim_date'])) : null, 'claim_medium' => $v['claim_medium'],
            'verdict' => $v['verdict'], 'summary' => $t($v['summary']), 'evidence' => $evidence !== '' ? $evidence : null, 'explanation' => $explanation !== '' ? $explanation : null,
            'sources' => $sources ? json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'image' => $t($request->input('image')) ?: null,
            'category_id' => $cat && db()->value('SELECT id FROM {p}categories WHERE id = ?', [$cat]) ? $cat : null,
            'location_id' => $loc && db()->value('SELECT id FROM {p}locations WHERE id = ?', [$loc]) ? $loc : null,
            'news_id' => $news ?: null,
            'reviewer_id' => $v['status'] === 'published' && ($fc['status'] ?? '') !== 'published' ? auth()->id() : ($fc['reviewer_id'] ?? null),
            'status' => $v['status'], 'published_at' => $publishedAt,
            'update_note' => $note !== '' ? mb_substr($note, 0, 500) : ($fc['update_note'] ?? null),
            'updated_verdict_at' => $verdictChanged ? date('Y-m-d H:i:s') : ($fc['updated_verdict_at'] ?? null),
            'meta_title' => $t($v['meta_title']), 'meta_description' => $t($v['meta_description']),
        ];
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM {p}fact_checks WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    private function flush(): void
    {
        cache()->flush('home');
        cache()->flush('sitemap');
    }
}
