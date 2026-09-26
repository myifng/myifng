<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Page extends Model
{
    protected static string $table = 'pages';
    protected static array $fillable = ['title', 'slug', 'excerpt', 'content', 'featured_image', 'template', 'status', 'show_header', 'show_footer',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'robots', 'language_id', 'published_at', 'created_by', 'updated_by', 'deleted_at'];
    protected static bool $softDeletes = true;

    public const STATUSES = ['published' => 'प्रकाशित', 'draft' => 'ड्राफ़्ट'];
    public const TEMPLATES = [
        'default' => 'सामान्य (साइडबार के साथ)',
        'full-width' => 'पूरी चौड़ाई',
        'contact' => 'संपर्क (संपर्क कार्ड के साथ)',
        'landing' => 'लैंडिंग (बिना शीर्षक/साइडबार)',
    ];
    public const ROBOTS = ['index,follow' => 'Index, Follow (सामान्य)', 'noindex,follow' => 'Noindex, Follow', 'noindex,nofollow' => 'Noindex, Nofollow'];

    public static function findBySlug(string $slug): ?array
    {
        return static::firstWhere('slug', $slug);
    }

    /** slug दोहराया न जाए (ट्रैश वाले पेज भी गिने जाते हैं, क्योंकि unique index है) */
    public static function uniqueSlug(string $slug, int $exceptId = 0): string
    {
        $base = $slug;
        $i = 2;
        while ((int) static::db()->value('SELECT COUNT(*) FROM {p}pages WHERE slug = ? AND id <> ?', [$slug, $exceptId]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
