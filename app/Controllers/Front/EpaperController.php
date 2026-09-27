<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\EpaperService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** /epaper, /epaper/{संस्करण}, /epaper/{संस्करण}/{तारीख़} (रीडर), /archive, /pdf */
final class EpaperController extends FrontController
{
    public function index(Request $request): Response
    {
        $this->enabled();
        $cards = [];
        foreach (EpaperService::editions() as $e) {
            if ($i = EpaperService::latest((int) $e['id'])) {
                $cards[] = ['edition' => $e, 'issue' => $i];
            }
        }
        // एक ही संस्करण हो तो सीधे रीडर
        if (count($cards) === 1) {
            return $this->redirect(EpaperService::url($cards[0]['edition'], $cards[0]['issue']));
        }
        return $this->view('front/epaper-index', ['cards' => $cards, 'seo' => ['title' => 'ई-पेपर', 'canonical' => route('epaper'),
            'description' => 'पढ़ें ' . setting('site_name') . ' का आज का ई-पेपर: सभी संस्करण, पुराने अंक।']]);
    }

    /** संस्करण → उसका ताज़ा अंक */
    public function edition(Request $request, string $edition): Response
    {
        $this->enabled();
        $e = EpaperService::edition($edition) ?? throw new HttpException(404);
        $i = EpaperService::latest((int) $e['id']);
        if (!$i) {
            return $this->view('front/epaper-index', ['cards' => [], 'empty' => $e, 'seo' => ['title' => $e['name'] . ' ई-पेपर', 'robots' => 'noindex,follow']]);
        }
        return $this->redirect(EpaperService::url($e, $i));
    }

