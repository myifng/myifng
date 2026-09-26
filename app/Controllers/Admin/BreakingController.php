<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\BreakingNews;
use App\Services\AuditService;
use App\Services\BreakingService;
use App\Services\EmbedService;

/** ब्रेकिंग कंट्रोल रूम: ब्रेकिंग / फ़्लैश / अलर्ट, टिकर, होमपेज बैनर, मोबाइल अलर्ट, पुश (कतार) */
final class BreakingController extends Controller
{
    public const ENDS_IN = ['1' => '1 घंटा', '3' => '3 घंटे', '6' => '6 घंटे', '12' => '12 घंटे', '24' => '24 घंटे', '48' => '2 दिन', '0' => 'कभी नहीं (हाथ से बंद करें)', 'custom' => 'तय समय…'];

    private const TABS = [
        'live' => BreakingService::ACTIVE,
        'scheduled' => "b.status = 'active' AND b.starts_at > NOW()",
        'ended' => "(b.status = 'inactive' OR (b.ends_at IS NOT NULL AND b.ends_at <= NOW()))",
        'all' => '1=1',
    ];

    public function index(Request $request): Response
    {
        $tab = isset(self::TABS[$request->str('tab')]) ? $request->str('tab') : 'live';
        $page = max(1, $request->int('page', 1));
        $w = self::TABS[$tab];
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}breaking_news b WHERE $w");
        $items = db()->all("SELECT b.*, n.title AS news_title, u.name AS author FROM {p}breaking_news b LEFT JOIN {p}news n ON n.id = b.news_id LEFT JOIN {p}users u ON u.id = b.created_by
                            WHERE $w ORDER BY " . ($tab === 'live' ? 'b.priority DESC, b.starts_at DESC' : 'b.starts_at DESC, b.id DESC') . ' LIMIT 30 OFFSET ' . Paginator::offset($page, 30));
        $counts = [];
        foreach (self::TABS as $k => $cond) {
            $counts[$k] = (int) db()->value("SELECT COUNT(*) FROM {p}breaking_news b WHERE $cond");
        }
        return $this->view('admin/breaking/index', ['items' => new Paginator($items, $total, 30, $page), 'tab' => $tab, 'counts' => $counts, 'item' => null, 'ticker' => BreakingService::ticker()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = BreakingNews::create($data + ['created_by' => auth()->id()]);
        $this->afterSave($id, $data);
        AuditService::log('create', 'breaking', $id, BreakingNews::TYPES[$data['type']] . ': ' . $data['title'], null, $data);
        return $this->toRoute('admin.breaking.index')->with('success', $data['status'] === 'active'
            ? 'चालू हो गया' . (strtotime($data['starts_at']) > time() ? ' (' . hindi_date($data['starts_at'], true) . ' से)' : '') . '। वेबसाइट पर एक मिनट में दिखेगा।'
            : 'सेव हो गया, पर बंद है। प्रकाशन की अनुमति वाले संपादक इसे चालू करेंगे।');
    }

    public function edit(Request $request, int $id): Response
    {
        $item = BreakingNews::find($id) ?? throw new HttpException(404);
        $item['news_title'] = $item['news_id'] ? db()->value('SELECT title FROM {p}news WHERE id = ?', [$item['news_id']]) : null;
        return $this->view('admin/breaking/edit', ['item' => $item]);
    }

    public function update(Request $request, int $id): Response
    {
        $item = BreakingNews::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $item);
        BreakingNews::update($id, $data);
        $this->afterSave($id, $data, $item);
        AuditService::log('update', 'breaking', $id, 'बदला: ' . $data['title'], $item, $data);
        return $this->toRoute('admin.breaking.index')->with('success', 'बदलाव सेव हो गए।');
    }

    /** अभी रोकें: ख़त्म होने का समय = अभी (रिकॉर्ड बना रहता है) */
    public function stop(Request $request, int $id): Response
    {
        $item = BreakingNews::find($id) ?? throw new HttpException(404);
        BreakingNews::update($id, ['ends_at' => now(), 'updated_by' => auth()->id()]);
        BreakingService::changed();
        AuditService::log('stop', 'breaking', $id, 'रोका: ' . $item['title']);
        return $request->wantsJson() ? $this->json(['ok' => true]) : $this->back()->with('success', '“' . \App\Helpers\Str::limit($item['title'], 60) . '” हटा दिया गया।');
    }

    /** दोबारा चलाएँ: अभी से, डिफ़ॉल्ट समाप्ति के साथ */
    public function restart(Request $request, int $id): Response
    {
        $item = BreakingNews::find($id) ?? throw new HttpException(404);
        BreakingNews::update($id, ['status' => 'active', 'starts_at' => now(), 'ends_at' => BreakingService::defaultEnds(), 'updated_by' => auth()->id()]);
        BreakingService::changed();
        AuditService::log('restart', 'breaking', $id, 'दोबारा चलाया: ' . $item['title']);
        return $this->back()->with('success', 'दोबारा चालू हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $item = BreakingNews::find($id) ?? throw new HttpException(404);
        BreakingNews::delete($id);
        BreakingService::changed();
        AuditService::log('delete', 'breaking', $id, 'हटाया: ' . $item['title'], $item);
        return $this->toRoute('admin.breaking.index')->with('success', 'हटा दिया गया।');
    }

    private function afterSave(int $id, array $data, ?array $old = null): void
    {
        if ($data['push'] && $data['status'] === 'active' && ($old['push_status'] ?? 'none') === 'none') {
            BreakingService::queuePush($id);
        }
        BreakingService::changed();
    }

    private function payload(Request $request, ?array $item): array
    {
        $v = $this->validate($request, [
            'title' => 'required|max:255', 'type' => 'required|in:' . implode(',', array_keys(BreakingNews::TYPES)), 'priority' => 'required|in:1,2,3',
            'news_id' => 'nullable|integer|exists:news,id', 'url' => 'nullable|max:500', 'starts_at' => 'nullable|date',
            'ends_in' => 'required|in:' . implode(',', array_keys(self::ENDS_IN)), 'ends_at' => 'nullable|date',
        ], ['title' => 'शीर्षक', 'type' => 'प्रकार', 'priority' => 'प्राथमिकता', 'news_id' => 'ख़बर', 'url' => 'लिंक', 'starts_at' => 'शुरू', 'ends_in' => 'कब तक', 'ends_at' => 'ख़त्म होने का समय']);
        $errors = [];
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v['title'])));
        if ($title === '') {
            $errors['title'] = 'शीर्षक में सादा टेक्स्ट लिखें।';
        }
        $url = trim((string) $v['url']);
        if ($url !== '' && !EmbedService::safeLink($url)) {
            $errors['url'] = 'लिंक https:// से या साइट के / पाथ से शुरू हो।';
        }
        $starts = $v['starts_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['starts_at'])) : ($item['starts_at'] ?? now());
        if (!$item && strtotime($starts) < time() - 60) {
            $starts = now(); // नया आइटम पीछे की तारीख़ से नहीं
        }
        $ends = match ($v['ends_in']) {
            'custom' => $v['ends_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['ends_at'])) : null,
            '0' => null,
            default => date('Y-m-d H:i:s', strtotime($starts) + (int) $v['ends_in'] * 3600),
        };
        if ($v['ends_in'] === 'custom' && !$ends) {
            $errors['ends_at'] = 'ख़त्म होने का समय चुनें।';
        } elseif ($ends && strtotime($ends) <= strtotime($starts)) {
            $errors['ends_at'] = 'ख़त्म होने का समय शुरू के बाद का हो।';
        }
        $places = ['show_ticker' => $request->bool('show_ticker'), 'show_banner' => $request->bool('show_banner'), 'mobile_alert' => $request->bool('mobile_alert')];
        if (!array_filter($places)) {
            $errors['show_ticker'] = 'कम से कम एक जगह चुनें: टिकर, होमपेज अलर्ट या मोबाइल अलर्ट।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $canPublish = can('breaking.publish');
        $status = $canPublish ? ($request->str('status', 'active') === 'inactive' ? 'inactive' : 'active') : ($item['status'] ?? 'inactive');
        return ['title' => $title, 'type' => $v['type'], 'priority' => (int) $v['priority'], 'news_id' => $v['news_id'] ? (int) $v['news_id'] : null,
            'url' => $url ?: null, 'starts_at' => $starts, 'ends_at' => $ends, 'status' => $status, 'push' => $request->bool('push') ? 1 : 0, 'updated_by' => auth()->id()] + array_map('intval', $places);
    }
}
