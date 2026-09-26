<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Str;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Services\AuditService;
use App\Services\HtmlSanitizer;
use App\Services\MenuService;
use App\Services\UploadService;
use App\Validators\PageValidator;

/** पेज CMS: बनाना, बदलना, प्रीव्यू, कॉपी, ट्रैश, रीस्टोर, स्थायी रूप से हटाना, बल्क */
final class PageController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new PageRepository();
        $filters = ['status' => $request->str('status'), 'q' => $request->str('q')];
        return $this->view('admin/pages/index', [
            'pages' => $repo->paginate($filters, max(1, $request->int('page', 1))),
            'filters' => $filters,
            'counts' => $repo->counts(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/pages/form', ['page' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['created_by'] = auth()->id();
        $id = Page::create($data);
        MenuService::clearCache();
        AuditService::log('create', 'pages', $id, 'नया पेज: ' . $data['title'], null, array_diff_key($data, ['content' => 1]));
        return $this->toRoute('admin.pages.edit', ['id' => $id])->with('success', 'पेज “' . $data['title'] . '” बन गया' . ($data['status'] === 'published' ? ' और प्रकाशित हो गया।' : ' (ड्राफ़्ट)।'));
    }

    public function edit(Request $request, int $id): Response
    {
        $page = Page::find($id) ?? throw new HttpException(404);
        return $this->view('admin/pages/form', ['page' => $page]);
    }

    public function update(Request $request, int $id): Response
    {
        $page = Page::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $page);
        Page::update($id, $data);
        MenuService::clearCache();
        $note = $page['slug'] !== $data['slug'] && $page['status'] === 'published' ? ' URL बदला है; पुराना लिंक अब नहीं चलेगा (रीडायरेक्ट मैनेजर Phase 10 में)।' : '';
        AuditService::log('update', 'pages', $id, 'पेज बदला: ' . $data['title'], $page, $data);
        return $this->toRoute('admin.pages.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।' . $note);
    }

    /** फ़ॉर्म → सुरक्षित डेटा (वैलिडेशन, स्लग, sanitize, अपलोड, प्रकाशन अनुमति) */
    private function payload(Request $request, ?array $page): array
    {
        $v = $this->validate($request, PageValidator::rules(), PageValidator::LABELS);
        if (!isset(Page::ROBOTS[$v['robots']])) {
            throw new \App\Core\ValidationException(['robots' => 'सर्च इंजन का चुना गया मान मान्य नहीं है।'], $request->post());
        }
        $slug = $v['slug'] ?: Str::slug($v['title']);
        $slug = Page::uniqueSlug($slug !== '' ? $slug : 'page', (int) ($page['id'] ?? 0));

        // प्रकाशित करने की अनुमति न हो तो स्थिति नहीं बदलती
        $status = $v['status'];
        if (!can('pages.publish')) {
            $status = $page['status'] ?? 'draft';
        }
        $data = [
            'title' => $v['title'], 'slug' => $slug, 'excerpt' => $v['excerpt'], 'template' => $v['template'], 'status' => $status,
            'content' => HtmlSanitizer::clean((string) $request->input('content', ''), (string) config('app.url')),
            'show_header' => $request->bool('show_header') ? 1 : 0, 'show_footer' => $request->bool('show_footer') ? 1 : 0,
            'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'meta_keywords' => $v['meta_keywords'], 'robots' => $v['robots'],
            'updated_by' => auth()->id(),
        ];
        if ($status === 'published' && empty($page['published_at'])) {
            $data['published_at'] = now();
        }
        foreach (['featured_image' => 1600, 'og_image' => 1200] as $field => $w) {
            if ($file = $request->file($field)) {
                $r = UploadService::store($file, 'image', 'pages', BASE_PATH . '/public/uploads', $w);
                if (!$r['ok']) {
                    throw new \App\Core\ValidationException([$field => $r['error']], $request->post());
                }
                $data[$field] = $r['path'];
            } elseif ($request->bool('remove_' . $field)) {
                $data[$field] = null;
            }
        }
        return $data;
    }

    public function duplicate(Request $request, int $id): Response
    {
        $page = Page::find($id) ?? throw new HttpException(404);
        $copy = array_intersect_key($page, array_flip(['title', 'excerpt', 'content', 'featured_image', 'template', 'show_header', 'show_footer', 'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'robots', 'language_id']));
        $copy['title'] .= ' (कॉपी)';
        $copy['slug'] = Page::uniqueSlug($page['slug'] . '-copy');
        $copy['status'] = 'draft';
        $copy['created_by'] = $copy['updated_by'] = auth()->id();
        $new = Page::create($copy);
        AuditService::log('duplicate', 'pages', $new, 'पेज की कॉपी: ' . $page['title']);
        return $this->toRoute('admin.pages.edit', ['id' => $new])->with('success', 'कॉपी ड्राफ़्ट के रूप में बन गई।');
    }

    public function trash(Request $request, int $id): Response
    {
        $page = Page::find($id) ?? throw new HttpException(404);
        Page::delete($id);
        MenuService::clearCache();
        AuditService::log('trash', 'pages', $id, 'पेज ट्रैश में: ' . $page['title']);
        return $this->toRoute('admin.pages.index')->with('success', '“' . $page['title'] . '” ट्रैश में चला गया। ज़रूरत हो तो ट्रैश से वापस ला सकते हैं।');
    }

    public function restore(Request $request, int $id): Response
    {
        $page = Page::find($id, true) ?? throw new HttpException(404);
        Page::restore($id);
        MenuService::clearCache();
        AuditService::log('restore', 'pages', $id, 'पेज वापस लाया: ' . $page['title']);
        return $this->back()->with('success', '“' . $page['title'] . '” वापस आ गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $page = Page::find($id, true) ?? throw new HttpException(404);
        if ($page['deleted_at'] === null) {
            return $this->back()->with('danger', 'स्थायी रूप से हटाने से पहले पेज को ट्रैश में डालें।');
        }
        Page::forceDelete($id);
        AuditService::log('delete', 'pages', $id, 'पेज स्थायी रूप से हटाया: ' . $page['title'], ['title' => $page['title'], 'slug' => $page['slug']]);
        return $this->back()->with('success', 'पेज स्थायी रूप से हटा दिया गया।');
    }

    /** बल्क: publish / draft / trash / restore / delete */
    public function bulk(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $action = $request->str('action');
        $perm = ['publish' => 'pages.publish', 'draft' => 'pages.publish', 'trash' => 'pages.delete', 'restore' => 'pages.delete', 'delete' => 'pages.delete'][$action] ?? null;
        if (!$ids || !$perm) {
            return $this->back()->with('warning', 'पहले पेज और काम चुनें।');
        }
        $this->authorize($perm);
        $in = \App\Core\Database::in($ids);
        $n = match ($action) {
            'publish' => db()->query("UPDATE {p}pages SET status = 'published', published_at = COALESCE(published_at, NOW()), updated_by = ? WHERE deleted_at IS NULL AND id IN ($in)", [auth()->id(), ...$ids])->rowCount(),
            'draft' => db()->query("UPDATE {p}pages SET status = 'draft', updated_by = ? WHERE deleted_at IS NULL AND id IN ($in)", [auth()->id(), ...$ids])->rowCount(),
            'trash' => db()->query("UPDATE {p}pages SET deleted_at = NOW() WHERE deleted_at IS NULL AND id IN ($in)", $ids)->rowCount(),
            'restore' => db()->query("UPDATE {p}pages SET deleted_at = NULL WHERE id IN ($in)", $ids)->rowCount(),
            'delete' => db()->query("DELETE FROM {p}pages WHERE deleted_at IS NOT NULL AND id IN ($in)", $ids)->rowCount(),
        };
        MenuService::clearCache();
        AuditService::log('bulk_' . $action, 'pages', implode(',', $ids), "$n पेजों पर बल्क काम: $action");
        return $this->back()->with('success', "$n पेज अपडेट हुए।");
    }

    /** प्रीव्यू: ड्राफ़्ट भी, वेबसाइट के लेआउट में */
    public function preview(Request $request, int $id): Response
    {
        $page = Page::find($id) ?? throw new HttpException(404);
        return (new \App\Controllers\Front\PageController())->render($page, true);
    }
}
