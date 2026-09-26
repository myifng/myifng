<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\ValidationException;
use App\Models\AudioItem;
use App\Services\EmbedService;
use App\Services\MediaService;
use App\Services\MultimediaService;
use App\Services\TtsService;

/** ऑडियो न्यूज़ और पॉडकास्ट एपिसोड (अपलोड या बाहरी https लिंक), ट्रांसक्रिप्ट (TTS के लिए तैयार) */
final class AudioController extends MultimediaController
{
    protected const KIND = 'audio';
    protected const LABELS = ['one' => 'ऑडियो', 'many' => 'ऑडियो / पॉडकास्ट', 'new' => 'नया ऑडियो', 'icon' => 'fa-podcast',
        'desc' => 'ऑडियो न्यूज़ और पॉडकास्ट एपिसोड। सीरीज़ का RSS फ़ीड Apple Podcasts / Spotify में जोड़ा जा सकता है।'];
    protected const TYPES = AudioItem::TYPES;

    protected function model(): string
    {
        return AudioItem::class;
    }

    protected function formData(?array $row): array
    {
        $news = !empty($row['news_id']) ? db()->first('SELECT id, title FROM {p}news WHERE id = ?', [$row['news_id']]) : null;
        return ['series' => array_column(db()->all('SELECT id, title FROM {p}podcast_series ORDER BY sort_order, title'), 'title', 'id'), 'linkedNews' => $news, 'tts' => TtsService::available()];
    }

    protected function payload(Request $request, ?array $row): array
    {
        $data = MultimediaService::payload($request, 'audio_items', 'audio', $row, [
            'type' => 'required|in:news,episode', 'series_id' => 'nullable|integer|exists:podcast_series,id', 'episode_no' => 'nullable|integer|min:1|max:100000',
            'external_url' => 'nullable|max:500', 'duration' => 'nullable|max:10', 'transcript' => 'nullable|max:100000', 'news_id' => 'nullable|integer|exists:news,id',
        ], ['type' => 'प्रकार', 'series_id' => 'सीरीज़', 'episode_no' => 'एपिसोड नंबर', 'external_url' => 'बाहरी लिंक', 'duration' => 'अवधि', 'transcript' => 'ट्रांसक्रिप्ट', 'news_id' => 'ख़बर']);
        $errors = [];
        $file = $request->str('file');
        if ($file !== ($row['file'] ?? '') && !MediaService::validPath($file, 'audio')) {
            $errors['file'] = 'ऑडियो फ़ाइल मीडिया लाइब्रेरी से चुनें।';
        }
        $ext = trim((string) $data['external_url']);
        if ($ext !== '' && !preg_match('~^https://~i', (string) EmbedService::safeLink($ext))) {
            $errors['external_url'] = 'पूरा https:// लिंक डालें (MP3/M4A)।';
        }
        if ($file === '' && $ext === '' && $data['status'] === 'published') {
            $errors['file'] = 'प्रकाशित करने के लिए ऑडियो फ़ाइल चुनें या बाहरी लिंक डालें।';
        }
        if ($data['type'] === 'episode' && !$data['series_id']) {
            $errors['series_id'] = 'पॉडकास्ट एपिसोड के लिए सीरीज़ चुनें।';
        }
        $duration = EmbedService::parseDuration($data['duration']);
        if ((string) $data['duration'] !== '' && $duration === null) {
            $errors['duration'] = 'अवधि मिनट:सेकंड में लिखें, जैसे 12:30।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $transcript = trim(str_replace("\r", '', strip_tags((string) $data['transcript'])));
        return array_merge($data, [
            'file' => $file ?: null, 'external_url' => $ext ?: null, 'duration' => $duration, 'transcript' => $transcript ?: null,
            'series_id' => $data['type'] === 'episode' && $data['series_id'] ? (int) $data['series_id'] : null,
            'episode_no' => $data['type'] === 'episode' && $data['episode_no'] ? (int) $data['episode_no'] : null,
            'news_id' => $data['news_id'] ? (int) $data['news_id'] : null,
        ]);
    }
}
