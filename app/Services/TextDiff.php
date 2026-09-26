<?php
declare(strict_types=1);

namespace App\Services;

/**
 * दो वर्ज़न की तुलना: HTML → पैराग्राफ़, पैराग्राफ़ स्तर पर LCS; बदले पैराग्राफ़ में शब्द स्तर पर <del>/<ins>।
 * नतीजा सुरक्षित HTML (हर टेक्स्ट escape) है।
 */
final class TextDiff
{
    private const MAX_WORDS = 3000;

    /** HTML को पढ़ने लायक पैराग्राफ़ों में */
    public static function paragraphs(?string $html): array
    {
        $html = preg_replace('~<(br|/p|/h[1-6]|/li|/blockquote|/figcaption|/tr|/div)\b[^>]*>~i', "\n", (string) $html);
        $html = preg_replace('~<img\b[^>]*alt="([^"]*)"[^>]*>~i', "\n[इमेज: $1]\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        return array_values(array_filter(array_map(static fn($l) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l)), explode("\n", $text)), static fn($l) => $l !== ''));
    }

    /** @return array<array{0: string, 1: string}> [op (=,-,+), text] */
    public static function ops(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        // साझा शुरुआत/अंत हटाकर LCS छोटा रखें
        $pre = 0;
        while ($pre < $n && $pre < $m && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $n - $pre && $suf < $m - $pre && $a[$n - 1 - $suf] === $b[$m - 1 - $suf]) {
            $suf++;
        }
        $x = array_slice($a, $pre, $n - $pre - $suf);
        $y = array_slice($b, $pre, $m - $pre - $suf);
        $out = array_map(static fn($t) => ['=', $t], array_slice($a, 0, $pre));
        if (count($x) * count($y) > 250_000) {
            // बहुत बड़ा: सीधे पूरा हटाया/जोड़ा दिखाएँ
            foreach ($x as $t) {
                $out[] = ['-', $t];
            }
            foreach ($y as $t) {
                $out[] = ['+', $t];
            }
        } else {
            $L = [];
            for ($i = count($x); $i >= 0; $i--) {
                for ($j = count($y); $j >= 0; $j--) {
                    $L[$i][$j] = ($i === count($x) || $j === count($y)) ? 0 : ($x[$i] === $y[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]));
                }
            }
            $i = $j = 0;
            while ($i < count($x) && $j < count($y)) {
                if ($x[$i] === $y[$j]) {
                    $out[] = ['=', $x[$i++]];
                    $j++;
                } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                    $out[] = ['-', $x[$i++]];
                } else {
                    $out[] = ['+', $y[$j++]];
                }
            }
            while ($i < count($x)) {
                $out[] = ['-', $x[$i++]];
            }
            while ($j < count($y)) {
                $out[] = ['+', $y[$j++]];
            }
        }
        foreach (array_slice($a, $n - $suf) as $t) {
            $out[] = ['=', $t];
        }
        return $out;
    }

    /** पैराग्राफ़ों का diff HTML; हटाया-जोड़ा जोड़ा हो तो शब्द स्तर पर */
    public static function html(?string $old, ?string $new): string
    {
        $ops = self::ops(self::paragraphs($old), self::paragraphs($new));
        $html = '';
        $k = 0;
        $n = count($ops);
        while ($k < $n) {
            if ($ops[$k][0] === '=') {
                $html .= '<p class="diff-same">' . e($ops[$k++][1]) . '</p>';
                continue;
            }
            // लगातार हटाए और जोड़े पैराग्राफ़ों का समूह; क्रम से जोड़ी बनाकर शब्द-स्तर diff
            $del = $ins = [];
            while ($k < $n && $ops[$k][0] !== '=') {
                $ops[$k][0] === '-' ? $del[] = $ops[$k][1] : $ins[] = $ops[$k][1];
                $k++;
            }
            for ($i = 0; $i < max(count($del), count($ins)); $i++) {
                if (isset($del[$i], $ins[$i])) {
                    $html .= '<p class="diff-chg">' . self::words($del[$i], $ins[$i]) . '</p>';
                } elseif (isset($del[$i])) {
                    $html .= '<p class="diff-del">' . e($del[$i]) . '</p>';
                } else {
                    $html .= '<p class="diff-ins">' . e($ins[$i]) . '</p>';
                }
            }
        }
        return $html;
    }

    /** दो वाक्यों का शब्द-स्तर diff */
    public static function words(string $a, string $b): string
    {
        $x = preg_split('/(\s+)/u', $a, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $y = preg_split('/(\s+)/u', $b, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (count($x) > self::MAX_WORDS || count($y) > self::MAX_WORDS) {
            return '<del>' . e($a) . '</del> <ins>' . e($b) . '</ins>';
        }
        $html = '';
        foreach (self::ops($x, $y) as [$op, $t]) {
            $html .= match ($op) { '=' => e($t), '-' => '<del>' . e($t) . '</del>', '+' => '<ins>' . e($t) . '</ins>' };
        }
        return $html;
    }

    /** छोटे खानों (शीर्षक, श्रेणी…) की तुलना: [खाना => [पुराना, नया]] सिर्फ़ बदले हुए */
    public static function fields(array $old, array $new, array $labels): array
    {
        $out = [];
        foreach ($labels as $k => $label) {
            $o = $old[$k] ?? null;
            $n = $new[$k] ?? null;
            if (is_array($o) || is_array($n)) {
                $o = implode(', ', (array) $o);
                $n = implode(', ', (array) $n);
            }
            if ((string) $o !== (string) $n) {
                $out[$label] = [(string) $o, (string) $n];
            }
        }
        return $out;
    }
}
