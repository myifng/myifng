<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Ad;
use App\Models\Category;
use App\Services\AdService;
use App\Services\AuditService;
use App\Services\EmbedService;
use App\Services\MediaService;

/** विज्ञापन: सूची, बनाना/बदलना, कॉपी, चालू/रोकें, प्रदर्शन, CSV */
final class AdController extends Controller
{
    public function index(Request $request): Response
    {
        [$w, $params, $f] = $this->filters($request);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}ads a WHERE $w", $params);
        $items = db()->all("SELECT a.*, c.name AS campaign, v.company AS advertiser,
                (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM {p}ad_placements p JOIN {p}ad_slots s ON s.id = p.slot_id WHERE p.ad_id = a.id) AS slots
                FROM {p}ads a LEFT JOIN {p}ad_campaigns c ON c.id = a.campaign_id LEFT JOIN {p}advertisers v ON v.id = c.advertiser_id
                WHERE $w ORDER BY a.status = 'active' DESC, a.updated_at DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $today = db()->first('SELECT COALESCE(SUM(impressions), 0) i, COALESCE(SUM(clicks), 0) c FROM {p}ad_stats_daily WHERE day = CURDATE()');
        $week = db()->first('SELECT COALESCE(SUM(impressions), 0) i, COALESCE(SUM(clicks), 0) c FROM {p}ad_stats_daily WHERE day >= CURDATE() - INTERVAL 6 DAY');
        return $this->view('admin/ads/index', ['items' => new Paginator($items, $total, 30, $page), 'f' => $f, 'today' => $today, 'week' => $week,
            'slots' => $this->slotOptions(), 'campaigns' => $this->campaignOptions(), 'chart' => $this->chart('1=1', [])]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $request->int('campaign') ?: null);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id));
    }

    private function form(?array $ad, ?int $campaign = null): Response
    {
        $slotIds = $ad ? array_map('intval', array_column(db()->all('SELECT slot_id FROM {p}ad_placements WHERE ad_id = ?', [$ad['id']]), 'slot_id')) : [];
        $locIds = $ad ? array_filter(array_map('intval', explode(',', (string) $ad['location_ids']))) : [];
        $locs = $locIds ? db()->all('SELECT id, name FROM {p}locations WHERE id IN (' . implode(',', $locIds) . ')') : [];
        return $this->view('admin/ads/form', [
            'ad' => $ad, 'campaign' => $campaign, 'slotIds' => $slotIds, 'locations' => $locs,
            'slots' => db()->all("SELECT * FROM {p}ad_slots ORDER BY is_system DESC, FIELD(placement, 'header','below_header','homepage','sidebar','article_top','article_inline','article_bottom','category','mobile_sticky','popup','footer','custom'), name"),
            'campaigns' => $this->campaignOptions(), 'categories' => Category::options(),
            'preview' => $ad ? AdService::render($ad) : '', 'chart' => $ad ? $this->chart('ad_id = ?', [$ad['id']]) : null,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $slots] = $this->payload($request, null);
        $id = db()->transaction(function () use ($data, $slots) {
            $id = Ad::create($data + ['created_by' => auth()->id()]);
            $this->syncSlots($id, $slots);
            return $id;
        });
        AdService::changed();
        AuditService::log('create', 'ads', $id, 'विज्ञापन: ' . $data['name'] . ' (' . Ad::TYPES[$data['type']] . ')', null, array_diff_key($data, ['code' => 1]));
        return $this->toRoute('admin.ads.edit', ['id' => $id])->with('success', 'विज्ञापन सेव हो गया।' . ($slots ? '' : ' ध्यान दें: कोई जगह (स्लॉट) नहीं चुनी, इसलिए अभी कहीं नहीं दिखेगा।'));
    }

    public function update(Request $request, int $id): Response
    {
        $ad = $this->find($id);
        [$data, $slots] = $this->payload($request, $ad);
        db()->transaction(function () use ($id, $data, $slots) {
            Ad::update($id, $data);
            $this->syncSlots($id, $slots);
        });
        AdService::changed();
        AuditService::log('update', 'ads', $id, 'विज्ञापन बदला: ' . $data['name'], array_diff_key($ad, ['code' => 1]), array_diff_key($data, ['code' => 1]));
        return $this->toRoute('admin.ads.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।');
    }

    public function toggle(Request $request, int $id): Response
    {
        $ad = $this->find($id);
        $this->guardCode($ad);
        $to = $ad['status'] === 'active' ? 'paused' : 'active';
        Ad::update($id, ['status' => $to, 'updated_by' => auth()->id()]);
        AdService::changed();
        AuditService::log($to === 'active' ? 'resume' : 'pause', 'ads', $id, ($to === 'active' ? 'चालू: ' : 'रोका: ') . $ad['name']);
        return $this->back()->with('success', $to === 'active' ? 'विज्ञापन चालू।' : 'विज्ञापन रोका गया।');
    }

    public function duplicate(Request $request, int $id): Response
    {
        $ad = $this->find($id);
        $this->guardCode($ad);
        $copy = array_intersect_key($ad, array_flip(['campaign_id', 'type', 'image', 'mobile_image', 'code', 'video_url', 'target_url', 'text', 'devices', 'category_ids', 'location_ids', 'start_at', 'end_at', 'priority', 'max_impressions', 'max_clicks']));
        $new = Ad::create($copy + ['name' => mb_substr('कॉपी: ' . $ad['name'], 0, 190), 'status' => 'draft', 'created_by' => auth()->id()]);
        foreach (db()->all('SELECT slot_id FROM {p}ad_placements WHERE ad_id = ?', [$id]) as $p) {
            db()->insert('ad_placements', ['ad_id' => $new, 'slot_id' => $p['slot_id']]);
        }
        AuditService::log('duplicate', 'ads', $new, 'विज्ञापन कॉपी: ' . $ad['name']);
        return $this->toRoute('admin.ads.edit', ['id' => $new])->with('success', 'कॉपी बन गई (ड्राफ़्ट)। बदलाव करके चालू करें।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $ad = $this->find($id);
        $this->guardCode($ad);
        Ad::delete($id);
        AdService::changed();
        AuditService::log('delete', 'ads', $id, 'विज्ञापन हटाया: ' . $ad['name'] . ' (' . $ad['impressions'] . ' इम्प्रेशन, ' . $ad['clicks'] . ' क्लिक)');
        return $this->toRoute('admin.ads.index')->with('success', '“' . $ad['name'] . '” हटा दिया गया।');
    }

    /** प्रदर्शन CSV: चुनी तारीख़ों में (रोज़ के हिसाब से जोड़) */
    public function export(Request $request): Response
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->str('from')) ? $request->str('from') : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->str('to')) ? $request->str('to') : date('Y-m-d');
        $rows = db()->all("SELECT a.id, a.name, a.type, a.status, v.company, c.name AS campaign, COALESCE(SUM(d.impressions), 0) imp, COALESCE(SUM(d.clicks), 0) clk
                FROM {p}ads a LEFT JOIN {p}ad_campaigns c ON c.id = a.campaign_id LEFT JOIN {p}advertisers v ON v.id = c.advertiser_id
                LEFT JOIN {p}ad_stats_daily d ON d.ad_id = a.id AND d.day BETWEEN ? AND ? GROUP BY a.id ORDER BY imp DESC", [$from, $to]);
        AuditService::log('export', 'ads', null, "विज्ञापन रिपोर्ट $from से $to");
        return Response::csv("ads-$from-$to.csv", ['ID', 'विज्ञापन', 'प्रकार', 'स्थिति', 'विज्ञापनदाता', 'कैंपेन', 'इम्प्रेशन', 'क्लिक', 'CTR'],
            (static function () use ($rows) {
                foreach ($rows as $r) {
                    yield [$r['id'], $r['name'], Ad::TYPES[$r['type']] ?? $r['type'], Ad::STATUSES[$r['status']] ?? $r['status'], $r['company'], $r['campaign'], $r['imp'], $r['clk'], AdService::ctr((int) $r['imp'], (int) $r['clk'])];
                }
            })());
    }

    // ---------- अंदर की मदद ----------

    private function payload(Request $request, ?array $ad): array
    {
        $v = $this->validate($request, [
            'name' => 'required|max:190', 'type' => 'required|in:' . implode(',', array_keys(Ad::TYPES)), 'campaign_id' => 'nullable|integer|exists:ad_campaigns,id',
            'target_url' => 'nullable|max:500', 'text' => 'nullable|max:300', 'video_url' => 'nullable|max:500', 'code' => 'nullable|max:60000',
            'devices' => 'required|in:all,desktop,mobile', 'start_at' => 'nullable|date', 'end_at' => 'nullable|date', 'priority' => 'required|integer|min:1|max:10',
            'max_impressions' => 'nullable|integer|min:1', 'max_clicks' => 'nullable|integer|min:1', 'status' => 'required|in:active,paused,draft',
        ], ['name' => 'नाम', 'type' => 'प्रकार', 'campaign_id' => 'कैंपेन', 'target_url' => 'लिंक', 'text' => 'टेक्स्ट', 'video_url' => 'वीडियो', 'code' => 'कोड',
            'devices' => 'डिवाइस', 'start_at' => 'शुरू', 'end_at' => 'ख़त्म', 'priority' => 'प्राथमिकता', 'max_impressions' => 'अधिकतम इम्प्रेशन', 'max_clicks' => 'अधिकतम क्लिक', 'status' => 'स्थिति']);
        $type = $v['type'];
        $isCode = in_array($type, Ad::CODE_TYPES, true);
        if (($isCode || ($ad && in_array($ad['type'], Ad::CODE_TYPES, true))) && !can('ads.manage')) {
            throw new HttpException(403, 'HTML/AdSense कोड वाले विज्ञापन के लिए "ads.manage" अनुमति चाहिए।');
        }
        $errors = [];
        $target = trim((string) $v['target_url']);
        if ($target !== '' && !EmbedService::safeLink($target)) {
            $errors['target_url'] = 'लिंक https:// (या साइट के / पाथ) से शुरू हो।';
        }
        foreach (['image', 'mobile_image'] as $f) {
            $val = $request->str($f);
            if ($val !== ($ad[$f] ?? '') && !MediaService::validImagePath($val)) {
                $errors[$f] = 'इमेज मीडिया लाइब्रेरी से चुनें।';
            }
        }
        $video = trim((string) $v['video_url']);
        if ($type === 'video' && !(EmbedService::youtubeEmbed($video) || EmbedService::streamUrl($video) || ($video !== '' && ($video === ($ad['video_url'] ?? '') || MediaService::validPath($video, 'video'))))) {
            $errors['video_url'] = 'YouTube लिंक, https .mp4 पता या मीडिया लाइब्रेरी का वीडियो चुनें।';
        }
        match ($type) {
            'image' => $request->str('image') === '' ? $errors['image'] = 'बैनर की इमेज चुनें।' : null,
            'html', 'adsense' => trim((string) $v['code']) === '' ? $errors['code'] = 'विज्ञापन का कोड पेस्ट करें।' : null,
            'link' => $target === '' || trim((string) $v['text']) === '' ? $errors['target_url'] = 'टेक्स्ट विज्ञापन के लिए टेक्स्ट और लिंक दोनों चाहिए।' : null,
            default => null,
        };
        if (in_array($type, ['image', 'link'], true) && $target === '') {
            $errors['target_url'] ??= 'विज्ञापन किस पेज पर ले जाए, वह लिंक डालें।';
        }
        $start = $v['start_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['start_at'])) : null;
        $end = $v['end_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['end_at'])) : null;
        if ($start && $end && strtotime($end) <= strtotime($start)) {
            $errors['end_at'] = 'ख़त्म होने का समय शुरू के बाद का हो।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $ids = static fn($k) => implode(',', array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($request->post()[$k] ?? []))))), 0, 50)) ?: null;
        $slots = array_map('intval', array_column(db()->all('SELECT id FROM {p}ad_slots WHERE id IN (' . (implode(',', array_filter(array_map('intval', (array) ($request->post()['slots'] ?? [])))) ?: '0') . ')'), 'id'));
        $data = [
            'name' => trim(strip_tags((string) $v['name'])), 'type' => $type, 'campaign_id' => $v['campaign_id'] ? (int) $v['campaign_id'] : null,
            'image' => $type === 'image' ? ($request->str('image') ?: null) : null, 'mobile_image' => $type === 'image' ? ($request->str('mobile_image') ?: null) : null,
            'code' => $isCode ? trim((string) $v['code']) : null, 'video_url' => $type === 'video' ? $video : null,
            'target_url' => $target ?: null, 'text' => $v['text'] ? trim(strip_tags((string) $v['text'])) : null, 'devices' => $v['devices'],
            'category_ids' => $ids('category_ids'), 'location_ids' => $ids('location_ids'), 'start_at' => $start, 'end_at' => $end, 'priority' => (int) $v['priority'],
            'max_impressions' => $v['max_impressions'] ? (int) $v['max_impressions'] : null, 'max_clicks' => $v['max_clicks'] ? (int) $v['max_clicks'] : null,
            'status' => $v['status'], 'updated_by' => auth()->id(),
        ];
        return [$data, $slots];
    }

    private function syncSlots(int $id, array $slots): void
    {
        db()->query('DELETE FROM {p}ad_placements WHERE ad_id = ?', [$id]);
        foreach ($slots as $s) {
            db()->insert('ad_placements', ['ad_id' => $id, 'slot_id' => $s]);
        }
    }

    /** कोड वाला विज्ञापन बिना ads.manage के बदला/रोका/हटाया नहीं जा सकता */
    private function guardCode(array $ad): void
    {
        if (in_array($ad['type'], Ad::CODE_TYPES, true) && !can('ads.manage')) {
            throw new HttpException(403);
        }
    }

    private function filters(Request $request): array
    {
        $w = ['1=1'];
        $p = [];
        $f = ['status' => $request->str('status'), 'slot' => $request->int('slot'), 'campaign' => $request->int('campaign'), 'type' => $request->str('type'), 'q' => $request->str('q')];
        if (isset(Ad::STATUSES[$f['status']])) {
            $w[] = 'a.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'running') {
            $w[] = "a.status = 'active' AND (a.start_at IS NULL OR a.start_at <= NOW()) AND (a.end_at IS NULL OR a.end_at > NOW())";
        } elseif ($f['status'] === 'ended') {
            $w[] = 'a.end_at IS NOT NULL AND a.end_at <= NOW()';
        }
        if ($f['slot']) {
            $w[] = 'EXISTS (SELECT 1 FROM {p}ad_placements x WHERE x.ad_id = a.id AND x.slot_id = ?)';
            $p[] = $f['slot'];
        }
        if ($f['campaign']) {
            $w[] = 'a.campaign_id = ?';
            $p[] = $f['campaign'];
        }
        if (isset(Ad::TYPES[$f['type']])) {
            $w[] = 'a.type = ?';
            $p[] = $f['type'];
        }
        if ($f['q'] !== '') {
            $w[] = 'a.name LIKE ?';
            $p[] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        return [implode(' AND ', $w), $p, $f];
    }

    /** पिछले 30 दिन: इम्प्रेशन और क्लिक (Chart.js के लिए) */
    private function chart(string $where, array $params): array
    {
        $rows = array_column(db()->all("SELECT day, SUM(impressions) i, SUM(clicks) c FROM {p}ad_stats_daily WHERE day >= CURDATE() - INTERVAL 29 DAY AND $where GROUP BY day", $params), null, 'day');
        $labels = $imp = $clk = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $labels[] = date('j/n', strtotime($d));
            $imp[] = (int) ($rows[$d]['i'] ?? 0);
            $clk[] = (int) ($rows[$d]['c'] ?? 0);
        }
        return ['labels' => $labels, 'imp' => $imp, 'clk' => $clk];
    }

    private function slotOptions(): array
    {
        return array_column(db()->all('SELECT id, name FROM {p}ad_slots ORDER BY is_system DESC, name'), 'name', 'id');
    }

    private function campaignOptions(): array
    {
        return array_column(db()->all("SELECT c.id, CONCAT(v.company, ' — ', c.name) AS label FROM {p}ad_campaigns c JOIN {p}advertisers v ON v.id = c.advertiser_id ORDER BY v.company, c.name"), 'label', 'id');
    }

    private function find(int $id): array
    {
        return Ad::find($id) ?? throw new HttpException(404);
    }
}
