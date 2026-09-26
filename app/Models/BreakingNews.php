<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** ब्रेकिंग कंट्रोल रूम का एक आइटम (टिकर / होमपेज अलर्ट / मोबाइल अलर्ट) */
final class BreakingNews extends Model
{
    protected static string $table = 'breaking_news';
    protected static array $fillable = ['title', 'type', 'news_id', 'url', 'priority', 'show_ticker', 'show_banner', 'mobile_alert', 'push', 'push_status',
        'starts_at', 'ends_at', 'status', 'created_by', 'updated_by'];

    public const TYPES = ['breaking' => 'ब्रेकिंग', 'flash' => 'फ़्लैश', 'alert' => 'अलर्ट'];
    public const PRIORITIES = [1 => 'सामान्य', 2 => 'ज़रूरी', 3 => 'अति ज़रूरी'];
}
