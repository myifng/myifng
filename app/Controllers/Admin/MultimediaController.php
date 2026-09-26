<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\AuditService;
use App\Services\MultimediaService;

/**
 * वीडियो / फ़ोटो गैलरी / वेब स्टोरी / ऑडियो: साझा सूची, बनाना, बदलना, हटाना।
 * हर बच्चा कंट्रोलर बस अपने खाने (payload) और अपनी फ़ॉर्म वाली चीज़ें (formData) देता है।
 */
abstract class MultimediaController extends Controller
{
    /** 'video' | 'gallery' | 'story' | 'audio' */
    protected const KIND = '';
    /** फ़ॉर्म/सूची के टेक्स्ट */
    protected const LABELS = ['one' => '', 'many' => '', 'new' => '', 'desc' => '', 'icon' => ''];
    /** प्रकार के टैब (वीडियो, ऑडियो) */
    protected const TYPES = [];

    abstract protected function model(): string;

    /** प्रकार-विशेष खाने (साझा खानों के ऊपर) */
    abstract protected function payload(Request $request, ?array $row): array;

    /** फ़ॉर्म के लिए अतिरिक्त डेटा */
    protected function formData(?array $row): array
    {
        return [];
    }

    /** सेव के बाद (फ़ोटो, स्लाइड) */
    protected function afterSave(int $id, Request $request, ?array $row): void
    {
    }

    protected function cfg(): array
    {
        return MultimediaService::KINDS[static::KIND];
    }

    public function index(Request $request): Response
    {
        $cfg = $this->cfg();
        return $this->view('admin/multimedia/index', MultimediaService::adminList($cfg['table'], $request, static::TYPES) + [
            'kind' => static::KIND, 'module' => $cfg['module'], 'labels' => static::LABELS, 'types' => static::TYPES,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id));
    }

    protected function form(?array $row): Response
    {
        $cfg = $this->cfg();
        return $this->view('admin/' . str_replace('_', '-', $cfg['module']) . '/form', [
            'row' => $row, 'kind' => static::KIND, 'module' => $cfg['module'], 'labels' => static::LABELS, 'categories' => Category::options(),
        ] + $this->formData($row));
    }

    public function store(Request $request): Response
    {
        $cfg = $this->cfg();
        $data = $this->payload($request, null);
        $model = $this->model();
        $id = db()->transaction(function () use ($model, $data, $request) {
            $id = $model::create($data + ['created_by' => auth()->id()]);
            $this->afterSave($id, $request, null);
            return $id;
        });
        MultimediaService::changed();
        AuditService::log('create', $cfg['module'], $id, static::LABELS['one'] . ': ' . $data['title'], null, $this->auditData($data));
        return $this->toRoute('admin.' . $cfg['module'] . '.edit', ['id' => $id])->with('success', $this->savedMessage($data, true));
    }

    public function update(Request $request, int $id): Response
    {
        $cfg = $this->cfg();
        $row = $this->find($id);
        $data = $this->payload($request, $row);
        $model = $this->model();
        db()->transaction(function () use ($model, $id, $data, $request, $row) {
            $model::update($id, $data);
            $this->afterSave($id, $request, $row);
        });
        MultimediaService::changed();
        AuditService::log('update', $cfg['module'], $id, static::LABELS['one'] . ' बदला: ' . $data['title'], $this->auditData($row), $this->auditData($data));
        return $this->toRoute('admin.' . $cfg['module'] . '.edit', ['id' => $id])->with('success', $this->savedMessage($data, false));
    }

    public function destroy(Request $request, int $id): Response
    {
        $cfg = $this->cfg();
        $row = $this->find($id);
        $model = $this->model();
        $model::delete($id);
        MultimediaService::changed();
        AuditService::log('delete', $cfg['module'], $id, static::LABELS['one'] . ' हटाया: ' . $row['title'], $this->auditData($row));
        return $this->toRoute('admin.' . $cfg['module'] . '.index')->with('success', '“' . $row['title'] . '” हटा दिया गया।');
    }

    protected function find(int $id): array
    {
        $model = $this->model();
        return $model::find($id) ?? throw new HttpException(404);
    }

    private function savedMessage(array $data, bool $new): string
    {
        $state = MultimediaService::state($data + ['status' => 'draft', 'published_at' => null]);
        return ($new ? 'बन गया' : 'सेव हो गया') . ' · ' . ['draft' => 'ड्राफ़्ट (वेबसाइट पर नहीं)', 'scheduled' => 'शेड्यूल: ' . hindi_date($data['published_at'], true), 'published' => 'वेबसाइट पर प्रकाशित'][$state];
    }

    /** ऑडिट में लंबा टेक्स्ट नहीं */
    private function auditData(array $d): array
    {
        return array_map(static fn($v) => is_string($v) && mb_strlen($v) > 300 ? mb_substr($v, 0, 300) . '…' : $v, array_diff_key($d, ['transcript' => 1]));
    }
}
