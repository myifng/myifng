<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static array $fillable = ['module', 'action', 'name'];
    protected static bool $timestamps = false;
    protected static bool $softDeletes = false;
}
