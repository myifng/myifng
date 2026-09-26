<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Location;
use App\Repositories\AssignmentRepository;
use App\Services\AuditService;
use App\Services\NewsService;

/** असाइनमेंट डेस्क: संपादक ख़बरें सौंपते हैं; रिपोर्टर अपने असाइनमेंट देखते, स्वीकारते और लिखना शुरू करते हैं */
final class AssignmentController extends Controller
{
    private const LABELS = ['title' => 'शीर्षक', 'description' => 'विवरण', 'reporter_id' => 'रिपोर्टर', 'category_id' => 'बीट (श्रेणी)', 'location_id' => 'लोकेशन',
        'priority' => 'प्राथमिकता', 'deadline' => 'डेडलाइन', 'instructions' => 'निर्देश', 'status' => 'स्थिति'];

    /** सभी असाइनमेंट कौन देखे: देने वाले (create) या manage */
    private static function desk(): bool
    {
        return can('assignments.create') || can('assignments.manage');
    }

    private function find(int $id): array
    {
        $a = Assignment::find($id);
        if (!$a || (!self::desk() && (int) $a['reporter_id'] !== (int) auth()->id())) {
            throw new HttpException(404);
        }
        return $a;
    }

    public function index(Request $request): Response
    {
        $repo = new AssignmentRepository();
        $tab = isset(AssignmentRepository::TABS[$request->str('tab')]) ? $request->str('tab') : 'today';
        $f = ['reporter' => $request->int('reporter'), 'q' => mb_substr($request->str('q'), 0, 100)];
        $all = self::desk() && !$request->bool('mine');
        return $this->view('admin/assignments/index', [
            'items' => $repo->paginate($tab, $all, $f, max(1, $request->int('page', 1))), 'counts' => $repo->counts($all, $f),
            'tab' => $tab, 'filters' => $f, 'all' => $all, 'reporters' => self::desk() ? NewsService::reporters() : [],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    private function form(?array $a): Response
    {
        $loc = !empty($a['location_id']) ? Location::find((int) $a['location_id']) : null;
        $att = [];
        $ids = array_map('intval', (array) json_decode((string) ($a['attachments'] ?? ''), true));
        if ($ids) {
            $att = db()->all('SELECT * FROM {p}media WHERE id IN (' . Database::in($ids) . ')', $ids);
        }
        return $this->view('admin/assignments/form', ['a' => $a, 'location' => $loc, 'attachments' => $att, 'categories' => Category::options(), 'reporters' => NewsService::reporters()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request);
        $data['created_by'] = auth()->id();
        $data['status'] = 'open';
        $id = Assignment::create($data);
        AuditService::log('create', 'assignments', $id, 'असाइनमेंट दिया: ' . $data['title'], null, $data);
        return $this->toRoute('admin.assignments.index')->with('success', 'असाइनमेंट “' . $data['title'] . '” सौंप दिया गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->find($id));
    }

    public function update(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $data = $this->payload($request);
        if (isset(Assignment::STATUSES[$request->str('status')])) {
            $data['status'] = $request->str('status');
        }
        Assignment::update($id, $data);
        AuditService::log('update', 'assignments', $id, 'असाइनमेंट बदला: ' . $data['title'], $a, $data);
        return $this->toRoute('admin.assignments.edit', ['id' => $id])->with('success', 'असाइनमेंट सेव हो गया।');
    }

    private function payload(Request $request): array
    {
        $v = $this->validate($request, [
            'title' => 'required|max:190', 'description' => 'nullable|max:5000', 'reporter_id' => 'required|integer',
            'category_id' => 'nullable|integer|exists:categories,id', 'location_id' => 'nullable|integer|exists:locations,id',
            'priority' => 'required|in:' . implode(',', array_keys(Assignment::PRIORITIES)), 'deadline' => 'nullable|date', 'instructions' => 'nullable|max:5000',
        ], self::LABELS);
        if (!in_array((int) $v['reporter_id'], array_map('intval', array_column(NewsService::reporters(), 'id')), true)) {
            throw new ValidationException(['reporter_id' => 'यह यूज़र रिपोर्टर नहीं चुना जा सकता।'], $request->post());
        }
        $att = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $request->input('attachments', []))))), 0, 20);
        if ($att) {
            $att = array_map('intval', array_column(db()->all('SELECT id FROM {p}media WHERE deleted_at IS NULL AND id IN (' . Database::in($att) . ')', $att), 'id'));
        }
        return [
            'title' => strip_tags($v['title']), 'description' => $v['description'], 'reporter_id' => (int) $v['reporter_id'],
            'category_id' => $v['category_id'] ? (int) $v['category_id'] : null, 'location_id' => $v['location_id'] ? (int) $v['location_id'] : null,
            'priority' => $v['priority'], 'deadline' => $v['deadline'] ? date('Y-m-d H:i:s', strtotime($v['deadline'])) : null,
            'instructions' => $v['instructions'], 'attachments' => $att ? json_encode($att) : null,
        ];
    }

    /** स्थिति: रिपोर्टर सिर्फ़ स्वीकार/काम जारी; डेस्क कोई भी */
    public function status(Request $request, int $id): Response
    {
        $a = $this->find($id);
        $to = $request->str('status');
        $allowed = can('assignments.edit') || can('assignments.manage') ? array_keys(Assignment::STATUSES) : Assignment::REPORTER_STATUSES;
        if (!in_array($to, $allowed, true) || ($a['status'] === 'completed' && !can('assignments.manage'))) {
            return $this->back()->with('danger', 'यह स्थिति आप नहीं चुन सकते।');
        }
        Assignment::update($id, ['status' => $to]);
        AuditService::log('status', 'assignments', $id, 'असाइनमेंट की स्थिति: ' . Assignment::STATUSES[$to][0] . ' · ' . $a['title']);
        return $this->back()->with('success', '“' . $a['title'] . '”: ' . Assignment::STATUSES[$to][0]);
    }

    /** "लिखना शुरू करें": जुड़ी ख़बर हो तो वही, वरना नई ड्राफ़्ट का फ़ॉर्म */
    public function start(Request $request, int $id): Response
    {
        $a = $this->find($id);
        if ((int) $a['reporter_id'] !== (int) auth()->id() && !can('assignments.manage')) {
            throw new HttpException(403);
        }
        if ($a['news_id'] && db()->value('SELECT id FROM {p}news WHERE id = ? AND deleted_at IS NULL', [$a['news_id']])) {
            return $this->toRoute('admin.news.edit', ['id' => $a['news_id']]);
        }
        return $this->redirect(route('admin.news.create') . '?assignment=' . $id);
    }

    public function destroy(Request $request, int $id): Response
    {
        $a = $this->find($id);
        Assignment::delete($id);
        AuditService::log('delete', 'assignments', $id, 'असाइनमेंट हटाया: ' . $a['title'], ['title' => $a['title']]);
        return $this->toRoute('admin.assignments.index')->with('success', 'असाइनमेंट हटा दिया गया। जुड़ी ख़बर (अगर हो) बनी रहेगी।');
    }
}
