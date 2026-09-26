<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Repositories\MediaRepository;
use App\Services\AuditService;
use App\Services\MediaService;

/** मीडिया लाइब्रेरी: अपलोड (कई फ़ाइलें), विवरण, बदलना, फ़ोल्डर, ट्रैश, स्थायी रूप से हटाना, पिकर (JSON) */
final class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new MediaRepository();
        $f = $this->filters($request);
        return $this->view('admin/media/index', [
            'media' => $repo->paginate($f, max(1, $request->int('page', 1))), 'filters' => $f,
            'counts' => $repo->counts(), 'folders' => $repo->folders(), 'months' => $repo->months(),
        ]);
    }

    private function filters(Request $request): array
    {
        return ['kind' => $request->str('kind'), 'folder' => $request->str('folder'), 'month' => $request->str('month'), 'q' => $request->str('q'), 'trash' => $request->str('view') === 'trash'];
    }

    /** पिकर के लिए JSON (सिर्फ़ ट्रैश के बाहर वाली) */
    public function browse(Request $request): Response
    {
        $f = $this->filters($request);
        $f['trash'] = false;
        $p = (new MediaRepository())->paginate($f, max(1, $request->int('page', 1)), 30);
        return $this->json(['items' => array_map([MediaService::class, 'toJson'], $p->items), 'page' => $p->page, 'has_more' => $p->page < $p->pages]);
    }

    /** एक फ़ाइल (AJAX से कई बार; बिना JS के एक फ़ॉर्म से) */
    public function store(Request $request): Response
    {
        $file = $request->file('file');
        $folder = $this->folderId($request->int('folder_id'));
        $kind = in_array($request->str('only'), ['image', 'video', 'audio', 'document'], true) ? $request->str('only') : null;
        if (!$file) {
            return $this->fail($request, 'कोई फ़ाइल नहीं चुनी गई।');
        }
        $r = MediaService::upload($file, $folder, ['alt' => $request->str('alt'), 'credit' => $request->str('credit')], $kind);
        if (!$r['ok']) {
            return $this->fail($request, $r['error']);
        }
        $m = $r['media'];
        AuditService::log('upload', 'media', $m['id'], 'मीडिया अपलोड: ' . $m['original_name'] . ' (' . MediaService::humanSize((int) $m['size']) . ')');
        if ($request->wantsJson()) {
            return $this->json(['ok' => true, 'item' => MediaService::toJson($m), 'warning' => $r['warning'], 'edit' => route('admin.media.edit', ['id' => $m['id']])]);
        }
        return $this->toRoute('admin.media.edit', ['id' => $m['id']])->with($r['warning'] ? 'warning' : 'success', $r['warning'] ?: 'फ़ाइल अपलोड हो गई। अब alt टेक्स्ट और कैप्शन भरें।');
    }

    private function fail(Request $request, string $msg): Response
    {
        return $request->wantsJson() ? $this->json(['ok' => false, 'message' => $msg], 422) : $this->back()->with('danger', $msg);
    }

    private function folderId(int $id): ?int
    {
        return $id > 0 && MediaFolder::find($id) ? $id : null;
    }

    public function edit(Request $request, int $id): Response
    {
        $m = Media::find($id, true) ?? throw new HttpException(404);
        return $this->view('admin/media/edit', ['m' => $m, 'folders' => (new MediaRepository())->folders(), 'usage' => $this->usage($m)]);
    }

    /** यह फ़ाइल कहाँ लगी है (श्रेणी/टॉपिक/पेज; Phase 4 में ख़बरें) */
    private function usage(array $m): array
    {
        $out = [];
        $f = $m['file'];
        foreach (db()->all('SELECT id, name FROM {p}categories WHERE image = ?', [$f]) as $r) {
            $out[] = ['श्रेणी', $r['name'], route('admin.categories.edit', ['id' => $r['id']])];
        }
        foreach (db()->all('SELECT id, name FROM {p}topics WHERE image = ? OR banner = ?', [$f, $f]) as $r) {
            $out[] = ['टॉपिक', $r['name'], route('admin.topics.edit', ['id' => $r['id']])];
        }
        $base = preg_replace('/\.[a-z0-9]+$/', '', $f);
        foreach (db()->all('SELECT id, title FROM {p}pages WHERE deleted_at IS NULL AND content LIKE ?', ['%' . addcslashes($base, '%_\\') . '%']) as $r) {
            $out[] = ['पेज', $r['title'], route('admin.pages.edit', ['id' => $r['id']])];
        }
        return $out;
    }

    public function update(Request $request, int $id): Response
    {
        $m = Media::find($id) ?? throw new HttpException(404);
        $v = $this->validate($request, [
            'title' => 'nullable|max:190', 'alt' => 'nullable|max:255', 'caption' => 'nullable|max:500', 'credit' => 'nullable|max:150', 'keywords' => 'nullable|max:255',
            'folder_id' => 'nullable|integer',
        ], ['title' => 'शीर्षक', 'alt' => 'Alt टेक्स्ट', 'caption' => 'कैप्शन', 'credit' => 'क्रेडिट', 'keywords' => 'कीवर्ड', 'folder_id' => 'फ़ोल्डर']);
        $data = ['title' => $v['title'], 'alt' => $v['alt'], 'caption' => $v['caption'], 'credit' => $v['credit'], 'keywords' => $v['keywords'], 'folder_id' => $this->folderId((int) $v['folder_id'])];
        Media::update($id, $data);
        AuditService::log('update', 'media', $id, 'मीडिया विवरण बदला: ' . ($data['title'] ?: $m['original_name']), $m, $data);
        return $request->wantsJson()
            ? $this->json(['ok' => true, 'message' => 'सेव हो गया।', 'item' => MediaService::toJson(Media::find($id))])
            : $this->toRoute('admin.media.edit', ['id' => $id])->with('success', 'विवरण सेव हो गया।');
    }

    public function replace(Request $request, int $id): Response
    {
        $m = Media::find($id) ?? throw new HttpException(404);
        $file = $request->file('file');
        if (!$file) {
            throw new ValidationException(['file' => 'नई फ़ाइल चुनें।'], []);
        }
        $r = MediaService::replace($m, $file);
        if (!$r['ok']) {
            throw new ValidationException(['file' => $r['error']], []);
        }
        AuditService::log('replace', 'media', $id, 'मीडिया फ़ाइल बदली: ' . ($m['title'] ?: $m['original_name']) . ' (पुरानी फ़ाइल सुरक्षित रखी गई)');
        $msg = 'फ़ाइल बदल गई। पुरानी फ़ाइल सर्वर पर सुरक्षित रखी गई है।' . (!empty($r['renamed']) ? ' फ़ाइल का प्रकार बदला है, इसलिए इसका URL भी बदला; जहाँ यह पहले से लगी है वहाँ दोबारा चुनें।' : '');
        return $this->toRoute('admin.media.edit', ['id' => $id])->with(!empty($r['renamed']) || $r['warning'] ? 'warning' : 'success', $msg . ($r['warning'] ? ' ' . $r['warning'] : ''));
    }

    public function regenerate(Request $request, int $id): Response
    {
        $m = Media::find($id) ?? throw new HttpException(404);
        $warn = MediaService::regenerate($m);
        AuditService::log('regenerate', 'media', $id, 'इमेज के आकार दोबारा बने: ' . ($m['title'] ?: $m['original_name']));
        return $this->back()->with($warn ? 'warning' : 'success', $warn ?: 'छोटे आकार (thumbnail, WebP, वॉटरमार्क) मौजूदा सेटिंग से दोबारा बन गए।');
    }

    public function trash(Request $request, int $id): Response
    {
        $m = Media::find($id) ?? throw new HttpException(404);
        Media::delete($id);
        AuditService::log('trash', 'media', $id, 'मीडिया ट्रैश में: ' . ($m['title'] ?: $m['original_name']));
        return $this->toRoute('admin.media.index')->with('success', 'फ़ाइल ट्रैश में चली गई। वेबसाइट पर जहाँ लगी है वहाँ अभी भी दिखेगी, जब तक स्थायी रूप से न हटाई जाए।');
    }

    public function restore(Request $request, int $id): Response
    {
        $m = Media::find($id, true) ?? throw new HttpException(404);
        Media::restore($id);
        AuditService::log('restore', 'media', $id, 'मीडिया वापस लाया: ' . ($m['title'] ?: $m['original_name']));
        return $this->back()->with('success', 'फ़ाइल वापस आ गई।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $m = Media::find($id, true) ?? throw new HttpException(404);
        if ($m['deleted_at'] === null) {
            return $this->back()->with('danger', 'स्थायी रूप से हटाने से पहले फ़ाइल को ट्रैश में डालें।');
        }
        MediaService::purge($m);
        AuditService::log('delete', 'media', $id, 'मीडिया स्थायी रूप से हटाया: ' . $m['original_name'], ['file' => $m['file'], 'original_name' => $m['original_name']]);
        return $this->toRoute('admin.media.index', [])->with('success', 'फ़ाइल हमेशा के लिए हटा दी गई।');
    }

    /** बल्क: फ़ोल्डर में ले जाएँ / ट्रैश / वापस / स्थायी रूप से हटाएँ */
    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $action = $request->str('action');
        $perm = ['move' => 'media.edit', 'trash' => 'media.delete', 'restore' => 'media.delete', 'delete' => 'media.manage'][$action] ?? null;
        if (!$ids || !$perm) {
            return $this->back()->with('warning', 'पहले फ़ाइलें और काम चुनें।');
        }
        $this->authorize($perm);
        $in = Database::in($ids);
        switch ($action) {
            case 'move':
                $folder = $this->folderId($request->int('folder_id'));
                $n = db()->query("UPDATE {p}media SET folder_id = ? WHERE id IN ($in)", [$folder, ...$ids])->rowCount();
                break;
            case 'trash':
                $n = db()->query("UPDATE {p}media SET deleted_at = NOW() WHERE deleted_at IS NULL AND id IN ($in)", $ids)->rowCount();
                break;
            case 'restore':
                $n = db()->query("UPDATE {p}media SET deleted_at = NULL WHERE id IN ($in)", $ids)->rowCount();
                break;
            default:
                $rows = db()->all("SELECT * FROM {p}media WHERE deleted_at IS NOT NULL AND id IN ($in)", $ids);
                foreach ($rows as $m) {
                    MediaService::purge($m);
                }
                $n = count($rows);
        }
        AuditService::log('bulk_' . $action, 'media', implode(',', $ids), "$n फ़ाइलों पर बल्क काम: $action");
        return $this->back()->with('success', "$n फ़ाइलें अपडेट हुईं।");
    }

    // ---------- फ़ोल्डर (media.manage) ----------
    public function folderStore(Request $request): Response
    {
        $name = trim(mb_substr(strip_tags($request->str('name')), 0, 100));
        if ($name === '') {
            return $this->back()->with('danger', 'फ़ोल्डर का नाम लिखें।');
        }
        if (db()->value('SELECT id FROM {p}media_folders WHERE name = ?', [$name])) {
            return $this->back()->with('warning', 'इस नाम का फ़ोल्डर पहले से है।');
        }
        $id = MediaFolder::create(['name' => $name, 'created_by' => auth()->id()]);
        AuditService::log('create', 'media', $id, 'मीडिया फ़ोल्डर बना: ' . $name);
        return $this->redirect(route('admin.media.index') . '?folder=' . $id)->with('success', 'फ़ोल्डर “' . $name . '” बन गया।');
    }

    public function folderUpdate(Request $request, int $id): Response
    {
        $f = MediaFolder::find($id) ?? throw new HttpException(404);
        $name = trim(mb_substr(strip_tags($request->str('name')), 0, 100));
        if ($name === '') {
            return $this->back()->with('danger', 'फ़ोल्डर का नाम लिखें।');
        }
        MediaFolder::update($id, ['name' => $name]);
        AuditService::log('update', 'media', $id, "मीडिया फ़ोल्डर का नाम बदला: {$f['name']} → $name");
        return $this->back()->with('success', 'फ़ोल्डर का नाम बदल गया।');
    }

    public function folderDestroy(Request $request, int $id): Response
    {
        $f = MediaFolder::find($id) ?? throw new HttpException(404);
        // फ़ाइलें नहीं हटतीं; "बिना फ़ोल्डर" में चली जाती हैं (FK: ON DELETE SET NULL)
        MediaFolder::delete($id);
        AuditService::log('delete', 'media', $id, 'मीडिया फ़ोल्डर हटाया: ' . $f['name']);
        return $this->toRoute('admin.media.index')->with('success', 'फ़ोल्डर हटा। उसकी फ़ाइलें “बिना फ़ोल्डर” में हैं।');
    }
}
