<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\LiveTvChannel;
use App\Models\LiveTvProgram;
use App\Services\AuditService;
use App\Services\EmbedService;
use App\Services\LiveTvService;
use App\Services\MediaService;
use App\Services\TaxonomyService;

/** लाइव टीवी: चैनल (YouTube / एम्बेड / स्ट्रीम) और कार्यक्रम सूची */
final class LiveTvController extends Controller
{
    public function index(Request $request): Response
    {
        $channels = db()->all('SELECT c.*, (SELECT COUNT(*) FROM {p}live_tv_programs p WHERE p.channel_id = c.id) AS programs FROM {p}live_tv_channels c ORDER BY c.is_default DESC, c.sort_order, c.name');
        foreach ($channels as &$c) {
            $c['valid'] = LiveTvService::player($c) !== null;
            $c['now'] = LiveTvService::current((int) $c['id']);
        }
        unset($c);
        return $this->view('admin/live-tv/index', ['channels' => $channels, 'fallback' => (string) setting('live_tv_url')]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/live-tv/form', ['channel' => null, 'programs' => []]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}live_tv_channels');
        $id = LiveTvChannel::create($data);
        $this->afterSave($id, $data);
        AuditService::log('create', 'live_tv', $id, 'चैनल: ' . $data['name'], null, $data);
        return $this->toRoute('admin.live_tv.edit', ['id' => $id])->with('success', 'चैनल बन गया। अब नीचे कार्यक्रम जोड़ें।');
    }

    public function edit(Request $request, int $id): Response
    {
        $channel = LiveTvChannel::find($id) ?? throw new HttpException(404);
        $programs = db()->all('SELECT * FROM {p}live_tv_programs WHERE channel_id = ? ORDER BY start_time', [$id]);
        return $this->view('admin/live-tv/form', ['channel' => $channel, 'programs' => $programs]);
    }

