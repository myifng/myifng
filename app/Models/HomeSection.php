<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class HomeSection extends Model
{
    protected static string $table = 'home_sections';
    protected static array $fillable = ['block_type', 'title', 'settings', 'show_desktop', 'show_mobile', 'is_active', 'sort_order', 'created_by'];
}
