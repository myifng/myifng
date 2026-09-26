<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LoginHistory extends Model
{
    protected static string $table = 'login_history';
    protected static array $fillable = [];
    protected static bool $timestamps = false;
    protected static bool $softDeletes = false;
}
