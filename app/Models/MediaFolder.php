<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class MediaFolder extends Model
{
    protected static string $table = 'media_folders';
    protected static array $fillable = ['parent_id', 'name', 'created_by'];
}
