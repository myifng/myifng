<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Redirect extends Model
{
    protected static string $table = 'redirects';
    protected static array $fillable = ['source', 'source_hash', 'match_type', 'target', 'code', 'hits', 'last_hit_at', 'is_auto', 'entity', 'note', 'status', 'created_by'];

    public const CODES = [301 => '301 स्थायी (SEO के लिए सही)', 302 => '302 अस्थायी', 307 => '307 अस्थायी (तरीका वही)', 410 => '410 हमेशा के लिए हटा दिया'];
    public const MATCH = ['exact' => 'ठीक यही पता', 'prefix' => 'इससे शुरू होने वाले सब'];
}
