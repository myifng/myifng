<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\ElectionService as ES;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** सार्वजनिक चुनाव केंद्र */
final class ElectionController extends FrontController
{
    public function index(Request $request): Response
    {
        $items = db()->all("SELECT e.*, l.name state FROM {p}elections e LEFT JOIN {p}locations l ON l.id = e.state_id WHERE e.status <> 'draft'
            ORDER BY FIELD(e.status, 'counting', 'polling', 'upcoming', 'declared'), e.is_featured DESC, e.year DESC, e.id DESC");
        $featured = $items[0] ?? null;
        AnalyticsService::context(['type' => 'election']);
        return $this->view('front/elections/index', ['items' => $items, 'featured' => $featured, 'tally' => $featured ? ES::tally((int) $featured['id']) : [],
            'progress' => $featured ? ES::progress($featured) : null, 'side' => $this->sidebar(),
            'seo' => ['title' => 'चुनाव केंद्र: नतीजे, रुझान और उम्मीदवार', 'description' => 'लोकसभा और विधानसभा चुनाव के लाइव रुझान, नतीजे, सीट-वार उम्मीदवार और पिछले नतीजे, ' . setting('site_name') . ' पर।',
                'canonical' => route('elections.index')]]);
    }

    public function show(Request $request, string $slug): Response
    {
        $e = $this->election($slug);
        AnalyticsService::context(['type' => 'election', 'id' => $e['id']]);
        $tally = ES::tally((int) $e['id']);
        $news = [];
        if ($e['topic_id']) {
            [$w, $p] = NewsQuery::topicWhere((int) $e['topic_id']);
            $news = NewsQuery::list($w, $p, 8);
        }
        $crumbs = [['होम', url()], ['चुनाव', route('elections.index')], [$e['name'], null]];
        $live = $e['status'] === 'counting';
        return $this->view('front/elections/show', ['e' => $e, 'tally' => $tally, 'alliances' => ES::alliances($tally), 'progress' => ES::progress($e), 'seats' => ES::seats((int) $e['id']),
            'key' => ES::keyCandidates((int) $e['id'], 8), 'news' => $news, 'crumbs' => $crumbs, 'live' => $live,
            'districts' => db()->all('SELECT DISTINCT d.id, d.name FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id JOIN {p}locations d ON d.id = s.district_id WHERE r.election_id = ? ORDER BY d.name', [$e['id']]),
            'seo' => ['title' => $e['name'] . ($live ? ' लाइव: रुझान और नतीजे' : ($e['status'] === 'declared' ? ' नतीजे' : '')), 'description' => $e['description'] ?: $e['name'] . ': पार्टी-वार सीटें, वोट शेयर, सीट-वार नतीजे और उम्मीदवार।',
                'canonical' => ES::url($e), 'jsonld' => SeoService::breadcrumbs($crumbs)]]);
    }

    public function seat(Request $request, string $slug, string $seat): Response
    {
        $e = $this->election($slug);
        $s = db()->first('SELECT s.*, r.status, r.rounds, r.total_rounds, r.electors, r.total_votes, r.margin, r.updated_at result_at, d.name district, d.path district_path
            FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id LEFT JOIN {p}locations d ON d.id = s.district_id WHERE r.election_id = ? AND s.slug = ?', [$e['id'], $seat]);
        if (!$s) {
            throw new HttpException(404);
        }
        AnalyticsService::context(['type' => 'election', 'id' => $e['id'], 'location_id' => $s['district_id']]);
        $crumbs = [['होम', url()], ['चुनाव', route('elections.index')], [$e['name'], ES::url($e)], [$s['name'], null]];
        $nearby = $s['district_id'] ? db()->all('SELECT s.name, s.slug FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id WHERE r.election_id = ? AND s.district_id = ? AND s.id <> ? ORDER BY s.number, s.name LIMIT 20',
            [$e['id'], $s['district_id'], $s['id']]) : [];
        return $this->view('front/elections/seat', ['e' => $e, 's' => $s, 'candidates' => ES::candidates((int) $e['id'], (int) $s['id']), 'history' => ES::history((int) $s['id'], (int) $e['id']),
            'nearby' => $nearby, 'crumbs' => $crumbs, 'live' => $e['status'] === 'counting' && $s['status'] !== 'declared', 'side' => $this->sidebar(null, $s['district_id'] ? (int) $s['district_id'] : null, ($s['district'] ?? '') . ' में लोकप्रिय'),
            'seo' => ['title' => $s['name'] . ' सीट: ' . $e['name'] . ' ' . ($s['status'] === 'declared' ? 'नतीजा' : 'रुझान'), 'description' => $s['name'] . ' (' . ($s['district'] ?? '') . ') सीट के उम्मीदवार, वोट, जीत का अंतर और पिछले नतीजे।',
                'canonical' => route('elections.seat', ['slug' => $e['slug'], 'seat' => $s['slug']]), 'jsonld' => SeoService::breadcrumbs($crumbs)]]);
    }

    /** लाइव JSON (ऑटो-रिफ़्रेश) */
    public function live(Request $request, string $slug): Response
    {
        $e = $this->election($slug);
        return $this->json(ES::live($e))->header('Cache-Control', 'public, max-age=15')->header('Access-Control-Allow-Origin', '*');
    }

    private function election(string $slug): array
    {
        return db()->first("SELECT e.*, l.name state FROM {p}elections e LEFT JOIN {p}locations l ON l.id = e.state_id WHERE e.slug = ? AND e.status <> 'draft'", [$slug]) ?? throw new HttpException(404);
    }
}
