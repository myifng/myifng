<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Advertiser;
use App\Services\AdvertiserService;
use App\Services\AuditService;

/** विज्ञापनदाता CRM: सूची, प्रोफ़ाइल (कैंपेन, विज्ञापन, इनवॉइस, भुगतान, प्रदर्शन), राजस्व */
final class AdvertiserController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $status = $request->str('status');
        if (isset(Advertiser::STATUSES[$status])) {
            $where[] = 'v.status = ?';
            $params[] = $status;
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(v.company LIKE ? OR v.contact_name LIKE ? OR v.phone LIKE ? OR v.email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}advertisers v WHERE $w", $params);
        $items = db()->all("SELECT v.*,
                (SELECT COUNT(*) FROM {p}ad_campaigns c WHERE c.advertiser_id = v.id AND c.status = 'active') AS live_campaigns,
                (SELECT COALESCE(SUM(i.total - i.paid), 0) FROM {p}ad_invoices i WHERE i.advertiser_id = v.id AND i.status IN ('sent','partial')) AS due,
                (SELECT COALESCE(SUM(p.amount), 0) FROM {p}ad_payments p WHERE p.advertiser_id = v.id) AS paid
                FROM {p}advertisers v WHERE $w ORDER BY v.status = 'active' DESC, v.company LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $kpi = can('advertisers.manage') ? $this->kpi() : null;
        return $this->view('admin/advertisers/index', ['items' => new Paginator($items, $total, 30, $page), 'status' => $status, 'q' => $q, 'kpi' => $kpi]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/advertisers/form', ['adv' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = Advertiser::create($data + ['created_by' => auth()->id()]);
        AuditService::log('create', 'advertisers', $id, 'विज्ञापनदाता: ' . $data['company'], null, $data);
        return $this->toRoute('admin.advertisers.show', ['id' => $id])->with('success', '“' . $data['company'] . '” जुड़ गया। अब कैंपेन बनाएँ।');
    }

    public function show(Request $request, int $id): Response
    {
        $adv = $this->find($id);
        $campaigns = db()->all('SELECT c.*, (SELECT COUNT(*) FROM {p}ads a WHERE a.campaign_id = c.id) AS ads, (SELECT COALESCE(SUM(a.impressions), 0) FROM {p}ads a WHERE a.campaign_id = c.id) AS imp,
                (SELECT COALESCE(SUM(a.clicks), 0) FROM {p}ads a WHERE a.campaign_id = c.id) AS clk FROM {p}ad_campaigns c WHERE c.advertiser_id = ? ORDER BY c.start_date DESC, c.id DESC', [$id]);
        $invoices = can('advertisers.manage') ? db()->all('SELECT * FROM {p}ad_invoices WHERE advertiser_id = ? ORDER BY issue_date DESC, id DESC LIMIT 50', [$id]) : [];
        $payments = can('advertisers.manage') ? db()->all('SELECT p.*, i.invoice_no FROM {p}ad_payments p JOIN {p}ad_invoices i ON i.id = p.invoice_id WHERE p.advertiser_id = ? ORDER BY p.paid_on DESC, p.id DESC LIMIT 20', [$id]) : [];
        return $this->view('admin/advertisers/show', ['adv' => $adv, 'campaigns' => $campaigns, 'invoices' => $invoices, 'payments' => $payments,
            'sum' => AdvertiserService::summary($id), 'spend' => \App\Services\AdService::campaignSpend()]);
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->view('admin/advertisers/form', ['adv' => $this->find($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        $adv = $this->find($id);
        $data = $this->payload($request, $adv);
        Advertiser::update($id, $data);
        AuditService::log('update', 'advertisers', $id, 'विज्ञापनदाता बदला: ' . $data['company'], $adv, $data);
        return $this->toRoute('admin.advertisers.show', ['id' => $id])->with('success', 'बदलाव सेव हो गए।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $adv = $this->find($id);
        if ((int) db()->value('SELECT COUNT(*) FROM {p}ad_invoices WHERE advertiser_id = ?', [$id])) {
            return $this->back()->with('danger', 'इस विज्ञापनदाता के इनवॉइस हैं (हिसाब के लिए ज़रूरी)। हटाने की जगह “बंद” करें।');
        }
        Advertiser::delete($id); // कैंपेन हटेंगे; उनके विज्ञापन हाउस विज्ञापन बन जाएँगे (campaign_id NULL)
        \App\Services\AdService::changed();
        AuditService::log('delete', 'advertisers', $id, 'विज्ञापनदाता हटाया: ' . $adv['company'], $adv);
        return $this->toRoute('admin.advertisers.index')->with('success', '“' . $adv['company'] . '” हटा दिया गया।');
    }

    public function export(Request $request): Response
    {
        $rows = db()->all("SELECT v.*, (SELECT COALESCE(SUM(p.amount), 0) FROM {p}ad_payments p WHERE p.advertiser_id = v.id) paid,
            (SELECT COALESCE(SUM(i.total - i.paid), 0) FROM {p}ad_invoices i WHERE i.advertiser_id = v.id AND i.status IN ('sent','partial')) due FROM {p}advertisers v ORDER BY v.company");
        AuditService::log('export', 'advertisers', null, 'विज्ञापनदाता CSV (' . count($rows) . ')');
        $money = can('advertisers.manage');
        return Response::csv('advertisers-' . date('Y-m-d') . '.csv', array_merge(['ID', 'कंपनी', 'संपर्क', 'फ़ोन', 'ईमेल', 'GSTIN', 'शहर', 'स्थिति'], $money ? ['कुल भुगतान', 'बकाया'] : []),
            (static function () use ($rows, $money) {
                foreach ($rows as $r) {
                    yield array_merge([$r['id'], $r['company'], $r['contact_name'], $r['phone'], $r['email'], $r['gstin'], $r['city'], Advertiser::STATUSES[$r['status']]], $money ? [$r['paid'], $r['due']] : []);
                }
            })());
    }

    /** राजस्व: इस महीने, बकाया, 12 महीने का ग्राफ़, सबसे बड़े विज्ञापनदाता */
    public function revenue(Request $request): Response
    {
        $top = db()->all('SELECT v.id, v.company, SUM(p.amount) s FROM {p}ad_payments p JOIN {p}advertisers v ON v.id = p.advertiser_id WHERE p.paid_on >= CURDATE() - INTERVAL 12 MONTH GROUP BY v.id ORDER BY s DESC LIMIT 10');
        $overdue = db()->all("SELECT i.*, v.company FROM {p}ad_invoices i JOIN {p}advertisers v ON v.id = i.advertiser_id WHERE i.status IN ('sent','partial') AND i.due_date < CURDATE() ORDER BY i.due_date LIMIT 20");
        return $this->view('admin/advertisers/revenue', ['kpi' => $this->kpi(), 'chart' => AdvertiserService::monthly(12), 'top' => $top, 'overdue' => $overdue]);
    }

    private function kpi(): array
    {
        $r = db()->first("SELECT
            (SELECT COALESCE(SUM(amount), 0) FROM {p}ad_payments WHERE DATE_FORMAT(paid_on, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) AS month_paid,
            (SELECT COALESCE(SUM(amount), 0) FROM {p}ad_payments WHERE YEAR(paid_on) = YEAR(CURDATE())) AS year_paid,
            (SELECT COALESCE(SUM(total - paid), 0) FROM {p}ad_invoices WHERE status IN ('sent','partial')) AS due,
            (SELECT COALESCE(SUM(total - paid), 0) FROM {p}ad_invoices WHERE status IN ('sent','partial') AND due_date < CURDATE()) AS overdue,
            (SELECT COUNT(*) FROM {p}ad_campaigns WHERE status = 'active') AS live_campaigns");
        return array_map('floatval', $r);
    }

    private function payload(Request $request, ?array $adv): array
    {
        $v = $this->validate($request, [
            'company' => 'required|max:190', 'contact_name' => 'nullable|max:150', 'email' => 'nullable|email|max:190', 'phone' => 'nullable|mobile',
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/i'], 'address' => 'nullable|max:400', 'city' => 'nullable|max:120',
            'status' => 'required|in:lead,active,inactive', 'notes' => 'nullable|max:5000',
        ], ['company' => 'कंपनी', 'contact_name' => 'संपर्क व्यक्ति', 'email' => 'ईमेल', 'phone' => 'फ़ोन', 'gstin' => 'GSTIN', 'address' => 'पता', 'city' => 'शहर', 'status' => 'स्थिति', 'notes' => 'नोट्स']);
        $company = trim(strip_tags((string) $v['company']));
        if ($company === '') {
            throw new ValidationException(['company' => 'कंपनी का नाम लिखें।'], $request->post());
        }
        $t = static fn($x) => $x !== null && trim(strip_tags((string) $x)) !== '' ? trim(strip_tags((string) $x)) : null;
        return ['company' => $company, 'contact_name' => $t($v['contact_name']), 'email' => $v['email'] ? mb_strtolower((string) $v['email']) : null,
            'phone' => $v['phone'] ?: null, 'gstin' => $v['gstin'] ? strtoupper((string) $v['gstin']) : null, 'address' => $t($v['address']), 'city' => $t($v['city']),
            'status' => $v['status'], 'notes' => $t($v['notes'])];
    }

    private function find(int $id): array
    {
        return Advertiser::find($id) ?? throw new HttpException(404);
    }
}
