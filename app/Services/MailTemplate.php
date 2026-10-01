<?php
declare(strict_types=1);

namespace App\Services;

/**
 * सभी ईमेल का एक जैसा, ब्रांड वाला ढाँचा: ऊपर लोगो, बीच में सामग्री, नीचे फ़ुटर।
 * रंग सेटिंग के मुख्य/दूसरे रंग से। ईमेल क्लाइंट (Gmail, Outlook, फ़ोन) के लिए सिर्फ़ table + inline style।
 * Mailer अपने-आप लपेटता है (जिस HTML में <html> न हो); न्यूज़लेटर का अपना टेम्पलेट रहता है।
 */
final class MailTemplate
{
    private const FONT = "'Mukta','Noto Sans Devanagari','Segoe UI',Arial,sans-serif";

    public static function brand(): string
    {
        $c = (string) setting('primary_color', '#d71920');
        return readable_color(preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#d71920');
    }

    public static function dark(): string
    {
        $c = (string) setting('secondary_color', '#15161a');
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#15161a';
    }

    /** पूरा ईमेल: $o = ['preheader' => इनबॉक्स में दिखने वाली पहली लाइन] */
    public static function wrap(string $content, array $o = []): string
    {
        $site = (string) setting('site_name', 'News');
        $brand = self::brand();
        $dark = self::dark();
        $home = url();
        $pre = trim((string) ($o['preheader'] ?? ''));
        $f = self::FONT;

        $logoPath = (string) setting('logo');
        if ($logoPath !== '' && !preg_match('/\.svg$/i', $logoPath)) { // SVG ज़्यादातर ईमेल ऐप में नहीं दिखता
            $logo = '<img src="' . e(upload_url($logoPath)) . '" alt="' . e($site) . '" height="48" style="display:block;height:48px;max-height:48px;width:auto;max-width:240px;border:0;outline:none;text-decoration:none;margin:0 auto">';
        } else {
            $w = preg_split('/\s+/u', $site, 2);
            $logo = '<span style="display:inline-block;font-family:' . $f . ';font-size:26px;font-weight:800;line-height:1">'
                . '<span style="background:' . $brand . ';color:#ffffff;padding:7px 10px 5px;border-radius:5px 0 0 5px;display:inline-block' . (empty($w[1]) ? ';border-radius:5px' : '') . '">' . e($w[0]) . '</span>'
                . (!empty($w[1]) ? '<span style="border:2px solid ' . $brand . ';border-left:0;color:' . $brand . ';padding:5px 10px 3px;border-radius:0 5px 5px 0;display:inline-block">' . e($w[1]) . '</span>' : '') . '</span>';
        }
        $tagline = trim((string) setting('tagline', ''));

        // फ़ुटर: पता, संपर्क, सोशल
        $contact = [];
        if ($v = trim((string) setting('contact_email'))) {
            $contact[] = '<a href="mailto:' . e($v) . '" style="color:#ffffff;text-decoration:none">' . e($v) . '</a>';
        }
        if ($v = trim((string) setting('contact_phone'))) {
            $contact[] = '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $v)) . '" style="color:#ffffff;text-decoration:none">' . e($v) . '</a>';
        }
        $social = [];
        foreach (['facebook' => 'Facebook', 'twitter' => 'X', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'telegram' => 'Telegram', 'whatsapp_channel' => 'WhatsApp', 'linkedin' => 'LinkedIn'] as $k => $label) {
            if (($u = trim((string) setting($k))) !== '' && preg_match('~^https?://~i', $u)) {
                $social[] = '<a href="' . e($u) . '" style="display:inline-block;margin:3px;padding:5px 11px;border:1px solid rgba(255,255,255,.28);border-radius:99px;color:#ffffff;font-size:12px;text-decoration:none">' . e($label) . '</a>';
            }
        }
        $address = trim((string) setting('address', ''));

        return '<!doctype html><html lang="' . e((string) setting('language', 'hi')) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . e($site) . '</title>'
            . '<style>@media (max-width:620px){.np-pad{padding-left:20px!important;padding-right:20px!important}.np-wrap{padding:12px 6px!important}.np-h1{font-size:21px!important}}a{color:' . $brand . '}</style></head>'
            . '<body style="margin:0;padding:0;background:#eef0f3;-webkit-text-size-adjust:100%;">'
            . ($pre !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px">' . e(mb_substr($pre, 0, 140)) . str_repeat('&#8204;&nbsp;', 30) . '</div>' : '')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f3"><tr><td align="center" class="np-wrap" style="padding:28px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(20,20,30,.08)">'
            // ऊपर ब्रांड पट्टी + लोगो
            . '<tr><td style="height:5px;line-height:5px;font-size:0;background:' . $brand . '">&nbsp;</td></tr>'
            . '<tr><td align="center" class="np-pad" style="padding:28px 36px 18px;border-bottom:1px solid #eeeeef"><a href="' . e($home) . '" style="text-decoration:none;display:inline-block">' . $logo . '</a>'
            . ($tagline !== '' ? '<div style="font-family:' . $f . ';font-size:13px;color:#7a7680;margin-top:10px">' . e($tagline) . '</div>' : '') . '</td></tr>'
            // सामग्री
            . '<tr><td class="np-pad" style="padding:30px 40px 34px;font-family:' . $f . ';font-size:16px;line-height:1.75;color:#24232a">' . $content . '</td></tr>'
            // मदद की पट्टी
            . ($contact ? '<tr><td class="np-pad" style="padding:14px 40px;background:#f7f7f9;border-top:1px solid #eeeeef;font-family:' . $f . ';font-size:13px;color:#6b6770">कोई सवाल? हमें लिखें: '
                . str_replace('color:#ffffff', 'color:' . $brand, implode(' &nbsp;·&nbsp; ', $contact)) . '</td></tr>' : '')
            // फ़ुटर
            . '<tr><td align="center" class="np-pad" style="padding:26px 36px 28px;background:' . $dark . ';font-family:' . $f . ';color:#cfcbd4;font-size:13px;line-height:1.7">'
            . '<div style="font-size:17px;font-weight:800;color:#ffffff;margin-bottom:2px">' . e($site) . '</div>'
            . ($address !== '' ? '<div>' . nl2br(e($address)) . '</div>' : '')
            . ($contact ? '<div style="margin-top:4px">' . implode(' &nbsp;|&nbsp; ', $contact) . '</div>' : '')
            . ($social ? '<div style="margin-top:12px">' . implode('', $social) . '</div>' : '')
            . '<div style="margin-top:14px"><a href="' . e($home) . '" style="display:inline-block;background:' . $brand . ';color:#ffffff;text-decoration:none;font-weight:700;padding:8px 18px;border-radius:8px">वेबसाइट पर जाएँ</a></div>'
            . '<div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.14);font-size:12px;color:#9d98a3">© ' . date('Y') . ' ' . e($site) . '. सर्वाधिकार सुरक्षित।<br>यह अपने-आप भेजा गया ईमेल है, कृपया इसका जवाब न दें।</div>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    // ---------- सामग्री के हिस्से (ईमेल बनाते समय) ----------

    public static function title(string $text, string $emoji = ''): string
    {
        return '<h1 class="np-h1" style="margin:0 0 16px;font-family:' . self::FONT . ';font-size:24px;line-height:1.35;font-weight:800;color:#18171c">' . ($emoji !== '' ? $emoji . ' ' : '') . e($text) . '</h1>';
    }

    public static function hello(string $name): string
    {
        return '<p style="margin:0 0 12px">नमस्ते <b>' . e($name) . '</b>,</p>';
    }

    /** सादा पैराग्राफ़ ($html भरोसेमंद होना चाहिए; उपयोगकर्ता का टेक्स्ट e() करके दें) */
    public static function p(string $html, string $style = ''): string
    {
        return '<p style="margin:0 0 14px;' . $style . '">' . $html . '</p>';
    }

    public static function button(string $url, string $label): string
    {
        $b = self::brand();
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0"><tr><td align="center" bgcolor="' . $b . '" style="border-radius:10px;background:' . $b . '">'
            . '<a href="' . e($url) . '" target="_blank" style="display:inline-block;padding:14px 28px;font-family:' . self::FONT . ';font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:10px">' . e($label) . ' &rarr;</a></td></tr></table>'
            . '<p style="margin:0 0 14px;font-size:12px;color:#8a8690;word-break:break-all">बटन न चले तो यह लिंक ब्राउज़र में खोलें:<br><a href="' . e($url) . '" style="color:' . $b . '">' . e($url) . '</a></p>';
    }

    /** OTP / कोड का बड़ा डिब्बा */
    public static function code(string $code, string $caption = ''): string
    {
        $b = self::brand();
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 20px"><tr><td align="center" style="background:#f6f5f8;border:1px dashed ' . $b . ';border-radius:12px;padding:18px 12px">'
            . ($caption !== '' ? '<div style="font-size:13px;color:#6b6770;margin-bottom:6px">' . e($caption) . '</div>' : '')
            . '<div style="font-family:Consolas,Menlo,monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#18171c;padding-left:10px">' . e($code) . '</div></td></tr></table>';
    }

    /** [[लेबल, मान], ...] की साफ़ तालिका (मान e() करके) */
    public static function info(array $rows): string
    {
        $h = '';
        foreach ($rows as [$k, $v]) {
            $h .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #eeeeef;color:#6b6770;font-size:14px;width:40%">' . e((string) $k) . '</td>'
                . '<td style="padding:9px 12px;border-bottom:1px solid #eeeeef;font-size:14px;font-weight:700;color:#18171c">' . e((string) $v) . '</td></tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:14px 0 18px;border:1px solid #eeeeef;border-radius:10px;border-collapse:separate;overflow:hidden">' . $h . '</table>';
    }

    /** छोटी सूचना पट्टी: info | warn | ok */
    public static function note(string $html, string $tone = 'info'): string
    {
        [$bg, $bd] = ['warn' => ['#fff6e6', '#e8a33d'], 'ok' => ['#ecf8f1', '#2e9e5b']][$tone] ?? ['#f3f4f8', '#9aa0ad'];
        return '<div style="margin:16px 0;padding:12px 14px;background:' . $bg . ';border-left:4px solid ' . $bd . ';border-radius:8px;font-size:14px;line-height:1.6;color:#3a3940">' . $html . '</div>';
    }
}
