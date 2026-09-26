<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Tts\TtsProvider;

/**
 * Text-to-Speech का ढाँचा: अभी कोई प्रदाता नहीं जुड़ा।
 * आगे जोड़ना: TtsProvider लागू करने वाली क्लास बनाएँ और config/tts.php में 'provider' => MyProvider::class लिखें।
 * ऑडियो आइटम पर transcript (पाठ) और tts_status (none/pending/done/failed) पहले से हैं।
 */
final class TtsService
{
    public static function provider(): ?TtsProvider
    {
        $class = (string) config('tts.provider', '');
        return $class !== '' && class_exists($class) && is_subclass_of($class, TtsProvider::class) ? new $class((array) config('tts.options', [])) : null;
    }

    public static function available(): bool
    {
        return self::provider() !== null;
    }
}
