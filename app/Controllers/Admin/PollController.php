<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Poll;
use App\Services\AuditService;

/** पोल: सवाल, विकल्प, समय, नतीजे, CSV */
final class PollController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->str('status');
        $where = isset(Poll::STATUSES[$status]) ? 'WHERE p.status = ?' : '';
        $params = $where ? [$status] : [];
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}polls p $where", $params);
        $items = db()->all("SELECT p.*, (SELECT COUNT(*) FROM {p}poll_options o WHERE o.poll_id = p.id) opts FROM {p}polls p $where ORDER BY p.id DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        return $this->view('admin/polls/index', ['items' => new Paginator($items, $total, 30, $page), 'status' => $status]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/polls/form', ['poll' => null, 'options' => []]);
    }

    public function store(Request $request): Response
    {
        [$data, $opts] = $this->payload($request, null);
        $id = db()->transaction(function () use ($data, $opts) {
            $id = Poll::create($data + ['created_by' => auth()->id()]);
            foreach ($opts as $i => [, $label]) {
                db()->insert('poll_options', ['poll_id' => $id, 'label' => $label, 'sort_order' => $i]);
            }
            return $id;
        });
        cache()->flush('home');
        AuditService::log('create', 'polls', $id, 'पोल: ' . $data['question'], null, $data);
        return $this->toRoute('admin.polls.edit', ['id' => $id])->with('success', 'पोल बन गया। ख़बर/पेज में [poll:' . $id . '] लिखकर या होमपेज के "पोल" ब्लॉक से लगाएँ।');
    }

    public function edit(Request $request, int $id): Response
    {
        $poll = $this->find($id);
        return $this->view('admin/polls/form', ['poll' => $poll, 'options' => db()->all('SELECT * FROM {p}poll_options WHERE poll_id = ? ORDER BY sort_order, id', [$id])]);
    }

    public function update(Request $request, int $id): Response
    {
        $poll = $this->find($id);
        [$data, $opts] = $this->payload($request, $poll);
        db()->transaction(function () use ($id, $data, $opts) {
            Poll::update($id, $data);
            $keep = [];
            foreach ($opts as $i => [$oid, $label]) {
                if ($oid && db()->value('SELECT id FROM {p}poll_options WHERE id = ? AND poll_id = ?', [$oid, $id])) {
                    db()->update('poll_options', ['label' => $label, 'sort_order' => $i], 'id = ?', [$oid]);
                    $keep[] = $oid;
                } else {
                    $keep[] = db()->insert('poll_options', ['poll_id' => $id, 'label' => $label, 'sort_order' => $i]);
                }
            }
            db()->query('DELETE FROM {p}poll_options WHERE poll_id = ? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')', [$id]);
            db()->query('UPDATE {p}polls SET total_votes = (SELECT COALESCE(SUM(votes), 0) FROM {p}poll_options WHERE poll_id = ?) WHERE id = ?', [$id, $id]);
        });
        cache()->flush('home');
        AuditService::log('update', 'polls', $id, 'पोल बदला: ' . $data['question'], $poll, $data);
        return $this->toRoute('admin.polls.edit', ['id' => $id])->with('success', 'पोल सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $poll = $this->find($id);
        Poll::delete($id);
        cache()->flush('home');
        AuditService::log('delete', 'polls', $id, 'पोल हटाया: ' . $poll['question'], $poll);
        return $this->toRoute('admin.polls.index')->with('success', 'पोल हटा दिया गया।');
    }

    public function export(Request $request, int $id): Response
    {
        $poll = $this->find($id);
        $opts = db()->all('SELECT label, votes FROM {p}poll_options WHERE poll_id = ? ORDER BY sort_order, id', [$id]);
        $sum = max(1, array_sum(array_column($opts, 'votes')));
        return Response::csv('poll-' . $id . '-' . date('Y-m-d') . '.csv', ['विकल्प', 'वोट', 'प्रतिशत'], (static function () use ($opts, $sum, $poll) {
            foreach ($opts as $o) {
                yield [$o['label'], $o['votes'], round(100 * $o['votes'] / $sum, 1)];
            }
            yield ['कुल मतदाता', $poll['voters'], ''];
        })());
    }

    /** [पोल डेटा, [[option_id|0, label], …]] */
    private function payload(Request $request, ?array $poll): array
    {
        $v = $this->validate($request, [
            'question' => 'required|min:5|max:300', 'description' => 'nullable|max:500', 'status' => 'required|in:draft,active,closed',
            'show_results' => 'required|in:after_vote,always,after_close', 'start_at' => 'nullable|date', 'end_at' => 'nullable|date',
        ], ['question' => 'सवाल', 'description' => 'विवरण', 'status' => 'स्थिति', 'show_results' => 'नतीजे', 'start_at' => 'शुरू', 'end_at' => 'ख़त्म']);
        $ids = (array) $request->input('option_id', []);
        $opts = [];
        foreach ((array) $request->input('option', []) as $i => $label) {
            $label = trim(strip_tags((string) $label));
            if ($label !== '') {
                $opts[] = [(int) ($ids[$i] ?? 0), mb_substr($label, 0, 200)];
            }
        }
        $errors = [];
        if (count($opts) < 2 || count($opts) > 10) {
            $errors['option'] = '2 से 10 विकल्प लिखें।';
        } elseif (count(array_unique(array_map(static fn($o) => mb_strtolower($o[1]), $opts))) !== count($opts)) {
            $errors['option'] = 'दो विकल्प एक जैसे नहीं हो सकते।';
        }
        $start = $v['start_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['start_at'])) : null;
        $end = $v['end_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['end_at'])) : null;
        if ($start && $end && $end <= $start) {
            $errors['end_at'] = 'ख़त्म होने का समय शुरू से बाद का हो।';
        }
        if ($v['status'] === 'active' && !can('polls.publish') && ($poll['status'] ?? '') !== 'active') {
            $errors['status'] = 'पोल चालू करने की अनुमति नहीं है (ड्राफ़्ट सेव करें)।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        return [['question' => trim(strip_tags((string) $v['question'])), 'description' => $v['description'] ? strip_tags((string) $v['description']) : null, 'status' => $v['status'],
            'multiple' => $request->bool('multiple') ? 1 : 0, 'require_login' => $request->bool('require_login') ? 1 : 0, 'show_results' => $v['show_results'], 'start_at' => $start, 'end_at' => $end], $opts];
    }

    private function find(int $id): array
    {
        return Poll::find($id) ?? throw new HttpException(404);
    }
}
