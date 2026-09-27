<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** पेज का वह हिस्सा जो किसी ख़बर/लिंक से जुड़ा है (x, y, w, h = पेज का %) */
final class EpaperHotspot extends Model
{
    protected static string $table = 'epaper_hotspots';
    protected static bool $timestamps = false;
    protected static array $fillable = ['page_id', 'x', 'y', 'w', 'h', 'news_id', 'url', 'label'];
}
