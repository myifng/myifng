<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AdSlot extends Model
{
    protected static string $table = 'ad_slots';
    protected static array $fillable = ['slot_key', 'name', 'placement', 'size', 'max_ads', 'is_system', 'status', 'description'];

    public const PLACEMENTS = [
        'header' => 'हेडर', 'below_header' => 'हेडर के नीचे', 'homepage' => 'होमपेज', 'sidebar' => 'साइडबार', 'article_top' => 'ख़बर: ऊपर',
        'article_inline' => 'ख़बर: बीच में', 'article_bottom' => 'ख़बर: नीचे', 'category' => 'श्रेणी पेज', 'mobile_sticky' => 'मोबाइल स्टिकी',
        'popup' => 'पॉपअप', 'footer' => 'फ़ुटर', 'custom' => 'कस्टम',
    ];
}
