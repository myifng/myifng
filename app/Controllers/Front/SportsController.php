<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\NewsQuery;
use App\Services\SeoService;
use App\Services\SportsService as SS;

/** सार्वजनिक खेल केंद्र */
final class SportsController extends FrontController
{
    public function index(Request $request): Response
    {
        AnalyticsService::context(['type' => 'sports']);
        $news = [];
        $cat = db()->first("SELECT id FROM {p}categories WHERE slug IN ('sports','khel','cricket') AND status = 'active' ORDER BY FIELD(slug, 'sports', 'khel', 'cricket') LIMIT 1");
        if ($cat) {
            [$w, $p] = NewsQuery::categoryWhere((int) $cat['id']);
            $news = NewsQuery::list($w, $p, 8);
        }
        return $this->view('front/sports/index', ['live' => SS::live(10), 'upcoming' => SS::upcoming(8), 'recent' => SS::recent(8), 'news' => $news,
            'tournaments' => db()->all("SELECT * FROM {p}sports_tournaments WHERE status IN ('live','upcoming') OR (status = 'completed' AND end_date >= CURDATE() - INTERVAL 30 DAY) ORDER BY is_featured DESC, FIELD(status, 'live', 'upcoming', 'completed'), start_date LIMIT 12"),
            'side' => $this->sidebar(), 'seo' => ['title' => 'खेल: लाइव स्कोर, मैच, पॉइंट्स टेबल', 'description' => 'क्रिकेट, फ़ुटबॉल, हॉकी, कबड्डी के लाइव स्कोर, फ़िक्स्चर, नतीजे और पॉइंट्स टेबल, ' . setting('site_name') . ' पर।', 'canonical' => route('sports.index')]]);
    }

    public function tournament(Request $request, string $slug): Response
    {
        $t = db()->first("SELECT * FROM {p}sports_tournaments WHERE slug = ? AND status <> 'draft'", [$slug]) ?? throw new HttpException(404);
        AnalyticsService::context(['type' => 'sports']);
        $news = [];
        if ($t['topic_id']) {
            [$w, $p] = NewsQuery::topicWhere((int) $t['topic_id']);
            $news = NewsQuery::list($w, $p, 6);
        }
        $standings = SS::standings((int) $t['id']);
        $groups = [];
        foreach ($standings as $s) {
            $groups[$s['group_name']][] = $s;
        }
        $crumbs = [['होम', url()], ['खेल', route('sports.index')], [$t['name'], null]];
        return $this->view('front/sports/tournament', ['t' => $t, 'live' => SS::live(20, (int) $t['id']), 'upcoming' => SS::upcoming(50, (int) $t['id']), 'recent' => SS::recent(50, (int) $t['id']),
            'groups' => $groups, 'news' => $news, 'crumbs' => $crumbs,
            'teams' => db()->all('SELECT DISTINCT tm.* FROM {p}sports_teams tm JOIN {p}sports_matches m ON tm.id IN (m.team1_id, m.team2_id) WHERE m.tournament_id = ? ORDER BY tm.name', [$t['id']]),
            'seo' => ['title' => $t['name'] . ($t['season'] ? ' ' . $t['season'] : '') . ': शेड्यूल, नतीजे, पॉइंट्स टेबल', 'description' => $t['description'] ?: $t['name'] . ' के सभी मैच, लाइव स्कोर, नतीजे और पॉइंट्स टेबल।',
                'canonical' => route('sports.tournament', ['slug' => $t['slug']]), 'image' => $t['logo'], 'jsonld' => SeoService::breadcrumbs($crumbs)]]);
    }

    public function match(Request $request, int $id, string $slug = ''): Response
    {
        $m = SS::match($id);
        if (!$m || ($m['tournament_id'] && db()->value("SELECT status FROM {p}sports_tournaments WHERE id = ?", [$m['tournament_id']]) === 'draft')) {
            throw new HttpException(404);
        }
        $url = SS::url($m);
        if ($slug !== basename((string) parse_url($url, PHP_URL_PATH))) {
            return Response::redirect($url, 301); // टीम का नाम बदला हो या पुराना/अधूरा पता
        }
        AnalyticsService::context(['type' => 'sports', 'id' => $id]);
        $report = $m['news_id'] ? NewsQuery::list('n.id = ?', [(int) $m['news_id']], 1)[0] ?? null : null;
        $crumbs = [['होम', url()], ['खेल', route('sports.index')]];
        if ($m['tournament']) {
            $crumbs[] = [$m['tournament'], route('sports.tournament', ['slug' => $m['tournament_slug']])];
        }
        $crumbs[] = [SS::title($m), null];
        $isLive = in_array($m['status'], ['live', 'break'], true);
        $comm = SS::commentary($id, 0, 200);
        $players = static fn($team) => $team ? db()->all('SELECT name, role, is_captain FROM {p}sports_players WHERE team_id = ? ORDER BY is_captain DESC, name', [$team]) : [];
        return $this->view('front/sports/match', ['m' => $m, 'comm' => $comm, 'report' => $report, 'crumbs' => $crumbs, 'isLive' => $isLive,
            'squad1' => $players($m['team1_id']), 'squad2' => $players($m['team2_id']),
            'more' => $m['tournament_id'] ? SS::list('m.id <> ?', [$id], "FIELD(m.status, 'live', 'break', 'scheduled', 'completed'), m.start_at", 6, (int) $m['tournament_id']) : [],
            'side' => $this->sidebar(), 'seo' => ['title' => SS::title($m) . ($isLive ? ' लाइव स्कोर' : ($m['status'] === 'completed' ? ' स्कोरकार्ड और नतीजा' : '')),
                'description' => trim(($m['result'] ?: $m['status_text'] ?: '') . ' ' . SS::title($m) . ' — ' . ($m['tournament'] ?? '') . ', ' . hindi_date($m['start_at'], true) . ($m['venue'] ? ', ' . $m['venue'] : '')),
                'canonical' => $url, 'jsonld' => SS::schema($m) . SeoService::breadcrumbs($crumbs)]]);
    }

    /** लाइव JSON: स्कोर + नई कमेंट्री (after=आख़िरी id) */
    public function live(Request $request, int $id): Response
    {
        $m = SS::match($id) ?? throw new HttpException(404);
        return $this->json(SS::liveJson($m, max(0, $request->int('after'))))->header('Cache-Control', 'public, max-age=10');
    }

    public function team(Request $request, string $slug): Response
    {
        $t = db()->first('SELECT * FROM {p}sports_teams WHERE slug = ?', [$slug]) ?? throw new HttpException(404);
        AnalyticsService::context(['type' => 'sports']);
        $crumbs = [['होम', url()], ['खेल', route('sports.index')], [$t['name'], null]];
        return $this->view('front/sports/team', ['t' => $t, 'players' => db()->all('SELECT * FROM {p}sports_players WHERE team_id = ? ORDER BY is_captain DESC, name', [$t['id']]),
            'upcoming' => SS::list("m.status IN ('live','break','scheduled','postponed')", [], 'm.start_at', 20, null, (int) $t['id']),
            'recent' => SS::list("m.status IN ('completed','abandoned')", [], 'm.start_at DESC', 20, null, (int) $t['id']), 'crumbs' => $crumbs, 'side' => $this->sidebar(),
            'seo' => ['title' => $t['name'] . ': खिलाड़ी, मैच और नतीजे', 'description' => $t['name'] . ' टीम के खिलाड़ी, आने वाले मैच और हाल के नतीजे।', 'canonical' => route('sports.team', ['slug' => $t['slug']]), 'jsonld' => SeoService::breadcrumbs($crumbs)]]);
    }
}
