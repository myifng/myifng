<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AdInvoice extends Model
{
    protected static string $table = 'ad_invoices';
    protected static array $fillable = ['invoice_no', 'advertiser_id', 'campaign_id', 'issue_date', 'due_date', 'subtotal', 'tax_rate', 'tax', 'total', 'paid', 'status', 'notes', 'created_by'];

    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'sent' => 'भेजा गया', 'partial' => 'आंशिक भुगतान', 'paid' => 'भुगतान हो गया', 'cancelled' => 'रद्द'];
    public const BADGE = ['draft' => 'secondary', 'sent' => 'info', 'partial' => 'warning', 'paid' => 'success', 'cancelled' => 'dark'];
}
