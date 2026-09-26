<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** लाइव ब्लॉग: एक ख़बर + समय वाले अपडेट */
final class LiveBlog extends Model
{
    protected static string $table = 'live_blogs';
    protected static array $fillable = ['news_id', 'status', 'started_at', 'ended_at', 'created_by'];

    public const STATUSES = ['live' => 'लाइव', 'paused' => 'रुका हुआ', 'ended' => 'ख़त्म'];
}
