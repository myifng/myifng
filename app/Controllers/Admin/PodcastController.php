<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\PodcastSeries;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\MultimediaService;
use App\Services\TaxonomyService;

/** पॉडकास्ट सीरीज़ (एपिसोड ऑडियो मॉड्यूल में) */
final class PodcastController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all("SELECT s.*, (SELECT COUNT(*) FROM {p}audio_items a WHERE a.series_id = s.id) AS episodes,
                            (SELECT COUNT(*) FROM {p}audio_items a WHERE a.series_id = s.id AND a.status = 'published' AND a.published_at <= NOW()) AS live_episodes
                            FROM {p}podcast_series s ORDER BY s.sort_order, s.title");
        return $this->view('admin/audio/podcasts', ['items' => $items, 'edit' => null, 'categories' => Category::options()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}podcast_series');
        $id = PodcastSeries::create($data);
        MultimediaService::changed();
        AuditService::log('create', 'audio', $id, 'पॉडकास्ट सीरीज़: ' . $data['title']);
        return $this->toRoute('admin.podcasts.index')->with('success', '“' . $data['title'] . '” बन गई। अब ऑडियो में “पॉडकास्ट एपिसोड” जोड़ें।');
    }

    public function edit(Request $request, int $id): Response
    {
        $edit = PodcastSeries::find($id) ?? throw new HttpException(404);
        return $this->view('admin/audio/podcasts', ['items' => [], 'edit' => $edit, 'categories' => Category::options()]);
    }

    public function update(Request $request, int $id): Response
    {
        $old = PodcastSeries::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $old);
        PodcastSeries::update($id, $data);
        MultimediaService::changed();
        AuditService::log('update', 'audio', $id, 'सीरीज़ बदली: ' . $data['title'], $old, $data);
        return $this->toRoute('admin.podcasts.index')->with('success', 'बदलाव सेव हो गए।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $s = PodcastSeries::find($id) ?? throw new HttpException(404);
        PodcastSeries::delete($id);
        MultimediaService::changed();
        AuditService::log('delete', 'audio', $id, 'सीरीज़ हटाई: ' . $s['title']);
        return $this->toRoute('admin.podcasts.index')->with('success', '“' . $s['title'] . '” हट गई। एपिसोड ऑडियो में बने रहेंगे।');
    }

    private function payload(Request $request, ?array $s): array
    {
        $v = $this->validate($request, [
            'title' => 'required|max:190', 'slug' => 'nullable|slug|max:190', 'description' => 'nullable|max:4000', 'author' => 'nullable|max:150',
            'category_id' => 'nullable|integer|exists:categories,id', 'status' => 'required|in:active,inactive', 'sort_order' => 'nullable|integer|min:0|max:100000',
        ], ['title' => 'नाम', 'slug' => 'URL (स्लग)', 'description' => 'विवरण', 'author' => 'होस्ट / लेखक', 'category_id' => 'श्रेणी', 'status' => 'स्थिति', 'sort_order' => 'क्रम']);
        $cover = $request->str('cover');
        if ($cover !== ($s['cover'] ?? '') && !MediaService::validImagePath($cover)) {
            throw new ValidationException(['cover' => 'इमेज मीडिया लाइब्रेरी से चुनें।'], $request->post());
        }
        $title = trim(strip_tags((string) $v['title']));
        $data = ['title' => $title, 'slug' => TaxonomyService::slug('podcast_series', (string) $v['slug'], $title, (int) ($s['id'] ?? 0), 'podcast'),
            'description' => $v['description'] ? strip_tags((string) $v['description']) : null, 'author' => $v['author'] ? strip_tags((string) $v['author']) : null,
            'category_id' => $v['category_id'] ? (int) $v['category_id'] : null, 'cover' => $cover ?: null, 'status' => $v['status']];
        if ($v['sort_order'] !== null) {
            $data['sort_order'] = (int) $v['sort_order'];
        }
        return $data;
    }
}
