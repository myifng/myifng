<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected static array $fillable = [];
    protected static bool $timestamps = false;
    protected static bool $softDeletes = false;
}
