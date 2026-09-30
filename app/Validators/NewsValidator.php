<?php
declare(strict_types=1);

namespace App\Validators;

final class NewsValidator
{
    public const LABELS = [
        'title' => 'शीर्षक', 'subtitle' => 'उप-शीर्षक', 'slug' => 'URL (स्लग)', 'summary' => 'सार', 'image_caption' => 'इमेज कैप्शन',
        'image_credit' => 'इमेज क्रेडिट', 'video_url' => 'वीडियो', 'category_id' => 'श्रेणी', 'location_id' => 'लोकेशन', 'reporter_id' => 'रिपोर्टर',
        'source' => 'स्रोत', 'news_credit' => 'न्यूज़ क्रेडिट', 'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण',
        'meta_keywords' => 'कीवर्ड', 'focus_keyword' => 'फ़ोकस कीवर्ड', 'og_title' => 'शेयर शीर्षक', 'og_description' => 'शेयर विवरण', 'canonical_url' => 'Canonical URL', 'robots' => 'सर्च इंजन', 'change_reason' => 'बदलाव का कारण',
        'scheduled_at' => 'शेड्यूल का समय',
    ];

    public static function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'subtitle' => 'nullable|max:255',
            'slug' => 'nullable|slug|max:200',
            'summary' => 'nullable|max:600',
            'image_caption' => 'nullable|max:300',
            'image_credit' => 'nullable|max:150',
            'video_url' => 'nullable|max:500',
            'category_id' => 'nullable|integer|exists:categories,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'reporter_id' => 'nullable|integer',
            'source' => 'nullable|max:150',
            'news_credit' => 'nullable|max:150',
            'meta_title' => 'nullable|max:190',
            'meta_description' => 'nullable|max:320',
            'meta_keywords' => 'nullable|max:255',
            'focus_keyword' => 'nullable|max:100',
            'og_title' => 'nullable|max:190',
            'og_description' => 'nullable|max:320',
            'canonical_url' => 'nullable|url|max:500',
            'robots' => 'required|max:40',
            'change_reason' => 'nullable|max:500',
            'scheduled_at' => 'nullable|date',
        ];
    }
}
