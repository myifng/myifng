<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** फ़ोटो गैलरी (एल्बम) */
final class Gallery extends Model
{
    protected static string $table = 'galleries';
    protected static array $fillable = ['title', 'slug', 'description', 'cover', 'photographer', 'location_id', 'category_id', 'status', 'is_featured', 'published_at',
        'meta_title', 'meta_description', 'created_by', 'updated_by'];
}
