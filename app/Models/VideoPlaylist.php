<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class VideoPlaylist extends Model
{
    protected static string $table = 'video_playlists';
    protected static array $fillable = ['name', 'slug', 'type', 'description', 'cover', 'status', 'sort_order'];

    public const TYPES = ['playlist' => 'प्लेलिस्ट', 'show' => 'शो'];
}
