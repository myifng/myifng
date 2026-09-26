<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';
    protected static array $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];
    protected static bool $timestamps = false;
    protected static bool $softDeletes = false;
}
