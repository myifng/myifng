<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Page;

final class PageValidator
{
    public const LABELS = [
        'title' => 'शीर्षक', 'slug' => 'URL (स्लग)', 'excerpt' => 'सार', 'template' => 'टेम्पलेट', 'status' => 'स्थिति',
        'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'meta_keywords' => 'कीवर्ड', 'robots' => 'सर्च इंजन',
    ];

    public static function rules(): array
    {
        return [
            'title' => 'required|max:190',
            'slug' => 'nullable|slug|max:190',
            'excerpt' => 'nullable|max:500',
            'template' => 'required|in:' . implode(',', array_keys(Page::TEMPLATES)),
            'status' => 'required|in:draft,published',
            'meta_title' => 'nullable|max:190',
            'meta_description' => 'nullable|max:320',
            'meta_keywords' => 'nullable|max:255',
            // robots के मान में comma है, इसलिए यह जाँच PageController में (in: नियम comma से बँटता है)
            'robots' => 'required|max:40',
        ];
    }
}
