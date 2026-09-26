<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** टॉपिक (जैसे "लोकसभा चुनाव") और विशेष सेक्शन (बैनर, रंग, अलग पेज) */
final class Topic extends Model
{
    protected static string $table = 'topics';
    protected static array $fillable = ['type', 'name', 'slug', 'description', 'image', 'banner', 'color', 'is_featured', 'sort_order', 'status', 'meta_title', 'meta_description'];

    public const TYPES = ['topic' => 'टॉपिक', 'special' => 'विशेष सेक्शन'];
    public const STATUSES = ['active' => 'चालू', 'inactive' => 'बंद'];
}
