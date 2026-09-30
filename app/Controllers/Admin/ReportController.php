<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use App\Services\NewsroomReport as NR;

/** न्यूज़रूम प्रदर्शन रिपोर्ट (§63) */
final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        AnalyticsService::fresh();
        $r = AnalyticsService::range($request);
        [$f, $t] = [$r['from'], $r['to']];
        return $this->view('admin/reports/index', [
            'r' => $r, 'sum' => NR::summary($f, $t), 'prev' => NR::summary($r['pfrom'], $r['pto']), 'daily' => NR::daily($f, $t),
            'reporters' => NR::reporters($f, $t, 50), 'editors' => NR::editors($f, $t), 'breaking' => NR::breaking($f, $t),
            'assign' => NR::assignments($f, $t), 'queue' => NR::queue(10),
        ]);
    }

    /** रिपोर्टर: मेरा प्रदर्शन (सिर्फ़ अपनी ख़बरें) */
    public function mine(Request $request): Response
    {
        AnalyticsService::fresh();
        $r = AnalyticsService::range($request);
        [$f, $t] = [$r['from'], $r['to']];
        $id = (int) auth()->id();
        $top = db()->all("SELECT n.id, n.title, n.slug, n.status, n.published_at, SUM(d.views) views, SUM(d.visitors) visitors, n.shares
            FROM {p}analytics_daily d JOIN {p}news n ON n.id = d.dim_key WHERE d.dim = 'news' AND d.day BETWEEN ? AND ? AND n.reporter_id = ?
            GROUP BY n.id ORDER BY views DESC LIMIT 15", [$f, $t, $id]);
        return $this->view('admin/reports/mine', ['r' => $r, 'sum' => NR::summary($f, $t, $id), 'assign' => NR::assignments($f, $t, $id), 'top' => $top,
            'series' => AnalyticsService::series($f, $t, 'reporter', (string) $id),
            'recent' => db()->all("SELECT n.id, n.title, n.status, n.updated_at, (SELECT r.message FROM {p}news_remarks r WHERE r.news_id = n.id AND r.to_status = 'rejected' ORDER BY r.id DESC LIMIT 1) reason
                FROM {p}news n WHERE n.reporter_id = ? AND n.deleted_at IS NULL AND n.status IN ('submitted','review','fact_check','rejected') ORDER BY n.updated_at DESC LIMIT 10", [$id])]);
    }

    /** लोकल कवरेज: किन ज़िलों से ख़बरें नहीं आ रहीं, कहाँ रिपोर्टर नहीं */
    public function coverage(Request $request): Response
    {
        $state = $request->int('state') ?: null;
        $rows = \App\Services\LocalService::coverage($state);
        $only = $request->str('show') === 'gaps';
        if ($request->str('export') === 'csv' && can('reports.export')) {
            AuditService::log('export', 'reports', null, 'लोकल कवरेज CSV');
            return Response::csv('local-coverage-' . date('Y-m-d') . '.csv', ['ज़िला', 'राज्य', '7 दिन', '30 दिन', 'आख़िरी ख़बर', 'सक्रिय रिपोर्टर', 'समीक्षा में', 'गैप'],
                array_map(static fn($r) => [$r['name'], $r['state'], $r['d7'], $r['d30'], $r['last'], $r['reporters'], $r['pending'], $r['gap'] ? 'हाँ' : ''], $rows));
        }
        $summary = ['districts' => count($rows), 'gaps' => count(array_filter($rows, static fn($r) => $r['gap'])), 'noReporter' => count(array_filter($rows, static fn($r) => !$r['reporters'])),
            'd7' => array_sum(array_column($rows, 'd7'))];
        return $this->view('admin/reports/coverage', ['rows' => $only ? array_values(array_filter($rows, static fn($r) => $r['gap'])) : $rows, 'summary' => $summary, 'state' => $state, 'only' => $only,
            'states' => db()->all("SELECT id, name FROM {p}locations WHERE type = 'state' AND status = 'active' ORDER BY name")]);
    }

    public function export(Request $request, string $kind): Response
    {
        $r = AnalyticsService::range($request);
        [$f, $t] = [$r['from'], $r['to']];
        [$head, $rows] = match ($kind) {
            'reporters' => [['रिपोर्टर', 'भेजी', 'प्रकाशित', 'अस्वीकार', 'बाकी', 'औसत शब्द', 'व्यू'],
                array_map(static fn($x) => [$x['name'], $x['submitted'], $x['published'], $x['rejected'], $x['pending'], $x['words'], $x['views']], NR::reporters($f, $t))],
            'editors' => [['एडिटर', 'कुल कार्रवाई', 'मंज़ूर', 'अस्वीकार', 'प्रकाशित', 'समीक्षा', 'औसत मंज़ूरी समय (घंटे)', 'अभी कतार'],
                array_map(static fn($x) => [$x['name'], $x['actions'], $x['approved'], $x['rejected'], $x['published'], $x['reviewing'], $x['avg_hours'], $x['queue']], NR::editors($f, $t))],
            'assignments' => [['रिपोर्टर', 'कुल', 'पूरे', 'बाकी', 'ओवरड्यू'],
                array_map(static fn($x) => [$x['name'], $x['total'], $x['completed'], $x['open'], $x['overdue']], NR::assignments($f, $t)['by'])],
            default => throw new HttpException(404),
        };
        AuditService::log('export', 'reports', null, 'न्यूज़रूम रिपोर्ट CSV: ' . $kind . ' (' . $f . ' – ' . $t . ')');
        return Response::csv('newsroom-' . $kind . '-' . $f . '-' . $t . '.csv', $head, $rows);
    }
}
