<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static bool $timestamps = false;
    protected static array $fillable = ['recipient_type', 'recipient_id', 'event', 'title', 'body', 'url', 'read_at', 'created_at'];
}
