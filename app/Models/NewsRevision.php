<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NewsRevision extends Model
{
    protected static string $table = 'news_revisions';
    protected static array $fillable = ['news_id', 'version', 'title', 'summary', 'content', 'data', 'status', 'reason', 'is_correction', 'changed_by', 'created_at'];
    protected static bool $timestamps = false;
}
