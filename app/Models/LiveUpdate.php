<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LiveUpdate extends Model
{
    protected static string $table = 'live_updates';
    protected static array $fillable = ['live_blog_id', 'title', 'body', 'image', 'embed_url', 'is_pinned', 'is_key', 'author_id', 'posted_at'];
}
