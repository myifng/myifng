<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Bureau extends Model
{
    protected static string $table = 'bureaus';
    protected static array $fillable = ['name', 'type', 'parent_id', 'location_id', 'address', 'phone', 'email', 'chief_user_id', 'status'];

    public const TYPES = ['head_office' => 'मुख्यालय', 'state' => 'राज्य ब्यूरो', 'district' => 'ज़िला ब्यूरो', 'tehsil' => 'तहसील ब्यूरो', 'city' => 'शहर ब्यूरो'];
}
