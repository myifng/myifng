<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\VideoPlaylist;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\MultimediaService;
use App\Services\TaxonomyService;

/** वीडियो प्लेलिस्ट और शो */
final class PlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all("SELECT p.*, (SELECT COUNT(*) FROM {p}videos v WHERE v.playlist_id = p.id) AS videos,
                            (SELECT COUNT(*) FROM {p}videos v WHERE v.playlist_id = p.id AND v.status = 'published' AND v.published_at <= NOW()) AS live_videos
                            FROM {p}video_playlists p ORDER BY p.sort_order, p.name");
        return $this->view('admin/videos/playlists', ['items' => $items, 'edit' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}video_playlists');
        $id = VideoPlaylist::create($data);
        MultimediaService::changed();
        AuditService::log('create', 'videos', $id, VideoPlaylist::TYPES[$data['type']] . ': ' . $data['name']);
        return $this->toRoute('admin.playlists.index')->with('success', '“' . $data['name'] . '” बन गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        $edit = VideoPlaylist::find($id) ?? throw new HttpException(404);
        return $this->view('admin/videos/playlists', ['items' => [], 'edit' => $edit]);
    }

    public function update(Request $request, int $id): Response
    {
        $old = VideoPlaylist::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $old);
        VideoPlaylist::update($id, $data);
        MultimediaService::changed();
        AuditService::log('update', 'videos', $id, 'प्लेलिस्ट बदली: ' . $data['name'], $old, $data);
        return $this->toRoute('admin.playlists.index')->with('success', 'बदलाव सेव हो गए।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $p = VideoPlaylist::find($id) ?? throw new HttpException(404);
        VideoPlaylist::delete($id); // वीडियो बने रहते हैं (playlist_id NULL)
        MultimediaService::changed();
        AuditService::log('delete', 'videos', $id, 'प्लेलिस्ट हटाई: ' . $p['name']);
        return $this->toRoute('admin.playlists.index')->with('success', '“' . $p['name'] . '” हट गई। उसके वीडियो बने रहेंगे।');
    }

    private function payload(Request $request, ?array $p): array
    {
        $v = $this->validate($request, [
            'name' => 'required|max:150', 'slug' => 'nullable|slug|max:170', 'type' => 'required|in:playlist,show', 'description' => 'nullable|max:1000',
            'status' => 'required|in:active,inactive', 'sort_order' => 'nullable|integer|min:0|max:100000',
        ], ['name' => 'नाम', 'slug' => 'URL (स्लग)', 'type' => 'प्रकार', 'description' => 'विवरण', 'status' => 'स्थिति', 'sort_order' => 'क्रम']);
        $cover = $request->str('cover');
        if ($cover !== ($p['cover'] ?? '') && !MediaService::validImagePath($cover)) {
            throw new ValidationException(['cover' => 'इमेज मीडिया लाइब्रेरी से चुनें।'], $request->post());
        }
        $name = trim(strip_tags((string) $v['name']));
        $data = ['name' => $name, 'slug' => TaxonomyService::slug('video_playlists', (string) $v['slug'], $name, (int) ($p['id'] ?? 0), 'playlist'), 'type' => $v['type'],
            'description' => $v['description'] ? strip_tags((string) $v['description']) : null, 'cover' => $cover ?: null, 'status' => $v['status']];
        if ($v['sort_order'] !== null) {
            $data['sort_order'] = (int) $v['sort_order'];
        }
        return $data;
    }
}
