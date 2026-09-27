<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EpaperPage extends Model
{
    protected static string $table = 'epaper_pages';
    protected static bool $timestamps = false;
    protected static array $fillable = ['issue_id', 'page_no', 'image', 'thumb', 'width', 'height', 'label', 'created_at'];
}
