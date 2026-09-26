<?php
declare(strict_types=1);

namespace App\Services\Tts;

/** Text-to-Speech प्रदाता (Google, Azure, AWS Polly, अपना सर्वर…) के लिए अनुबंध */
interface TtsProvider
{
    public function __construct(array $options);

    /**
     * पाठ → ऑडियो फ़ाइल (MP3) का अस्थायी पाथ; असफल हो तो null
     * @param string $lang जैसे 'hi-IN'
     */
    public function synthesize(string $text, string $lang = 'hi-IN'): ?string;

    /** प्रशासन में दिखने वाला नाम */
    public function name(): string;
}
