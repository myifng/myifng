<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class MenuItem extends Model
{
    protected static string $table = 'menu_items';
    protected static array $fillable = ['menu_id', 'parent_id', 'title', 'type', 'reference_id', 'url', 'target_blank', 'icon', 'is_active', 'sort_order'];
    protected static bool $timestamps = false;
}
