<?php
declare(strict_types=1);

namespace App\Services;

/**
 * संपादकीय वर्कफ़्लो: स्थितियाँ और उनके बीच के बदलाव (कौन, किस स्थिति से, किस अनुमति से)।
 * ख़बर की स्थिति सिर्फ़ यहीं से बदलती है; फ़ॉर्म से status कभी नहीं आता।
 *   ड्राफ़्ट → भेजी गई → डेस्क समीक्षा → फ़ैक्ट चेक → मंज़ूर → शेड्यूल/प्रकाशित → आर्काइव
 */
final class NewsWorkflow
{
    /** स्थिति => [लेबल, रंग] */
    public const STATUSES = [
        'draft' => ['ड्राफ़्ट', 'secondary'],
        'submitted' => ['भेजी गई', 'info'],
        'review' => ['डेस्क समीक्षा', 'primary'],
        'fact_check' => ['फ़ैक्ट चेक', 'warning'],
        'approved' => ['मंज़ूर', 'teal'],
        'scheduled' => ['शेड्यूल', 'purple'],
        'published' => ['प्रकाशित', 'success'],
        'rejected' => ['अस्वीकार', 'danger'],
        'disabled' => ['बंद', 'dark'],
        'archived' => ['आर्काइव', 'light'],
    ];

    /** रिपोर्टर (मालिक) इन स्थितियों में अपनी ख़बर बदल सकता है */
    public const OWNER_EDITABLE = ['draft', 'rejected'];

    /**
     * action => [from[], to, अनुमति, remark ज़रूरी?, बटन लेबल, आइकन, सिर्फ़ मालिक/डेस्क?]
     * 'owner' = मालिक (या news.approve वाला); अनुमति null = सिर्फ़ मालिक-नियम
     */
    public const ACTIONS = [
        'submit' => [['draft', 'rejected'], 'submitted', 'news.create', false, 'डेस्क को भेजें', 'fa-paper-plane', 'owner'],
        'withdraw' => [['submitted'], 'draft', 'news.create', false, 'वापस लें (ड्राफ़्ट)', 'fa-rotate-left', 'owner'],
        'review' => [['submitted'], 'review', 'news.approve', false, 'समीक्षा शुरू करें', 'fa-magnifying-glass', null],
        'fact_check' => [['submitted', 'review'], 'fact_check', 'news.approve', false, 'फ़ैक्ट चेक में भेजें', 'fa-scale-balanced', null],
        'approve' => [['submitted', 'review', 'fact_check'], 'approved', 'news.approve', false, 'मंज़ूर करें', 'fa-circle-check', null],
        'reject' => [['submitted', 'review', 'fact_check', 'approved'], 'rejected', 'news.approve', true, 'अस्वीकार / सुधार माँगें', 'fa-circle-xmark', null],
        'publish' => [['draft', 'submitted', 'review', 'fact_check', 'approved', 'scheduled', 'disabled', 'archived'], 'published', 'news.publish', false, 'अभी प्रकाशित करें', 'fa-globe', null],
        'schedule' => [['draft', 'submitted', 'review', 'fact_check', 'approved'], 'scheduled', 'news.publish', false, 'शेड्यूल करें', 'fa-calendar-check', null],
        'unschedule' => [['scheduled'], 'approved', 'news.publish', false, 'शेड्यूल हटाएँ', 'fa-calendar-xmark', null],
        'disable' => [['published'], 'disabled', 'news.publish', true, 'वेबसाइट से हटाएँ (बंद)', 'fa-eye-slash', null],
        'archive' => [['published', 'disabled'], 'archived', 'news.publish', false, 'आर्काइव करें', 'fa-box-archive', null],
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function badge(string $status): string
    {
        [$l, $c] = self::STATUSES[$status] ?? [$status, 'secondary'];
        return '<span class="badge-status st-' . e($status) . ' text-bg-' . e($c) . '"><i class="dot"></i>' . e($l) . '</span>';
    }

    /** मौजूदा यूज़र इस ख़बर पर यह काम कर सकता है? (कारण के साथ) */
    public static function check(array $news, string $action): ?string
    {
        $a = self::ACTIONS[$action] ?? null;
        if (!$a) {
            return 'यह काम मान्य नहीं है।';
        }
        [$from, , $perm, , , , $who] = $a;
        if ($news['deleted_at'] !== null) {
            return 'ट्रैश में पड़ी ख़बर पर यह काम नहीं हो सकता।';
        }
        if (!in_array($news['status'], $from, true)) {
            return '“' . self::label($news['status']) . '” स्थिति वाली ख़बर पर यह काम नहीं हो सकता।';
        }
        if (!can($perm)) {
            return 'आपके पास इसकी अनुमति नहीं है।';
        }
        if ($who === 'owner' && !NewsService::isOwner($news) && ($action === 'withdraw' || !can('news.approve'))) {
            return 'यह सिर्फ़ ख़बर लिखने वाला कर सकता है।';
        }
        return null;
    }

    /** इस यूज़र के लिए अभी उपलब्ध काम (बटन) */
    public static function available(array $news): array
    {
        $out = [];
        foreach (self::ACTIONS as $k => $a) {
            if (self::check($news, $k) === null) {
                $out[$k] = ['label' => $a[4], 'icon' => $a[5], 'remark' => $a[3], 'to' => $a[1]];
            }
        }
        return $out;
    }

    public static function target(string $action): string
    {
        return self::ACTIONS[$action][1];
    }

    public static function needsRemark(string $action): bool
    {
        return (bool) self::ACTIONS[$action][3];
    }
}
