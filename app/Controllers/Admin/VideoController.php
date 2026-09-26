<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\ValidationException;
use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Services\EmbedService;
use App\Services\MediaService;
use App\Services\MultimediaService;

/** वीडियो: YouTube / अपलोड / एम्बेड, प्रकार (शॉर्ट, इंटरव्यू, ग्राउंड रिपोर्ट, शो), प्लेलिस्ट */
final class VideoController extends MultimediaController
{
    protected const KIND = 'video';
    protected const LABELS = ['one' => 'वीडियो', 'many' => 'वीडियो', 'new' => 'नया वीडियो', 'icon' => 'fa-video',
        'desc' => 'वीडियो, शॉर्ट्स, इंटरव्यू, ग्राउंड रिपोर्ट और शो। YouTube लिंक, मीडिया लाइब्रेरी से अपलोड या एम्बेड।'];
    protected const TYPES = Video::TYPES;

    protected function model(): string
    {
        return Video::class;
    }

    protected function formData(?array $row): array
    {
        $loc = !empty($row['location_id']) ? \App\Models\Location::find((int) $row['location_id']) : null;
        return ['playlists' => array_column(db()->all('SELECT id, name, type FROM {p}video_playlists ORDER BY sort_order, name'), 'name', 'id'), 'location' => $loc];
    }

    protected function payload(Request $request, ?array $row): array
    {
        $data = MultimediaService::payload($request, 'videos', 'videos', $row, [
            'type' => 'required|in:' . implode(',', array_keys(Video::TYPES)), 'source' => 'required|in:' . implode(',', array_keys(Video::SOURCES)),
            'source_url' => 'nullable|max:2000', 'playlist_id' => 'nullable|integer|exists:video_playlists,id', 'location_id' => 'nullable|integer|exists:locations,id',
            'credit' => 'nullable|max:150', 'duration' => 'nullable|max:10',
        ], ['type' => 'प्रकार', 'source' => 'स्रोत', 'source_url' => 'लिंक', 'playlist_id' => 'प्लेलिस्ट', 'location_id' => 'लोकेशन', 'credit' => 'क्रेडिट', 'duration' => 'अवधि']);
        $errors = [];
        $url = trim((string) $data['source_url']);
        $file = $request->str('file');
        switch ($data['source']) {
            case 'youtube':
                if (!EmbedService::youtubeId($url)) {
                    $errors['source_url'] = 'YouTube वीडियो का लिंक डालें (youtu.be/…, youtube.com/watch?v=…, /shorts/…)।';
                }
                $file = '';
                break;
            case 'embed':
                $url = (string) EmbedService::iframeSrc($url);
                if ($url === '') {
                    $errors['source_url'] = 'https:// वाला iframe पता या पूरा <iframe> कोड डालें।';
                }
                $file = '';
                break;
            case 'upload':
                if ($file === '' || ($file !== ($row['file'] ?? '') && !MediaService::validPath($file, 'video'))) {
                    $errors['file'] = 'मीडिया लाइब्रेरी से वीडियो फ़ाइल चुनें।';
                }
                $url = '';
                break;
        }
        $duration = EmbedService::parseDuration($data['duration']);
        if ($data['duration'] !== null && $data['duration'] !== '' && $duration === null) {
            $errors['duration'] = 'अवधि मिनट:सेकंड में लिखें, जैसे 4:35 या 1:02:10।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        return array_merge($data, [
            'source_url' => $url ?: null, 'file' => $file ?: null, 'duration' => $duration,
            'playlist_id' => $data['playlist_id'] ? (int) $data['playlist_id'] : null, 'location_id' => $data['location_id'] ? (int) $data['location_id'] : null,
            'credit' => $data['credit'] ? strip_tags((string) $data['credit']) : null,
        ]);
    }
}
