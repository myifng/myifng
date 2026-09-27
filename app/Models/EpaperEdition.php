<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EpaperEdition extends Model
{
    protected static string $table = 'epaper_editions';
    protected static array $fillable = ['name', 'slug', 'type', 'location_id', 'description', 'is_default', 'status', 'sort_order'];

    public const TYPES = ['main' => 'मुख्य', 'state' => 'राज्य', 'district' => 'ज़िला', 'city' => 'शहर'];
}
