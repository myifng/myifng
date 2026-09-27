<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AdInvoice;

/**
 * विज्ञापनदाता CRM: प्रदर्शन, बिल/बकाया, इनवॉइस का हिसाब, राजस्व।
 * आगे विज्ञापनदाता लॉगिन: advertisers.user_id से forUser() (अपना पोर्टल बाद के phase में)।
 */
final class AdvertiserService
{
    public static function forUser(int $userId): ?array
    {
        return db()->first('SELECT * FROM {p}advertisers WHERE user_id = ?', [$userId]);
    }

    /** ₹ 1,23,456.00 (भारतीय तरीके से) */
    public static function money(float|int|string|null $n): string
    {
        $n = (float) $n;
        $neg = $n < 0;
        [$int, $dec] = explode('.', number_format(abs($n), 2, '.', ''));
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $int = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3 : $last3;
        return ($neg ? '-' : '') . '₹' . $int . '.' . $dec;
    }

    /** प्रदर्शन + पैसा: विज्ञापनदाता या कैंपेन */
    public static function summary(?int $advertiserId = null, ?int $campaignId = null): array
    {
        $w = $campaignId ? 'c.id = ' . $campaignId : ($advertiserId ? 'c.advertiser_id = ' . $advertiserId : '1=1');
        $perf = db()->first("SELECT COUNT(DISTINCT a.id) ads, COALESCE(SUM(a.impressions), 0) imp, COALESCE(SUM(a.clicks), 0) clk
                             FROM {p}ad_campaigns c LEFT JOIN {p}ads a ON a.campaign_id = c.id WHERE $w");
        $iw = $campaignId ? 'i.campaign_id = ' . $campaignId : ($advertiserId ? 'i.advertiser_id = ' . $advertiserId : '1=1');
        $money = db()->first("SELECT COALESCE(SUM(i.total), 0) billed, COALESCE(SUM(i.paid), 0) paid FROM {p}ad_invoices i WHERE $iw AND i.status NOT IN ('draft','cancelled')");
        return ['ads' => (int) $perf['ads'], 'impressions' => (int) $perf['imp'], 'clicks' => (int) $perf['clk'],
            'billed' => (float) $money['billed'], 'paid' => (float) $money['paid'], 'due' => max(0, (float) $money['billed'] - (float) $money['paid'])];
    }

    /** इनवॉइस की पंक्तियों से कुल, भुगतान से स्थिति */
    public static function recalc(int $invoiceId): void
    {
        $inv = AdInvoice::find($invoiceId);
        if (!$inv) {
            return;
        }
        $sub = (float) db()->value('SELECT COALESCE(SUM(amount), 0) FROM {p}ad_invoice_items WHERE invoice_id = ?', [$invoiceId]);
        $tax = round($sub * (float) $inv['tax_rate'] / 100, 2);
        $total = round($sub + $tax, 2);
        $paid = (float) db()->value('SELECT COALESCE(SUM(amount), 0) FROM {p}ad_payments WHERE invoice_id = ?', [$invoiceId]);
        $status = $inv['status'];
        if ($status !== 'cancelled') {
            if ($total > 0 && $paid >= $total) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partial';
            } elseif (in_array($status, ['paid', 'partial'], true)) {
                $status = 'sent';
            }
        }
        AdInvoice::update($invoiceId, ['subtotal' => $sub, 'tax' => $tax, 'total' => $total, 'paid' => $paid, 'status' => $status]);
    }

    /** INV-2026-00012 (ID से, कभी दोहराव नहीं) */
    public static function invoiceNo(int $id, string $date): string
    {
        return sprintf('INV-%s-%05d', date('Y', strtotime($date)), $id);
    }

    /** पिछले 12 महीने: भुगतान (राजस्व) और बिल */
    public static function monthly(int $months = 12, ?int $advertiserId = null): array
    {
        $from = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $aw = $advertiserId ? ' AND advertiser_id = ' . $advertiserId : '';
        $paid = array_column(db()->all("SELECT DATE_FORMAT(paid_on, '%Y-%m') m, SUM(amount) s FROM {p}ad_payments WHERE paid_on >= ?$aw GROUP BY m", [$from]), 's', 'm');
        $billed = array_column(db()->all("SELECT DATE_FORMAT(issue_date, '%Y-%m') m, SUM(total) s FROM {p}ad_invoices WHERE issue_date >= ? AND status NOT IN ('draft','cancelled')$aw GROUP BY m", [$from]), 's', 'm');
        $out = ['labels' => [], 'paid' => [], 'billed' => []];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
            $out['labels'][] = explode(' ', hindi_date($m . '-01'), 2)[1] ?? $m;
            $out['paid'][] = round((float) ($paid[$m] ?? 0));
            $out['billed'][] = round((float) ($billed[$m] ?? 0));
        }
        return $out;
    }
}
