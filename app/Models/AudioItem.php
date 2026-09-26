<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** ऑडियो न्यूज़ या पॉडकास्ट एपिसोड */
final class AudioItem extends Model
{
    protected static string $table = 'audio_items';
    protected static array $fillable = ['title', 'slug', 'description', 'type', 'series_id', 'episode_no', 'file', 'external_url', 'duration', 'cover', 'transcript', 'news_id',
        'tts_status', 'category_id', 'status', 'is_featured', 'published_at', 'meta_title', 'meta_description', 'created_by', 'updated_by'];

    public const TYPES = ['news' => 'ऑडियो न्यूज़', 'episode' => 'पॉडकास्ट एपिसोड'];
}
