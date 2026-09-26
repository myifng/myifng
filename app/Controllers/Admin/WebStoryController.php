<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\ValidationException;
use App\Models\WebStory;
use App\Models\WebStorySlide;
use App\Services\EmbedService;
use App\Services\MediaService;
use App\Services\MultimediaService;

/** वेब स्टोरी: 9:16 स्लाइड बिल्डर (इमेज/वीडियो, शीर्षक, टेक्स्ट, CTA, जगह, थीम, अवधि) */
final class WebStoryController extends MultimediaController
{
    protected const KIND = 'story';
    protected const LABELS = ['one' => 'वेब स्टोरी', 'many' => 'वेब स्टोरी', 'new' => 'नई वेब स्टोरी', 'icon' => 'fa-mobile-screen',
        'desc' => 'मोबाइल के लिए खड़ी (9:16) स्लाइड वाली कहानियाँ। Google Discover के लिए AMP संस्करण अपने आप।'];

    private array $slides = [];

    protected function model(): string
    {
        return WebStory::class;
    }

    protected function formData(?array $row): array
    {
        $slides = $row ? db()->all('SELECT * FROM {p}web_story_slides WHERE story_id = ? ORDER BY sort_order, id', [$row['id']]) : [];
        return ['slides' => $slides];
    }

    protected function payload(Request $request, ?array $row): array
    {
        $data = MultimediaService::payload($request, 'web_stories', 'web_stories', $row);
        $data['description'] = $data['description'] !== null ? mb_substr($data['description'], 0, 500) : null;
        $old = $row ? array_column(db()->all('SELECT media FROM {p}web_story_slides WHERE story_id = ?', [$row['id']]), 'media') : [];
        $this->slides = [];
        $raw = $request->post()['slides'] ?? [];
        $errors = [];
        foreach (is_array($raw) ? array_values($raw) : [] as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            $n = $i + 1;
            $t = static fn($k, $max) => ($v = trim(strip_tags((string) ($s[$k] ?? '')))) === '' ? null : mb_substr($v, 0, $max);
            $media = trim((string) ($s['media'] ?? ''));
            $type = 'image';
            if ($media !== '') {
                if (in_array($media, $old, true) || MediaService::validPath($media, 'image')) {
                    $type = preg_match('/\.(mp4|webm|mov)$/i', $media) ? 'video' : 'image';
                } elseif (MediaService::validPath($media, 'video')) {
                    $type = 'video';
                } else {
                    $errors["slides.$n"] = "स्लाइड $n: इमेज/वीडियो मीडिया लाइब्रेरी से चुनें।";
                    continue;
                }
            }
            $slide = [
                'media' => $media ?: null, 'media_type' => $type, 'heading' => $t('heading', 200), 'body' => $t('body', 600),
                'cta_label' => $t('cta_label', 60), 'cta_url' => null,
                'text_position' => in_array($s['text_position'] ?? '', ['top', 'center', 'bottom'], true) ? $s['text_position'] : 'bottom',
                'theme' => in_array($s['theme'] ?? '', ['dark', 'light', 'brand'], true) ? $s['theme'] : 'dark',
                'duration' => max(3, min(20, (int) ($s['duration'] ?? 7))),
            ];
            if (!$slide['media'] && !$slide['heading'] && !$slide['body']) {
                continue; // पूरी ख़ाली स्लाइड छोड़ दें
            }
            $cta = trim((string) ($s['cta_url'] ?? ''));
            if ($cta !== '') {
                if (!EmbedService::safeLink($cta)) {
                    $errors["slides.$n"] = "स्लाइड $n: बटन का लिंक https:// या साइट के / पाथ से शुरू हो।";
                    continue;
                }
                $slide['cta_url'] = mb_substr($cta, 0, 500);
                $slide['cta_label'] ??= 'और पढ़ें';
            }
            $this->slides[] = $slide;
        }
        if (count($this->slides) > WebStorySlide::MAX) {
            $errors['slides'] = 'ज़्यादा से ज़्यादा ' . WebStorySlide::MAX . ' स्लाइड।';
        }
        if ($data['status'] === 'published' && !$this->slides) {
            $errors['slides'] = 'प्रकाशित करने के लिए कम से कम एक स्लाइड बनाएँ।';
        }
        if ($errors) {
            throw new ValidationException(['slides' => implode(' ', $errors)], $request->post());
        }
        if (!$data['cover']) {
            foreach ($this->slides as $s) {
                if ($s['media'] && $s['media_type'] === 'image') {
                    $data['cover'] = $s['media'];
                    break;
                }
            }
        }
        return $data;
    }

    protected function afterSave(int $id, Request $request, ?array $row): void
    {
        db()->query('DELETE FROM {p}web_story_slides WHERE story_id = ?', [$id]);
        foreach ($this->slides as $i => $s) {
            WebStorySlide::create($s + ['story_id' => $id, 'sort_order' => $i + 1]);
        }
    }
}
