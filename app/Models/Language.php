<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Language extends Model
{
    protected static string $table = 'languages';
    protected static array $fillable = ['code', 'name', 'native_name', 'direction', 'is_default', 'status', 'sort_order'];
    protected static bool $timestamps = false;
    protected static bool $softDeletes = false;
}
