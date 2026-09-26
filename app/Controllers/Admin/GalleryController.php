<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\ValidationException;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Services\MediaService;
use App\Services\MultimediaService;

/** फ़ोटो गैलरी: एल्बम + फ़ोटो (कैप्शन, फ़ोटोग्राफ़र, स्थान, क्रेडिट, कॉपीराइट), क्रम */
final class GalleryController extends MultimediaController
{
    public const MAX_PHOTOS = 200;

    protected const KIND = 'gallery';
    protected const LABELS = ['one' => 'गैलरी', 'many' => 'फ़ोटो गैलरी', 'new' => 'नई गैलरी', 'icon' => 'fa-images',
        'desc' => 'फ़ोटो एल्बम: हर फ़ोटो का कैप्शन, फ़ोटोग्राफ़र, स्थान, क्रेडिट और कॉपीराइट। वेबसाइट पर फ़ुलस्क्रीन गैलरी।'];

    /** payload में जाँची गई फ़ोटो, afterSave में सेव */
    private array $photos = [];

    protected function model(): string
    {
        return Gallery::class;
    }

    protected function formData(?array $row): array
    {
        $photos = $row ? db()->all('SELECT * FROM {p}gallery_photos WHERE gallery_id = ? ORDER BY sort_order, id', [$row['id']]) : [];
        $loc = !empty($row['location_id']) ? \App\Models\Location::find((int) $row['location_id']) : null;
        return ['photos' => $photos, 'location' => $loc];
    }

    protected function payload(Request $request, ?array $row): array
    {
        $data = MultimediaService::payload($request, 'galleries', 'galleries', $row, [
            'photographer' => 'nullable|max:150', 'location_id' => 'nullable|integer|exists:locations,id',
        ], ['photographer' => 'फ़ोटोग्राफ़र', 'location_id' => 'लोकेशन']);
        $old = $row ? array_column(db()->all('SELECT image FROM {p}gallery_photos WHERE gallery_id = ?', [$row['id']]), 'image') : [];
        $this->photos = [];
        $raw = $request->post()['photos'] ?? [];
        foreach (is_array($raw) ? array_values($raw) : [] as $i => $p) {
            if (!is_array($p) || empty($p['image'])) {
                continue;
            }
            $img = (string) $p['image'];
            if (!in_array($img, $old, true) && !MediaService::validImagePath($img)) {
                throw new ValidationException(['photos' => 'फ़ोटो ' . ($i + 1) . ': इमेज मीडिया लाइब्रेरी से चुनें।'], $request->post());
            }
            $t = static fn($k, $n) => ($v = trim(strip_tags((string) ($p[$k] ?? '')))) === '' ? null : mb_substr($v, 0, $n);
            $this->photos[] = ['image' => $img, 'caption' => $t('caption', 500), 'photographer' => $t('photographer', 150), 'location' => $t('location', 150),
                'credit' => $t('credit', 150), 'copyright' => $t('copyright', 150)];
        }
        if (count($this->photos) > self::MAX_PHOTOS) {
            throw new ValidationException(['photos' => 'एक गैलरी में ज़्यादा से ज़्यादा ' . self::MAX_PHOTOS . ' फ़ोटो।'], $request->post());
        }
        if ($data['status'] === 'published' && !$this->photos) {
            throw new ValidationException(['photos' => 'प्रकाशित करने के लिए कम से कम एक फ़ोटो जोड़ें।'], $request->post());
        }
        if (!$data['cover'] && $this->photos) {
            $data['cover'] = $this->photos[0]['image'];
        }
        $data['photographer'] = $data['photographer'] ? strip_tags((string) $data['photographer']) : null;
        $data['location_id'] = $data['location_id'] ? (int) $data['location_id'] : null;
        return $data;
    }

    protected function afterSave(int $id, Request $request, ?array $row): void
    {
        db()->query('DELETE FROM {p}gallery_photos WHERE gallery_id = ?', [$id]);
        foreach ($this->photos as $i => $p) {
            GalleryPhoto::create($p + ['gallery_id' => $id, 'sort_order' => $i + 1]);
        }
    }
}
