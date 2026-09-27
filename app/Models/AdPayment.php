<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AdPayment extends Model
{
    protected static string $table = 'ad_payments';
    protected static bool $timestamps = false;
    protected static array $fillable = ['invoice_id', 'advertiser_id', 'amount', 'paid_on', 'method', 'reference', 'notes', 'created_by', 'created_at'];

    public const METHODS = ['bank' => 'बैंक ट्रांसफ़र', 'upi' => 'UPI', 'cash' => 'नकद', 'cheque' => 'चेक', 'online' => 'ऑनलाइन', 'other' => 'अन्य'];
}
