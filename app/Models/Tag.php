<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Tag extends Model
{
    protected static string $table = 'tags';
    protected static array $fillable = ['name', 'slug', 'description', 'usage_count', 'meta_title', 'meta_description'];
}
