<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class WebStorySlide extends Model
{
    protected static string $table = 'web_story_slides';
    protected static bool $timestamps = false;
    protected static array $fillable = ['story_id', 'sort_order', 'media', 'media_type', 'heading', 'body', 'cta_label', 'cta_url', 'text_position', 'theme', 'duration'];

    public const POSITIONS = ['top' => 'ऊपर', 'center' => 'बीच में', 'bottom' => 'नीचे'];
    public const THEMES = ['dark' => 'गहरा (सफ़ेद टेक्स्ट)', 'light' => 'हल्का (काला टेक्स्ट)', 'brand' => 'ब्रांड रंग'];
    public const MAX = 30;
}