    public function update(Request $request, int $id): Response
    {
        $channel = LiveTvChannel::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $channel);
        LiveTvChannel::update($id, $data);
        $this->afterSave($id, $data);
        AuditService::log('update', 'live_tv', $id, 'चैनल बदला: ' . $data['name'], $channel, $data);
        return $this->toRoute('admin.live_tv.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।');
    }

    /** "अभी लाइव" चालू/बंद (सूची से एक क्लिक) */
    public function toggle(Request $request, int $id): Response
    {
        $channel = LiveTvChannel::find($id) ?? throw new HttpException(404);
        LiveTvChannel::update($id, ['is_live' => $channel['is_live'] ? 0 : 1]);
        LiveTvService::changed();
        AuditService::log('toggle', 'live_tv', $id, $channel['name'] . ': ' . ($channel['is_live'] ? 'लाइव बंद' : 'लाइव चालू'));
        return $this->back()->with('success', $channel['is_live'] ? 'चैनल ऑफ़-एयर: हेडर का LIVE बटन छिप जाएगा।' : 'चैनल अभी लाइव है।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $channel = LiveTvChannel::find($id) ?? throw new HttpException(404);
        LiveTvChannel::delete($id);
        LiveTvService::changed();
        AuditService::log('delete', 'live_tv', $id, 'चैनल हटाया: ' . $channel['name'], $channel);
        return $this->toRoute('admin.live_tv.index')->with('success', '“' . $channel['name'] . '” और उसके कार्यक्रम हट गए।');
    }

    // ---------- कार्यक्रम ----------

    public function programStore(Request $request, int $id): Response
    {
        LiveTvChannel::find($id) ?? throw new HttpException(404);
        $data = $this->programPayload($request);
        $pid = LiveTvProgram::create($data + ['channel_id' => $id]);
        LiveTvService::changed();
        AuditService::log('program_add', 'live_tv', $id, 'कार्यक्रम: ' . $data['title']);
        return $this->toRoute('admin.live_tv.edit', ['id' => $id])->with('success', 'कार्यक्रम जुड़ गया।');
    }

    public function programUpdate(Request $request, int $pid): Response
    {
        $p = LiveTvProgram::find($pid) ?? throw new HttpException(404);
        $data = $this->programPayload($request);
        LiveTvProgram::update($pid, $data);
        LiveTvService::changed();
        AuditService::log('program_edit', 'live_tv', (int) $p['channel_id'], 'कार्यक्रम बदला: ' . $data['title'], $p, $data);
        return $this->toRoute('admin.live_tv.edit', ['id' => $p['channel_id']])->with('success', 'कार्यक्रम बदल गया।');
    }

    public function programDestroy(Request $request, int $pid): Response
    {
        $p = LiveTvProgram::find($pid) ?? throw new HttpException(404);
        LiveTvProgram::delete($pid);
        LiveTvService::changed();
        AuditService::log('program_delete', 'live_tv', (int) $p['channel_id'], 'कार्यक्रम हटाया: ' . $p['title']);
        return $this->toRoute('admin.live_tv.edit', ['id' => $p['channel_id']])->with('success', 'कार्यक्रम हट गया।');
    }

    private function afterSave(int $id, array $data): void
    {
        if ($data['is_default']) {
            db()->query('UPDATE {p}live_tv_channels SET is_default = 0 WHERE id <> ?', [$id]);
        } elseif (!(int) db()->value('SELECT COUNT(*) FROM {p}live_tv_channels WHERE is_default = 1')) {
            db()->query('UPDATE {p}live_tv_channels SET is_default = 1 WHERE id = ?', [$id]); // कोई तो डिफ़ॉल्ट रहे
        }
        LiveTvService::changed();
    }

    private function payload(Request $request, ?array $c): array
    {
        $v = $this->validate($request, [
            'name' => 'required|max:150', 'slug' => 'nullable|slug|max:170', 'source_type' => 'required|in:' . implode(',', array_keys(LiveTvChannel::SOURCES)),
            'source_url' => 'required|max:2000', 'description' => 'nullable|max:500', 'status' => 'required|in:active,inactive', 'sort_order' => 'nullable|integer|min:0|max:100000',
        ], ['name' => 'चैनल का नाम', 'slug' => 'URL (स्लग)', 'source_type' => 'स्रोत', 'source_url' => 'लिंक / कोड', 'description' => 'विवरण', 'status' => 'स्थिति', 'sort_order' => 'क्रम']);
        $name = trim(strip_tags((string) $v['name']));
        $src = trim((string) $v['source_url']);
        $errors = [];
        $clean = match ($v['source_type']) {
            'youtube' => EmbedService::youtubeEmbed($src) ? $src : null,
            'embed' => EmbedService::iframeSrc($src),
            'stream' => EmbedService::streamUrl($src),
        };
        if ($clean === null || mb_strlen($clean) > 500) {
            $errors['source_url'] = [
                'youtube' => 'YouTube वीडियो/लाइव का लिंक (youtu.be/…, youtube.com/watch?v=…, /live/…) या चैनल का लिंक (youtube.com/channel/UC…) डालें।',
                'embed' => 'https:// वाला iframe पता या पूरा <iframe …> कोड पेस्ट करें (सिर्फ़ src लिया जाएगा)।',
                'stream' => 'https:// वाला .m3u8 (HLS) या .mp4 पता डालें।',
            ][$v['source_type']];
        }
        $logo = $request->str('logo');
        if ($logo !== ($c['logo'] ?? '') && !MediaService::validImagePath($logo)) {
            $errors['logo'] = 'इमेज मीडिया लाइब्रेरी से चुनें।';
        }
        if ($name === '') {
            $errors['name'] = 'नाम में सादा टेक्स्ट लिखें।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $data = ['name' => $name, 'slug' => TaxonomyService::slug('live_tv_channels', (string) $v['slug'], $name, (int) ($c['id'] ?? 0), 'channel'),
            'source_type' => $v['source_type'], 'source_url' => $clean, 'logo' => $logo ?: null, 'description' => $v['description'] ? strip_tags((string) $v['description']) : null,
            'is_default' => $request->bool('is_default') ? 1 : 0, 'is_live' => $request->bool('is_live') ? 1 : 0, 'status' => $v['status']];
        if ($v['sort_order'] !== null) {
            $data['sort_order'] = (int) $v['sort_order'];
        }
        return $data;
    }

    private function programPayload(Request $request): array
    {
        $v = $this->validate($request, [
            'title' => 'required|max:190', 'host' => 'nullable|max:150', 'description' => 'nullable|max:500',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:00)?$/'], 'end_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:00)?$/'], 'status' => 'required|in:active,inactive',
        ], ['title' => 'कार्यक्रम', 'host' => 'एंकर', 'description' => 'विवरण', 'start_time' => 'शुरू', 'end_time' => 'ख़त्म', 'status' => 'स्थिति']);
        $days = array_values(array_unique(array_filter(array_map('strval', (array) ($request->post()['days'] ?? [])), static fn($d) => preg_match('/^[0-6]$/', $d))));
        if (!$days) {
            throw new ValidationException(['days' => 'कम से कम एक दिन चुनें।'], $request->post());
        }
        $v['start_time'] = substr((string) $v['start_time'], 0, 5);
        $v['end_time'] = substr((string) $v['end_time'], 0, 5);
        if ($v['start_time'] === $v['end_time']) {
            throw new ValidationException(['end_time' => 'ख़त्म होने का समय शुरू से अलग हो।'], $request->post());
        }
        sort($days);
        return ['title' => trim(strip_tags((string) $v['title'])), 'host' => $v['host'] ? strip_tags((string) $v['host']) : null, 'description' => $v['description'] ? strip_tags((string) $v['description']) : null,
            'days' => implode(',', $days), 'start_time' => $v['start_time'] . ':00', 'end_time' => $v['end_time'] . ':00', 'status' => $v['status']];
    }
}
