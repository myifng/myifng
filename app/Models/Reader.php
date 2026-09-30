<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Reader extends Model
{
    protected static string $table = 'readers';
    protected static array $fillable = ['name', 'email', 'password', 'mobile', 'location_id', 'status', 'email_verified_at', 'prefs', 'history_enabled', 'trusted', 'last_login_at'];

    public const STATUSES = ['pending' => 'ईमेल सत्यापन बाकी', 'active' => 'चालू', 'blocked' => 'ब्लॉक'];
}
