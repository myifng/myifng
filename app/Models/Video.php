<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Video extends Model
{
    protected static string $table = 'videos';
    protected static array $fillable = ['title', 'slug', 'description', 'type', 'source', 'source_url', 'file', 'duration', 'cover', 'playlist_id', 'location_id', 'credit',
        'category_id', 'status', 'is_featured', 'published_at', 'meta_title', 'meta_description', 'created_by', 'updated_by'];

    public const TYPES = ['video' => 'वीडियो', 'short' => 'शॉर्ट वीडियो', 'interview' => 'इंटरव्यू', 'ground_report' => 'ग्राउंड रिपोर्ट', 'show' => 'शो'];
    public const SOURCES = ['youtube' => 'YouTube', 'upload' => 'अपलोड (मीडिया लाइब्रेरी)', 'embed' => 'एम्बेड (iframe का https पता)'];
}
