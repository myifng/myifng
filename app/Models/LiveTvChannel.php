<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LiveTvChannel extends Model
{
    protected static string $table = 'live_tv_channels';
    protected static array $fillable = ['name', 'slug', 'source_type', 'source_url', 'logo', 'description', 'is_default', 'is_live', 'status', 'sort_order'];

    public const SOURCES = ['youtube' => 'YouTube लाइव', 'embed' => 'एम्बेड (iframe)', 'stream' => 'स्ट्रीमिंग URL (HLS .m3u8 / MP4)'];
}
