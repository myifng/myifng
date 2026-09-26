<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class WebStory extends Model
{
    protected static string $table = 'web_stories';
    protected static array $fillable = ['title', 'slug', 'description', 'cover', 'category_id', 'status', 'is_featured', 'published_at', 'meta_title', 'meta_description', 'created_by', 'updated_by'];
}
