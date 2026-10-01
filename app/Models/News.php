<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** ख़बर। स्थिति सिर्फ़ NewsWorkflow से बदलती है; ट्रैश = deleted_at */
final class News extends Model
{
    protected static string $table = 'news';
    protected static array $fillable = ['title', 'subtitle', 'slug', 'summary', 'content', 'featured_image', 'image_caption', 'image_credit', 'video_url', 'audio_file',
        'category_id', 'location_id', 'reporter_id', 'editor_id', 'assignment_id', 'source', 'news_credit', 'status',
        'is_breaking', 'is_featured', 'is_trending', 'is_editor_pick', 'is_exclusive', 'is_live', 'is_sponsored',
        'published_at', 'scheduled_at', 'correction_note', 'corrected_at', 'meta_title', 'meta_description', 'meta_keywords', 'focus_keyword', 'og_title', 'og_description', 'og_image', 'faq', 'allow_comments', 'allow_listen', 'canonical_url', 'robots',
        'word_count', 'language_id', 'created_by', 'updated_by', 'deleted_at'];
    protected static bool $softDeletes = true;

    /** फ़्लैग: कॉलम => लेबल */
    public const FLAGS = [
        'is_breaking' => ['ब्रेकिंग', 'fa-bolt'], 'is_featured' => ['फ़ीचर्ड', 'fa-star'], 'is_trending' => ['ट्रेंडिंग', 'fa-fire'],
        'is_editor_pick' => ['एडिटर पिक', 'fa-award'], 'is_exclusive' => ['एक्सक्लूसिव', 'fa-gem'], 'is_live' => ['लाइव', 'fa-tower-broadcast'],
        'is_sponsored' => ['प्रायोजित', 'fa-rectangle-ad'],
    ];
    public const ROBOTS = ['index,follow' => 'Index, Follow (सामान्य)', 'noindex,follow' => 'Noindex, Follow', 'noindex,nofollow' => 'Noindex, Nofollow'];
}
