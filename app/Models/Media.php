<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** मीडिया लाइब्रेरी की एक फ़ाइल (मूल + वेरिएंट) */
final class Media extends Model
{
    protected static string $table = 'media';
    protected static array $fillable = ['folder_id', 'file', 'original_name', 'mime', 'kind', 'size', 'width', 'height', 'variants',
        'title', 'alt', 'caption', 'credit', 'keywords', 'uploaded_by', 'deleted_at'];
    protected static bool $softDeletes = true;

    public const KINDS = ['image' => 'इमेज', 'video' => 'वीडियो', 'audio' => 'ऑडियो', 'document' => 'दस्तावेज़'];
    public const ICONS = ['image' => 'fa-image', 'video' => 'fa-film', 'audio' => 'fa-music', 'document' => 'fa-file-lines'];
}
