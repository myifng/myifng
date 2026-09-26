<?php
declare(strict_types=1);

namespace App\Helpers;

/** टेक्स्ट के सहायक: हिंदी शीर्षक → अंग्रेज़ी slug */
final class Str
{
    private const CONS = [
        'क' => 'k', 'ख' => 'kh', 'ग' => 'g', 'घ' => 'gh', 'ङ' => 'n', 'च' => 'ch', 'छ' => 'chh', 'ज' => 'j', 'झ' => 'jh', 'ञ' => 'n',
        'ट' => 't', 'ठ' => 'th', 'ड' => 'd', 'ढ' => 'dh', 'ण' => 'n', 'त' => 't', 'थ' => 'th', 'द' => 'd', 'ध' => 'dh', 'न' => 'n',
        'प' => 'p', 'फ' => 'f', 'ब' => 'b', 'भ' => 'bh', 'म' => 'm', 'य' => 'y', 'र' => 'r', 'ल' => 'l', 'व' => 'v', 'श' => 'sh',
        'ष' => 'sh', 'स' => 's', 'ह' => 'h', 'ळ' => 'l', 'क़' => 'q', 'ख़' => 'kh', 'ग़' => 'g', 'ज़' => 'z', 'ड़' => 'd', 'ढ़' => 'dh',
        'फ़' => 'f', 'य़' => 'y',
    ];
    private const NUKTA = ['क' => 'q', 'ख' => 'kh', 'ग' => 'g', 'ज' => 'z', 'ड' => 'd', 'ढ' => 'dh', 'फ' => 'f', 'य' => 'y'];
    private const VOWELS = ['अ' => 'a', 'आ' => 'a', 'इ' => 'i', 'ई' => 'i', 'उ' => 'u', 'ऊ' => 'u', 'ऋ' => 'ri', 'ए' => 'e', 'ऐ' => 'ai', 'ओ' => 'o', 'औ' => 'au', 'ऑ' => 'o', 'ऍ' => 'e'];
    private const MATRAS = ['ा' => 'a', 'ि' => 'i', 'ी' => 'i', 'ु' => 'u', 'ू' => 'u', 'ृ' => 'ri', 'े' => 'e', 'ै' => 'ai', 'ो' => 'o', 'ौ' => 'au', 'ॉ' => 'o', 'ॅ' => 'e'];
    private const DIGITS = ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9'];

    /**
     * देवनागरी → रोमन: "गोरखपुर में नया फ्लाईओवर" → "gorakhpur men naya flaiovar"
     * शब्द के आख़िर और बीच का अनकहा 'अ' हटाया जाता है (schwa deletion)।
     */
    public static function transliterate(string $text): string
    {
        $out = '';
        $word = [];
        $flush = static function () use (&$word, &$out): void {
            $n = count($word);
            if ($n > 1) {
                $last = $n - 1;
                // आख़िरी अनकहा 'अ' हटाएँ; य/र/व वाले संयुक्त अक्षर में रहने दें (राज्य → rajya)
                if ($word[$last]['inh'] && ($word[$last - 1]['v'] !== '' || !in_array($word[$last]['c'], ['y', 'r', 'v'], true))) {
                    $word[$last]['v'] = '';
                    $word[$last]['inh'] = false;
                }
                // बीच का: स्वर + व्यंजन + [अ] + व्यंजन + स्वर → हटाएँ (दाएँ से बाएँ)
                for ($i = $n - 2; $i >= 1; $i--) {
                    if ($word[$i]['inh'] && $word[$i - 1]['v'] !== '' && $word[$i + 1]['c'] !== '' && $word[$i + 1]['v'] !== '') {
                        $word[$i]['v'] = '';
                        $word[$i]['inh'] = false;
                    }
                }
            }
            foreach ($word as $s) {
                $out .= $s['c'] . $s['v'] . $s['coda'];
            }
            $word = [];
        };

        foreach (mb_str_split($text) as $ch) {
            $k = count($word) - 1;
            if (isset(self::CONS[$ch])) {
                $word[] = ['c' => self::CONS[$ch], 'v' => 'a', 'inh' => true, 'coda' => '', 'raw' => $ch];
            } elseif ($ch === '़' && $k >= 0) {
                if (isset(self::NUKTA[$word[$k]['raw']])) {
                    $word[$k]['c'] = self::NUKTA[$word[$k]['raw']];
                }
            } elseif (isset(self::MATRAS[$ch]) && $k >= 0) {
                $word[$k]['v'] = self::MATRAS[$ch];
                $word[$k]['inh'] = false;
            } elseif ($ch === '्' && $k >= 0) {
                $word[$k]['v'] = '';
                $word[$k]['inh'] = false;
            } elseif (($ch === 'ं' || $ch === 'ँ') && $k >= 0) {
                $word[$k]['coda'] .= 'n';
                $word[$k]['inh'] = false;
            } elseif ($ch === 'ः' && $k >= 0) {
                $word[$k]['coda'] .= 'h';
            } elseif (isset(self::VOWELS[$ch])) {
                $word[] = ['c' => '', 'v' => self::VOWELS[$ch], 'inh' => false, 'coda' => '', 'raw' => $ch];
            } else {
                $flush();
                $out .= self::DIGITS[$ch] ?? ($ch === 'ॐ' ? 'om' : ($ch === '।' ? ' ' : $ch));
            }
        }
        $flush();
        return $out;
    }

    /** SEO-friendly अंग्रेज़ी slug */
    public static function slug(string $text, int $max = 80): string
    {
        $s = strtolower(self::transliterate(trim($text)));
        if (function_exists('iconv')) {
            $s = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        }
        $s = trim((string) preg_replace('/-+/', '-', (string) preg_replace('/[^a-z0-9]+/', '-', $s)), '-');
        if (strlen($s) > $max) {
            $s = substr($s, 0, $max);
            $cut = strrpos($s, '-');
            if ($cut !== false && $cut > 20) {
                $s = substr($s, 0, $cut);
            }
        }
        return $s;
    }

    public static function limit(?string $text, int $len = 100, string $end = '…'): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
        return mb_strlen($t) > $len ? mb_substr($t, 0, $len) . $end : $t;
    }

    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** संवेदनशील जानकारी छुपाएँ: 9876543210 → 98******10 */
    public static function mask(?string $v, int $keepStart = 2, int $keepEnd = 2): string
    {
        $v = (string) $v;
        $len = mb_strlen($v);
        if ($len <= $keepStart + $keepEnd) {
            return str_repeat('*', $len);
        }
        return mb_substr($v, 0, $keepStart) . str_repeat('*', $len - $keepStart - $keepEnd) . mb_substr($v, -$keepEnd);
    }
}
