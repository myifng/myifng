<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AdCampaign extends Model
{
    protected static string $table = 'ad_campaigns';
    protected static array $fillable = ['advertiser_id', 'name', 'pricing', 'rate', 'budget', 'start_date', 'end_date', 'status', 'notes', 'created_by'];

    public const PRICING = ['fixed' => 'तय राशि', 'cpm' => 'CPM (प्रति 1000 इम्प्रेशन)', 'cpc' => 'CPC (प्रति क्लिक)'];
    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'active' => 'चालू', 'paused' => 'रुका', 'completed' => 'पूरा'];
}
