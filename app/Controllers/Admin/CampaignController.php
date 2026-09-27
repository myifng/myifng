<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\AdCampaign;
use App\Services\AdService;
use App\Services\AdvertiserService;
use App\Services\AuditService;

/** कैंपेन: बजट, भाव (तय/CPM/CPC), तारीख़ें, विज्ञापन, ख़र्च बनाम बजट, प्रदर्शन */
final class CampaignController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->view('admin/advertisers/campaign-form', ['c' => null, 'advertiser' => $request->int('advertiser'), 'advertisers' => $this->advertisers()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request);
        $id = AdCampaign::create($data + ['created_by' => auth()->id()]);
        AdService::changed();
        AuditService::log('create', 'advertisers', $id, 'कैंपेन: ' . $data['name'], null, $data);
        return $this->toRoute('admin.campaigns.show', ['id' => $id])->with('success', 'कैंपेन बन गया। अब इसमें विज्ञापन जोड़ें।');
    }

    public function show(Request $request, int $id): Response
    {
        $c = $this->find($id);
        $adv = db()->first('SELECT * FROM {p}advertisers WHERE id = ?', [$c['advertiser_id']]);
        $ads = db()->all('SELECT a.*, (SELECT GROUP_CONCAT(s.name SEPARATOR ", ") FROM {p}ad_placements p JOIN {p}ad_slots s ON s.id = p.slot_id WHERE p.ad_id = a.id) AS slots
                          FROM {p}ads a WHERE a.campaign_id = ? ORDER BY a.status = "active" DESC, a.id DESC', [$id]);
        $rows = array_column(db()->all('SELECT d.day, SUM(d.impressions) i, SUM(d.clicks) c FROM {p}ad_stats_daily d JOIN {p}ads a ON a.id = d.ad_id WHERE a.campaign_id = ? AND d.day >= CURDATE() - INTERVAL 29 DAY GROUP BY d.day', [$id]), null, 'day');
        $chart = ['labels' => [], 'imp' => [], 'clk' => []];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $chart['labels'][] = date('j/n', strtotime($d));
            $chart['imp'][] = (int) ($rows[$d]['i'] ?? 0);
            $chart['clk'][] = (int) ($rows[$d]['c'] ?? 0);
        }
        return $this->view('admin/advertisers/campaign', ['c' => $c, 'adv' => $adv, 'ads' => $ads, 'chart' => $chart,
            'sum' => AdvertiserService::summary(null, $id), 'spent' => AdService::campaignSpend($id)[$id] ?? 0.0]);
    }

    public function edit(Request $request, int $id): Response
    {
        $c = $this->find($id);
        return $this->view('admin/advertisers/campaign-form', ['c' => $c, 'advertiser' => (int) $c['advertiser_id'], 'advertisers' => $this->advertisers()]);
    }

    public function update(Request $request, int $id): Response
    {
        $c = $this->find($id);
        $data = $this->payload($request);
        AdCampaign::update($id, $data);
        AdService::changed();
        AuditService::log('update', 'advertisers', $id, 'कैंपेन बदला: ' . $data['name'], $c, $data);
        return $this->toRoute('admin.campaigns.show', ['id' => $id])->with('success', 'बदलाव सेव हो गए।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $c = $this->find($id);
        AdCampaign::delete($id); // इसके विज्ञापन हाउस विज्ञापन बन जाते हैं
        AdService::changed();
        AuditService::log('delete', 'advertisers', $id, 'कैंपेन हटाया: ' . $c['name'], $c);
        return $this->toRoute('admin.advertisers.show', ['id' => $c['advertiser_id']])->with('success', 'कैंपेन हटा दिया गया। उसके विज्ञापन अब हाउस विज्ञापन हैं; ज़रूरत न हो तो उन्हें रोकें।');
    }

    private function payload(Request $request): array
    {
        $v = $this->validate($request, [
            'advertiser_id' => 'required|integer|exists:advertisers,id', 'name' => 'required|max:190', 'pricing' => 'required|in:fixed,cpm,cpc',
            'rate' => 'nullable|numeric|min:0|max:100000000', 'budget' => 'nullable|numeric|min:0|max:1000000000', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date',
            'status' => 'required|in:draft,active,paused,completed', 'notes' => 'nullable|max:5000',
        ], ['advertiser_id' => 'विज्ञापनदाता', 'name' => 'नाम', 'pricing' => 'भाव का तरीका', 'rate' => 'भाव', 'budget' => 'बजट', 'start_date' => 'शुरू', 'end_date' => 'ख़त्म', 'status' => 'स्थिति', 'notes' => 'नोट्स']);
        $start = $v['start_date'] ? date('Y-m-d', strtotime((string) $v['start_date'])) : null;
        $end = $v['end_date'] ? date('Y-m-d', strtotime((string) $v['end_date'])) : null;
        if ($start && $end && $end < $start) {
            throw new ValidationException(['end_date' => 'ख़त्म होने की तारीख़ शुरू के बाद की हो।'], $request->post());
        }
        if ($v['pricing'] !== 'fixed' && (float) $v['rate'] <= 0) {
            throw new ValidationException(['rate' => 'CPM/CPC के लिए भाव (₹) डालें।'], $request->post());
        }
        return ['advertiser_id' => (int) $v['advertiser_id'], 'name' => trim(strip_tags((string) $v['name'])), 'pricing' => $v['pricing'], 'rate' => round((float) $v['rate'], 2),
            'budget' => round((float) $v['budget'], 2), 'start_date' => $start, 'end_date' => $end, 'status' => $v['status'], 'notes' => $v['notes'] ? strip_tags((string) $v['notes']) : null];
    }

    private function advertisers(): array
    {
        return array_column(db()->all("SELECT id, company FROM {p}advertisers WHERE status <> 'inactive' ORDER BY company"), 'company', 'id');
    }

    private function find(int $id): array
    {
        return AdCampaign::find($id) ?? throw new HttpException(404);
    }
}
