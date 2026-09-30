<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NotFound extends Model
{
    protected static string $table = 'not_found_log';
    protected static bool $timestamps = false;
    protected static array $fillable = ['path', 'path_hash', 'hits', 'bot_hits', 'referrer', 'is_internal', 'user_agent', 'first_seen', 'last_seen', 'status'];

    public const STATUSES = ['new' => 'नया', 'ignored' => 'अनदेखा', 'fixed' => 'ठीक किया'];
}
