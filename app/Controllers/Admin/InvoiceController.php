<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\AdInvoice;
use App\Models\AdPayment;
use App\Services\AdService;
use App\Services\AdvertiserService;
use App\Services\AuditService;

/** इनवॉइस: लाइन आइटम, GST, स्थिति, प्रिंट/PDF, भुगतान दर्ज करना */
final class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        [$w, $p, $f] = $this->filters($request);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}ad_invoices i WHERE $w", $p);
        $items = db()->all("SELECT i.*, v.company FROM {p}ad_invoices i JOIN {p}advertisers v ON v.id = i.advertiser_id WHERE $w ORDER BY i.issue_date DESC, i.id DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $p);
        $sum = db()->first("SELECT COALESCE(SUM(CASE WHEN i.status NOT IN ('draft','cancelled') THEN i.total END), 0) billed, COALESCE(SUM(i.paid), 0) paid FROM {p}ad_invoices i WHERE $w", $p);
        return $this->view('admin/invoices/index', ['items' => new Paginator($items, $total, 30, $page), 'f' => $f, 'sum' => $sum, 'advertisers' => $this->advertisers()]);
    }

    public function create(Request $request): Response
    {
        $adv = $request->int('advertiser');
        $camp = $request->int('campaign') ? db()->first('SELECT * FROM {p}ad_campaigns WHERE id = ?', [$request->int('campaign')]) : null;
        $lines = [];
        if ($camp) {
            $adv = (int) $camp['advertiser_id'];
            $spent = AdService::campaignSpend((int) $camp['id'])[(int) $camp['id']] ?? 0;
            $period = ($camp['start_date'] ? hindi_date($camp['start_date']) : '') . ($camp['end_date'] ? ' – ' . hindi_date($camp['end_date']) : '');
            $lines[] = ['description' => 'विज्ञापन कैंपेन: ' . $camp['name'] . ($period ? ' (' . $period . ')' : ''), 'qty' => 1, 'rate' => $camp['pricing'] === 'fixed' ? (float) $camp['budget'] : $spent];
        }
        return $this->view('admin/invoices/form', ['inv' => null, 'lines' => $lines ?: [['description' => '', 'qty' => 1, 'rate' => '']], 'advertiser' => $adv,
            'campaign' => $camp['id'] ?? null, 'advertisers' => $this->advertisers(), 'campaigns' => $this->campaigns()]);
    }

    public function store(Request $request): Response
    {
        [$data, $lines] = $this->payload($request);
        $id = db()->transaction(function () use ($data, $lines, $request) {
            $id = AdInvoice::create($data + ['invoice_no' => 'TMP-' . bin2hex(random_bytes(6)), 'status' => $request->str('action') === 'send' ? 'sent' : 'draft', 'created_by' => auth()->id()]);
            AdInvoice::update($id, ['invoice_no' => AdvertiserService::invoiceNo($id, $data['issue_date'])]);
            $this->saveLines($id, $lines);
            AdvertiserService::recalc($id);
            return $id;
        });
        $inv = AdInvoice::find($id);
        AuditService::log('create', 'advertisers', $id, 'इनवॉइस ' . $inv['invoice_no'] . ': ' . AdvertiserService::money($inv['total']));
        return $this->toRoute('admin.invoices.show', ['id' => $id])->with('success', 'इनवॉइस ' . $inv['invoice_no'] . ' बन गया।');
    }

    public function show(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        return $this->view('admin/invoices/show', $this->data($inv));
    }

    public function edit(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        $this->editable($inv);
        return $this->view('admin/invoices/form', ['inv' => $inv, 'lines' => db()->all('SELECT * FROM {p}ad_invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]),
            'advertiser' => (int) $inv['advertiser_id'], 'campaign' => $inv['campaign_id'], 'advertisers' => $this->advertisers(), 'campaigns' => $this->campaigns()]);
    }

    public function update(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        $this->editable($inv);
        [$data, $lines] = $this->payload($request);
        db()->transaction(function () use ($id, $data, $lines) {
            AdInvoice::update($id, $data);
            $this->saveLines($id, $lines);
            AdvertiserService::recalc($id);
        });
        $new = AdInvoice::find($id);
        AuditService::log('update', 'advertisers', $id, 'इनवॉइस बदला ' . $inv['invoice_no'] . ': ' . AdvertiserService::money($inv['total']) . ' → ' . AdvertiserService::money($new['total']));
        return $this->toRoute('admin.invoices.show', ['id' => $id])->with('success', 'इनवॉइस सेव हो गया।');
    }

    public function print(Request $request, int $id): Response
    {
        return $this->view('print/invoice', $this->data($this->find($id)));
    }

    /** भेजा गया / रद्द / ड्राफ़्ट */
    public function status(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        $to = $request->str('status');
        if (!in_array($to, ['draft', 'sent', 'cancelled'], true)) {
            throw new HttpException(422);
        }
        if ($to === 'cancelled' && (float) $inv['paid'] > 0) {
            return $this->back()->with('danger', 'भुगतान दर्ज है; रद्द करने से पहले भुगतान हटाएँ।');
        }
        if (in_array($inv['status'], ['paid', 'partial'], true) && $to === 'draft') {
            return $this->back()->with('danger', 'भुगतान वाला इनवॉइस ड्राफ़्ट नहीं हो सकता।');
        }
        AdInvoice::update($id, ['status' => $to]);
        AdvertiserService::recalc($id);
        AuditService::log('status', 'advertisers', $id, 'इनवॉइस ' . $inv['invoice_no'] . ': ' . AdInvoice::STATUSES[$inv['status']] . ' → ' . AdInvoice::STATUSES[$to]);
        return $this->back()->with('success', 'स्थिति: ' . AdInvoice::STATUSES[$to]);
    }

    public function pay(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        if (in_array($inv['status'], ['draft', 'cancelled'], true)) {
            return $this->back()->with('danger', 'ड्राफ़्ट/रद्द इनवॉइस पर भुगतान दर्ज नहीं होता; पहले “भेजा गया” करें।');
        }
        $v = $this->validate($request, [
            'amount' => 'required|numeric|min:0.01', 'paid_on' => 'required|date', 'method' => 'required|in:' . implode(',', array_keys(AdPayment::METHODS)),
            'reference' => 'nullable|max:120', 'notes' => 'nullable|max:300',
        ], ['amount' => 'राशि', 'paid_on' => 'तारीख़', 'method' => 'तरीका', 'reference' => 'रेफ़रेंस', 'notes' => 'नोट']);
        $due = round((float) $inv['total'] - (float) $inv['paid'], 2);
        $amount = round((float) $v['amount'], 2);
        if ($amount > $due) {
            throw new ValidationException(['amount' => 'राशि बकाया (' . AdvertiserService::money($due) . ') से ज़्यादा नहीं हो सकती।'], $request->post());
        }
        $on = date('Y-m-d', strtotime((string) $v['paid_on']));
        if ($on > date('Y-m-d')) {
            throw new ValidationException(['paid_on' => 'आगे की तारीख़ का भुगतान दर्ज नहीं होता।'], $request->post());
        }
        AdPayment::create(['invoice_id' => $id, 'advertiser_id' => $inv['advertiser_id'], 'amount' => $amount, 'paid_on' => $on, 'method' => $v['method'],
            'reference' => $v['reference'] ? strip_tags((string) $v['reference']) : null, 'notes' => $v['notes'] ? strip_tags((string) $v['notes']) : null, 'created_by' => auth()->id(), 'created_at' => now()]);
        AdvertiserService::recalc($id);
        AuditService::log('payment', 'advertisers', $id, 'भुगतान ' . AdvertiserService::money($amount) . ' (' . $inv['invoice_no'] . ')');
        return $this->toRoute('admin.invoices.show', ['id' => $id])->with('success', AdvertiserService::money($amount) . ' का भुगतान दर्ज हुआ।');
    }

    public function unpay(Request $request, int $id, int $pid): Response
    {
        $inv = $this->find($id);
        $p = db()->first('SELECT * FROM {p}ad_payments WHERE id = ? AND invoice_id = ?', [$pid, $id]) ?? throw new HttpException(404);
        AdPayment::delete($pid);
        AdvertiserService::recalc($id);
        AuditService::log('payment_delete', 'advertisers', $id, 'भुगतान हटाया ' . AdvertiserService::money($p['amount']) . ' (' . $inv['invoice_no'] . ')', $p);
        return $this->back()->with('success', 'भुगतान हटा दिया गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $inv = $this->find($id);
        if (!in_array($inv['status'], ['draft', 'cancelled'], true) || (float) $inv['paid'] > 0) {
            return $this->back()->with('danger', 'सिर्फ़ ड्राफ़्ट या रद्द (बिना भुगतान) इनवॉइस हटाया जा सकता है।');
        }
        AdInvoice::delete($id);
        AuditService::log('delete', 'advertisers', $id, 'इनवॉइस हटाया ' . $inv['invoice_no'], $inv);
        return $this->toRoute('admin.invoices.index')->with('success', 'इनवॉइस हटा दिया गया।');
    }

    public function export(Request $request): Response
    {
        [$w, $p] = $this->filters($request);
        $rows = db()->all("SELECT i.*, v.company, v.gstin FROM {p}ad_invoices i JOIN {p}advertisers v ON v.id = i.advertiser_id WHERE $w ORDER BY i.issue_date", $p);
        AuditService::log('export', 'advertisers', null, 'इनवॉइस CSV (' . count($rows) . ')');
        return Response::csv('invoices-' . date('Y-m-d') . '.csv', ['इनवॉइस', 'तारीख़', 'देय तारीख़', 'विज्ञापनदाता', 'GSTIN', 'राशि', 'GST %', 'GST', 'कुल', 'भुगतान', 'बकाया', 'स्थिति'],
            (static function () use ($rows) {
                foreach ($rows as $r) {
                    yield [$r['invoice_no'], $r['issue_date'], $r['due_date'], $r['company'], $r['gstin'], $r['subtotal'], $r['tax_rate'], $r['tax'], $r['total'], $r['paid'],
                        round($r['total'] - $r['paid'], 2), AdInvoice::STATUSES[$r['status']]];
                }
            })());
    }

    // ---------- मदद ----------

    private function payload(Request $request): array
    {
        $v = $this->validate($request, [
            'advertiser_id' => 'required|integer|exists:advertisers,id', 'campaign_id' => 'nullable|integer|exists:ad_campaigns,id', 'issue_date' => 'required|date',
            'due_date' => 'nullable|date', 'tax_rate' => 'required|numeric|min:0|max:50', 'notes' => 'nullable|max:2000',
        ], ['advertiser_id' => 'विज्ञापनदाता', 'campaign_id' => 'कैंपेन', 'issue_date' => 'तारीख़', 'due_date' => 'देय तारीख़', 'tax_rate' => 'GST %', 'notes' => 'नोट']);
        if ($v['campaign_id'] && (int) db()->value('SELECT advertiser_id FROM {p}ad_campaigns WHERE id = ?', [$v['campaign_id']]) !== (int) $v['advertiser_id']) {
            throw new ValidationException(['campaign_id' => 'कैंपेन इसी विज्ञापनदाता का चुनें।'], $request->post());
        }
        $lines = [];
        foreach ((array) ($request->post()['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = trim(strip_tags((string) ($row['description'] ?? '')));
            $qty = (float) ($row['qty'] ?? 0);
            $rate = (float) ($row['rate'] ?? 0);
            if ($desc === '' && !$rate) {
                continue; // ख़ाली पंक्ति
            }
            if ($desc === '' || $qty <= 0 || $rate < 0 || $qty > 1000000 || $rate > 1000000000) {
                throw new ValidationException(['items' => 'हर पंक्ति में विवरण, मात्रा (0 से ज़्यादा) और दर सही भरें।'], $request->post());
            }
            $lines[] = ['description' => mb_substr($desc, 0, 300), 'qty' => round($qty, 2), 'rate' => round($rate, 2), 'amount' => round($qty * $rate, 2)];
        }
        if (!$lines) {
            throw new ValidationException(['items' => 'कम से कम एक पंक्ति भरें।'], $request->post());
        }
        $issue = date('Y-m-d', strtotime((string) $v['issue_date']));
        $due = $v['due_date'] ? date('Y-m-d', strtotime((string) $v['due_date'])) : null;
        if ($due && $due < $issue) {
            throw new ValidationException(['due_date' => 'देय तारीख़ इनवॉइस की तारीख़ के बाद की हो।'], $request->post());
        }
        return [['advertiser_id' => (int) $v['advertiser_id'], 'campaign_id' => $v['campaign_id'] ? (int) $v['campaign_id'] : null, 'issue_date' => $issue, 'due_date' => $due,
            'tax_rate' => round((float) $v['tax_rate'], 2), 'notes' => $v['notes'] ? strip_tags((string) $v['notes']) : null], $lines];
    }

    private function saveLines(int $id, array $lines): void
    {
        db()->query('DELETE FROM {p}ad_invoice_items WHERE invoice_id = ?', [$id]);
        foreach ($lines as $i => $l) {
            db()->insert('ad_invoice_items', $l + ['invoice_id' => $id, 'sort_order' => $i]);
        }
    }

    /** भुगतान होने के बाद पंक्तियाँ नहीं बदलतीं (हिसाब बदल जाएगा) */
    private function editable(array $inv): void
    {
        if (!in_array($inv['status'], ['draft', 'sent'], true) || (float) $inv['paid'] > 0) {
            throw new HttpException(403, 'भुगतान वाला/रद्द इनवॉइस बदला नहीं जा सकता।');
        }
    }

    private function data(array $inv): array
    {
        return ['inv' => $inv, 'adv' => db()->first('SELECT * FROM {p}advertisers WHERE id = ?', [$inv['advertiser_id']]),
            'campaign' => $inv['campaign_id'] ? db()->first('SELECT * FROM {p}ad_campaigns WHERE id = ?', [$inv['campaign_id']]) : null,
            'lines' => db()->all('SELECT * FROM {p}ad_invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$inv['id']]),
            'payments' => db()->all('SELECT p.*, u.name AS by_name FROM {p}ad_payments p LEFT JOIN {p}users u ON u.id = p.created_by WHERE p.invoice_id = ? ORDER BY p.paid_on, p.id', [$inv['id']])];
    }

    private function filters(Request $request): array
    {
        $w = ['1=1'];
        $p = [];
        $f = ['status' => $request->str('status'), 'advertiser' => $request->int('advertiser'), 'month' => preg_match('/^\d{4}-\d{2}$/', $request->str('month')) ? $request->str('month') : ''];
        if (isset(AdInvoice::STATUSES[$f['status']])) {
            $w[] = 'i.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'overdue') {
            $w[] = "i.status IN ('sent','partial') AND i.due_date < CURDATE()";
        }
        if ($f['advertiser']) {
            $w[] = 'i.advertiser_id = ?';
            $p[] = $f['advertiser'];
        }
        if ($f['month']) {
            $w[] = "DATE_FORMAT(i.issue_date, '%Y-%m') = ?";
            $p[] = $f['month'];
        }
        return [implode(' AND ', $w), $p, $f];
    }

    private function advertisers(): array
    {
        return array_column(db()->all('SELECT id, company FROM {p}advertisers ORDER BY company'), 'company', 'id');
    }

    private function campaigns(): array
    {
        return db()->all('SELECT id, advertiser_id, name FROM {p}ad_campaigns ORDER BY name');
    }

    private function find(int $id): array
    {
        return AdInvoice::find($id) ?? throw new HttpException(404);
    }
}
