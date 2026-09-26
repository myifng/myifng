<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class GalleryPhoto extends Model
{
    protected static string $table = 'gallery_photos';
    protected static bool $timestamps = false;
    protected static array $fillable = ['gallery_id', 'image', 'caption', 'photographer', 'location', 'credit', 'copyright', 'sort_order'];
}
