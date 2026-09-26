<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\HomeSection;
use App\Services\AuditService;
use App\Services\HomepageService;
use App\Services\MenuService;

/** होमपेज बिल्डर: सेक्शन जोड़ें, बदलें, क्रम, चालू/बंद, कॉपी, हटाएँ */
final class HomepageController extends Controller
{
    public function index(Request $request): Response
    {
        $tables = MenuService::existingTables();
        return $this->view('admin/homepage/index', [
            'sections' => HomepageService::sections(),
            'blocks' => HomepageService::blocks(),
            'categories' => in_array('categories', $tables, true) ? db()->all('SELECT id, name FROM {p}categories ORDER BY name') : [],
            'locations' => in_array('locations', $tables, true) ? db()->all('SELECT id, name FROM {p}locations ORDER BY name LIMIT 500') : [],
        ]);
    }

    public function store(Request $request): Response
    {
        $type = $request->str('block_type');
        $block = HomepageService::block($type) ?? throw new HttpException(422, 'ब्लॉक का प्रकार मान्य नहीं है।');
        if (!empty($block['manage'])) {
            $this->authorize('homepage.manage');
        }
        $id = HomeSection::create([
            'block_type' => $type, 'title' => $block['label'], 'settings' => json_encode(HomepageService::defaults($type), JSON_UNESCAPED_UNICODE),
            'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}home_sections'), 'created_by' => auth()->id(),
        ]);
        HomepageService::clearCache();
        AuditService::log('create', 'homepage', $id, 'होमपेज सेक्शन जोड़ा: ' . $block['label']);
        return $this->redirect(route('admin.homepage') . '#section-' . $id)->with('success', '“' . $block['label'] . '” सेक्शन नीचे जुड़ गया। इसकी सेटिंग बदलें और सेव करें।');
    }

    public function update(Request $request, int $id): Response
    {
        $s = HomeSection::find($id) ?? throw new HttpException(404);
        $block = HomepageService::block($s['block_type']) ?? throw new HttpException(422);
        if (!empty($block['manage'])) {
            $this->authorize('homepage.manage');
        }
        $title = trim(mb_substr(strip_tags($request->str('title')), 0, 150));
        $settings = HomepageService::normalize($s['block_type'], (array) $request->input('settings', []));
        foreach ($block['fields'] as $name => $f) {
            if (!empty($f['required']) && empty($settings[$name])) {
                return $this->redirect(route('admin.homepage') . '#section-' . $id)->with('danger', '“' . $f['label'] . '” चुनना ज़रूरी है।');
            }
        }
        $data = ['title' => $title, 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'show_desktop' => $request->bool('show_desktop') ? 1 : 0, 'show_mobile' => $request->bool('show_mobile') ? 1 : 0, 'is_active' => $request->bool('is_active') ? 1 : 0];
        HomeSection::update($id, $data);
        HomepageService::clearCache();
        AuditService::log('update', 'homepage', $id, 'होमपेज सेक्शन बदला: ' . ($title ?: $block['label']), $s, $data);
        return $this->redirect(route('admin.homepage') . '#section-' . $id)->with('success', 'सेक्शन सेव हो गया।');
    }

    public function toggle(Request $request, int $id): Response
    {
        $s = HomeSection::find($id) ?? throw new HttpException(404);
        HomeSection::update($id, ['is_active' => $s['is_active'] ? 0 : 1]);
        HomepageService::clearCache();
        AuditService::log($s['is_active'] ? 'disable' : 'enable', 'homepage', $id, 'सेक्शन ' . ($s['is_active'] ? 'बंद' : 'चालू') . ': ' . $s['title']);
        return $request->wantsJson() ? $this->json(['ok' => true, 'active' => !$s['is_active']]) : $this->redirect(route('admin.homepage') . '#section-' . $id);
    }

    public function duplicate(Request $request, int $id): Response
    {
        $s = HomeSection::find($id) ?? throw new HttpException(404);
        if (!empty(HomepageService::block($s['block_type'])['manage'])) {
            $this->authorize('homepage.manage');
        }
        db()->query('UPDATE {p}home_sections SET sort_order = sort_order + 1 WHERE sort_order > ?', [$s['sort_order']]);
        $new = HomeSection::create(['block_type' => $s['block_type'], 'title' => $s['title'] . ' (कॉपी)', 'settings' => $s['settings'], 'show_desktop' => $s['show_desktop'],
            'show_mobile' => $s['show_mobile'], 'is_active' => 0, 'sort_order' => $s['sort_order'] + 1, 'created_by' => auth()->id()]);
        HomepageService::clearCache();
        AuditService::log('duplicate', 'homepage', $new, 'सेक्शन की कॉपी: ' . $s['title']);
        return $this->redirect(route('admin.homepage') . '#section-' . $new)->with('success', 'कॉपी बन गई (अभी बंद है)।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $s = HomeSection::find($id) ?? throw new HttpException(404);
        HomeSection::delete($id);
        HomepageService::clearCache();
        AuditService::log('delete', 'homepage', $id, 'सेक्शन हटाया: ' . $s['title'], $s);
        return $this->toRoute('admin.homepage')->with('success', 'सेक्शन हटा दिया गया।');
    }

    /** ड्रैग से क्रम: ids = [3,1,2] */
    public function reorder(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $valid = array_map('intval', array_column(db()->all('SELECT id FROM {p}home_sections'), 'id'));
        if (!$ids || array_diff($ids, $valid)) {
            return $this->json(['ok' => false, 'message' => 'क्रम सही नहीं है। पेज रीफ़्रेश करें।'], 422);
        }
        db()->transaction(function ($db) use ($ids) {
            foreach ($ids as $i => $id) {
                $db->update('home_sections', ['sort_order' => $i + 1], 'id = ?', [$id]);
            }
        });
        HomepageService::clearCache();
        AuditService::log('reorder', 'homepage', null, 'होमपेज सेक्शन का क्रम बदला');
        return $this->json(['ok' => true, 'message' => 'क्रम सेव हो गया।']);
    }
}