    /** बिना JS वाला संस्करण/तारीख़ चुनने का फ़ॉर्म */
    public function go(Request $request): Response
    {
        $this->enabled();
        $e = EpaperService::edition($request->str('edition')) ?? throw new HttpException(404);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->str('date')) ? $request->str('date') : '';
        if ($date && ($i = EpaperService::issue((int) $e['id'], $date))) {
            return $this->redirect(EpaperService::url($e, $i));
        }
        if ($date) {
            return $this->redirect(route('epaper.archive', ['edition' => $e['slug']]) . '?month=' . substr($date, 0, 7))->with('info', hindi_date($date) . ' का ' . $e['name'] . ' उपलब्ध नहीं है। आर्काइव से चुनें।');
        }
        return $this->redirect(route('epaper.edition', ['edition' => $e['slug']]));
    }

    public function show(Request $request, string $edition, string $date): Response
    {
        $this->enabled();
        $e = EpaperService::edition($edition) ?? throw new HttpException(404);
        $issue = EpaperService::issue((int) $e['id'], $date) ?? throw new HttpException(404);
        NewsQuery::hit('epaper_issues', (int) $issue['id']);
        $free = EpaperService::freePages($issue);
        $pages = EpaperService::pages((int) $issue['id']);
        $open = array_values(array_filter($pages, static fn($p) => $p['page_no'] <= $free));
        $spots = [];
        if ($open) {
            $rows = db()->all('SELECT h.*, n.slug AS news_slug, n.title AS news_title FROM {p}epaper_hotspots h LEFT JOIN {p}news n ON n.id = h.news_id AND ' . NewsQuery::PUBLISHED
                . ' WHERE h.page_id IN (' . implode(',', array_map(static fn($p) => (int) $p['id'], $open)) . ')');
            foreach ($rows as $h) {
                $link = $h['news_slug'] ? route('news.show', ['slug' => $h['news_slug']]) : \App\Services\EmbedService::href($h['url']);
                if ($link) {
                    $spots[(int) $h['page_id']][] = $h + ['link' => $link, 'title' => $h['label'] ?: ($h['news_title'] ?: 'लेख पढ़ें')];
                }
            }
        }
        $pageNo = max(1, min(count($pages), $request->int('page', 1)));
        $editions = [];
        foreach (EpaperService::editions() as $ed) {
            $editions[] = $ed;
        }
        $url = EpaperService::url($e, $issue);
        $title = $e['name'] . ' ई-पेपर · ' . hindi_date($issue['issue_date']);
        $cover = $pages ? upload_url($pages[0]['image']) : null;
        return $this->view('front/epaper', [
            'edition' => $e, 'issue' => $issue, 'pages' => $pages, 'free' => $free, 'spots' => $spots, 'pageNo' => $pageNo, 'editions' => $editions,
            'prev' => EpaperService::sibling($issue, -1), 'next' => EpaperService::sibling($issue, 1), 'download' => EpaperService::canDownload($issue), 'url' => $url,
            'seo' => ['title' => $title . ($issue['title'] ? ' · ' . $issue['title'] : ''), 'canonical' => $url, 'image' => $cover,
                'description' => 'पढ़ें ' . setting('site_name') . ' ' . $e['name'] . ' का ' . hindi_date($issue['issue_date']) . ' का ई-पेपर: ' . count($pages) . ' पेज।',
                'jsonld' => SeoService::json(['@context' => 'https://schema.org', '@type' => 'PublicationIssue', 'name' => $title, 'url' => $url,
                    'datePublished' => $issue['issue_date'], 'pageStart' => 1, 'pageEnd' => count($pages), 'image' => $cover,
                    'isPartOf' => ['@type' => 'Periodical', 'name' => setting('site_name') . ' ' . $e['name'], 'publisher' => ['@type' => 'NewsMediaOrganization', 'name' => (string) setting('site_name')]],
                    'isAccessibleForFree' => $issue['access'] === 'free'])],
        ]);
    }

    /** महीने का कैलेंडर: किस दिन अंक है (कवर के साथ) */
    public function archive(Request $request, string $edition): Response
    {
        $this->enabled();
        $e = EpaperService::edition($edition) ?? throw new HttpException(404);
        $month = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $request->str('month')) ? $request->str('month') : date('Y-m');
        if ($month > date('Y-m')) {
            $month = date('Y-m');
        }
        $rows = db()->all("SELECT i.issue_date, i.cover, i.page_count, i.title, i.access FROM {p}epaper_issues i WHERE i.edition_id = ? AND DATE_FORMAT(i.issue_date, '%Y-%m') = ? AND "
            . EpaperService::PUBLISHED . ' AND i.page_count > 0', [$e['id'], $month]);
        $byDate = array_column($rows, null, 'issue_date');
        $first = (int) db()->value('SELECT MIN(DATE_FORMAT(i.issue_date, "%Y%m")) FROM {p}epaper_issues i WHERE i.edition_id = ? AND ' . EpaperService::PUBLISHED, [$e['id']]);
        $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
        $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
        return $this->view('front/epaper-archive', [
            'edition' => $e, 'month' => $month, 'byDate' => $byDate, 'editions' => EpaperService::editions(),
            'prevMonth' => $first && (int) str_replace('-', '', $prevMonth) >= $first ? $prevMonth : null, 'nextMonth' => $nextMonth <= date('Y-m') ? $nextMonth : null,
            'seo' => ['title' => $e['name'] . ' ई-पेपर आर्काइव · ' . hindi_date($month . '-01'), 'canonical' => route('epaper.archive', ['edition' => $e['slug']]) . '?month=' . $month,
                'description' => $e['name'] . ' के पुराने ई-पेपर अंक।', 'robots' => $byDate ? 'index,follow' : 'noindex,follow'],
        ]);
    }

    public function pdf(Request $request, string $edition, string $date): Response
    {
        $this->enabled();
        $e = EpaperService::edition($edition) ?? throw new HttpException(404);
        $issue = EpaperService::issue((int) $e['id'], $date) ?? throw new HttpException(404);
        $abs = $issue['pdf'] ? EpaperService::root() . $issue['pdf'] : '';
        if (!EpaperService::canDownload($issue) || !is_file($abs)) {
            throw new HttpException(404);
        }
        $name = preg_replace('/[^a-z0-9-]/', '', $e['slug']) . '-' . $issue['issue_date'] . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    private function enabled(): void
    {
        if (!EpaperService::enabled()) {
            throw new HttpException(404);
        }
    }
}
