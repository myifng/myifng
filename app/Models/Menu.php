<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Menu extends Model
{
    protected static string $table = 'menus';
    protected static array $fillable = ['name', 'location', 'description'];
}
