<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\FormService;
use App\Services\HtmlSanitizer;

/** करियर: वैकेंसी (आवेदन इनबॉक्स में, प्रकार "career") */
final class JobController extends Controller
{
    public const TYPES = ['full_time' => 'फ़ुल-टाइम', 'part_time' => 'पार्ट-टाइम', 'internship' => 'इंटर्नशिप', 'freelance' => 'फ़्रीलांस', 'contract' => 'कॉन्ट्रैक्ट'];
    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'open' => 'खुली', 'closed' => 'बंद'];

    public function index(Request $request): Response
    {
        $items = db()->all("SELECT j.*, (SELECT COUNT(*) FROM {p}form_submissions s WHERE s.job_id = j.id) apps,
            (SELECT COUNT(*) FROM {p}form_submissions s WHERE s.job_id = j.id AND s.status = 'new') fresh FROM {p}jobs j ORDER BY j.status = 'open' DESC, j.id DESC");
        return $this->view('admin/careers/index', ['items' => $items]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/careers/form', ['job' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = db()->insert('jobs', $data + ['created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        AuditService::log('create', 'careers', $id, 'वैकेंसी: ' . $data['title']);
        return $this->toRoute('admin.careers.edit', ['id' => $id])->with('success', 'वैकेंसी सेव हो गई।' . ($data['status'] === 'open' ? ' वेबसाइट पर /careers में दिख रही है।' : ''));
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->view('admin/careers/form', ['job' => $this->find($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        $job = $this->find($id);
        $data = $this->payload($request, $job);
        db()->update('jobs', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        AuditService::log('update', 'careers', $id, 'वैकेंसी बदली: ' . $data['title']);
        return $this->toRoute('admin.careers.edit', ['id' => $id])->with('success', 'वैकेंसी सेव हो गई।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $job = $this->find($id);
        db()->query('DELETE FROM {p}jobs WHERE id = ?', [$id]);
        AuditService::log('delete', 'careers', $id, 'वैकेंसी हटाई: ' . $job['title']);
        return $this->toRoute('admin.careers.index')->with('success', 'वैकेंसी हटाई; उसके आवेदन इनबॉक्स में बने रहेंगे।');
    }

    private function payload(Request $request, ?array $job): array
    {
        $v = $this->validate($request, ['title' => 'required|min:3|max:190', 'slug' => 'nullable|slug|max:150', 'department' => 'nullable|max:120', 'location' => 'nullable|max:120',
            'job_type' => 'required|in:' . implode(',', array_keys(self::TYPES)), 'requirements' => 'nullable|max:5000', 'salary' => 'nullable|max:120',
            'vacancies' => 'required|integer|min:1|max:999', 'deadline' => 'nullable|date', 'status' => 'required|in:draft,open,closed'],
            ['title' => 'पद', 'slug' => 'पता', 'department' => 'विभाग', 'location' => 'जगह', 'job_type' => 'प्रकार', 'requirements' => 'योग्यता', 'salary' => 'वेतन', 'vacancies' => 'पद संख्या', 'deadline' => 'अंतिम तारीख़', 'status' => 'स्थिति']);
        $t = static fn($x) => $x !== null && trim(strip_tags((string) $x)) !== '' ? trim(strip_tags((string) $x)) : null;
        return ['title' => (string) $t($v['title']), 'slug' => FormService::slug((string) ($v['slug'] ?? ''), (string) $v['title'], (int) ($job['id'] ?? 0), 'jobs'),
            'department' => $t($v['department']), 'location' => $t($v['location']), 'job_type' => $v['job_type'],
            'description' => ($d = HtmlSanitizer::clean((string) $request->input('description', ''), (string) config('app.url'))) !== '' ? $d : null,
            'requirements' => $t($v['requirements']), 'salary' => $t($v['salary']), 'vacancies' => (int) $v['vacancies'],
            'deadline' => $v['deadline'] ? date('Y-m-d', strtotime((string) $v['deadline'])) : null, 'status' => $v['status']];
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM {p}jobs WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }
}
