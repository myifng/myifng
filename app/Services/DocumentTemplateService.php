<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Reporter;
use App\Models\ReporterDocument;

/**
 * रिपोर्टर के पत्र/प्रमाणपत्र (अधिकार पत्र, नियुक्ति पत्र, प्रेस प्रमाणपत्र, अनुभव प्रमाणपत्र) के टेम्पलेट।
 * एडमिन सेटिंग → "पत्र और प्रमाणपत्र" में शीर्षक और सामग्री बदलता है; {name} जैसे variable अपने-आप भरते हैं।
 *
 * टेम्पलेट की भाषा (सादा टेक्स्ट, HTML नहीं):
 *   {variable}              → मान (नाम, ID, पद, तारीख़ जैसे मान गाढ़े; श्री/पुत्र/संस्थान सादे)
 *   **टेक्स्ट**             → गाढ़ा
 *   [[bureau| और ब्यूरो {bureau}]] → यह हिस्सा सिर्फ़ तब, जब bureau ख़ाली न हो
 *   ख़ाली लाइन              → नया पैराग्राफ़
 *   "- " या "1. " से शुरू लाइनें → सूची
 */
final class DocumentTemplateService
{
    public const TYPES = ['authorization', 'appointment', 'press_certificate', 'experience'];

    /** variable => [लेबल, उदाहरण] */
    public const VARS = [
        'name' => ['रिपोर्टर का नाम', 'अमित वर्मा'],
        'honorific' => ['श्री / सुश्री', 'श्री'],
        'relation' => ['पुत्र / पुत्री', 'पुत्र'],
        'guardian' => ['पिता/पति का नाम', 'रमेश वर्मा'],
        'reporter_id' => ['रिपोर्टर ID', 'RPT-2026-0001'],
        'designation' => ['पद', 'ज़िला संवाददाता'],
        'reporter_type' => ['रिपोर्टर का प्रकार', 'पूर्णकालिक'],
        'area' => ['कार्यक्षेत्र (क्षेत्र, ज़िला, राज्य)', 'गोरखपुर, उत्तर प्रदेश'],
        'district' => ['ज़िला', 'गोरखपुर'],
        'state' => ['राज्य', 'उत्तर प्रदेश'],
        'bureau' => ['ब्यूरो', 'पूर्वांचल ब्यूरो'],
        'beat' => ['बीट (श्रेणी)', 'राजनीति'],
        'mobile' => ['मोबाइल', '98765 43210'],
        'email' => ['ईमेल', 'amit@example.com'],
        'address' => ['पता', 'गोरखपुर'],
        'joining_date' => ['जॉइनिंग की तारीख़', '1 जनवरी 2026'],
        'valid_until' => ['वैधता (तारीख़ तक)', '31 दिसंबर 2026'],
        'end_date' => ['कार्य की अंतिम तारीख़ (अनुभव)', '30 सितंबर 2026'],
        'issue_date' => ['जारी करने की तारीख़', '1 अक्टूबर 2026'],
        'doc_no' => ['पत्र संख्या', 'AUTH-2026-00008'],
        'site_name' => ['संस्थान का नाम', 'समाचार भारती'],
        'verify_url' => ['सत्यापन का पता', 'example.com/verify-reporter'],
        'signatory' => ['हस्ताक्षरकर्ता', 'राजेश कुमार'],
        'signatory_designation' => ['हस्ताक्षरकर्ता का पद', 'प्रधान संपादक'],
    ];

    /** डिफ़ॉल्ट (एडमिन ने कुछ न बदला हो, या ख़ाली छोड़ा हो) */
    public static function defaults(): array
    {
        return [
            'authorization' => ['अधिकार पत्र',
                "प्रमाणित किया जाता है कि {honorific} {name}, {relation} {guardian}, रिपोर्टर ID {reporter_id}, **{site_name}** में {designation} के रूप में {area} क्षेत्र में समाचार संकलन, फ़ोटो/वीडियो कवरेज और संबंधित पत्रकारिता कार्यों के लिए अधिकृत हैं।\n\n"
                . "संबंधित विभागों और अधिकारियों से अनुरोध है कि इन्हें समाचार संकलन में आवश्यक सहयोग प्रदान करें। यह अधिकार पत्र {valid_until} तक मान्य है। इस पत्र का उपयोग किसी भी प्रकार की वसूली, दबाव या निजी लाभ के लिए वर्जित है।"],
            'appointment' => ['नियुक्ति पत्र',
                "{honorific} {name},\n\n"
                . "आपके आवेदन और साक्षात्कार/सत्यापन के आधार पर आपको दिनांक {joining_date} से **{site_name}** में {designation} ({reporter_type}) के पद पर नियुक्त किया जाता है। आपका कार्यक्षेत्र {area}[[bureau| तथा ब्यूरो {bureau}]][[beat|; बीट: {beat}]] रहेगा।\n\n"
                . "शर्तें:\n1. आप संस्थान की संपादकीय नीति, आचार संहिता और रिपोर्टर नीति का पालन करेंगे।\n2. हर ख़बर तथ्यों की जाँच के बाद ही भेजेंगे; किसी भी ख़बर के प्रकाशन का अंतिम निर्णय संपादक का होगा।\n"
                . "3. संस्थान के नाम, ID कार्ड या पत्र का दुरुपयोग करने पर नियुक्ति तुरंत समाप्त की जा सकती है।\n4. यह नियुक्ति {valid_until} तक मान्य है और नवीनीकरण संस्थान के निर्णय पर होगा।\n\n"
                . "हम आपके उज्ज्वल भविष्य की कामना करते हैं।"],
            'press_certificate' => ['प्रेस प्रमाणपत्र',
                "प्रमाणित किया जाता है कि {honorific} {name}, रिपोर्टर ID {reporter_id}, **{site_name}** के मान्य प्रतिनिधि ({designation}) हैं और {area} क्षेत्र में पत्रकारिता कार्य करते हैं।\n\n"
                . "यह प्रमाणपत्र {valid_until} तक मान्य है। इसकी सत्यता नीचे दिए QR या {verify_url} पर जाँची जा सकती है।"],
            'experience' => ['अनुभव प्रमाणपत्र',
                "प्रमाणित किया जाता है कि {honorific} {name}, {relation} {guardian}, ने **{site_name}** में दिनांक {joining_date} से {end_date} तक {designation} के रूप में {area} क्षेत्र में कार्य किया।\n\n"
                . "इस अवधि में इनका कार्य और आचरण संतोषजनक रहा। हम इनके भविष्य के लिए शुभकामनाएँ देते हैं।"],
        ];
    }

    /** [शीर्षक, सामग्री]: सेटिंग से, ख़ाली हो तो डिफ़ॉल्ट */
    public static function template(string $type): array
    {
        $d = self::defaults()[$type] ?? [ReporterDocument::TYPES[$type][0] ?? 'पत्र', ''];
        $t = trim((string) setting('doc_tpl_' . $type . '_title', ''));
        $b = trim((string) setting('doc_tpl_' . $type . '_body', ''));
        return [$t !== '' ? $t : $d[0], $b !== '' ? $b : $d[1]];
    }

    /** रिपोर्टर और दस्तावेज़ से सभी variable के मान */
    public static function values(array $ctx): array
    {
        $r = $ctx['r'];
        $doc = $ctx['doc'];
        $gender = $ctx['gender'] ?? '';
        $area = $ctx['area'] ?? null;
        $district = $ctx['district'] ?? null;
        $state = $ctx['state'] ?? null;
        $endDate = $r['status'] === 'resigned' ? date('Y-m-d', strtotime((string) $r['updated_at'])) : date('Y-m-d');
        $mob = preg_replace('/\D/', '', (string) ($r['mobile'] ?? ''));
        return [
            'name' => (string) $ctx['name'],
            'honorific' => ['male' => 'श्री', 'female' => 'सुश्री'][$gender] ?? 'श्री/सुश्री',
            'relation' => ['male' => 'पुत्र', 'female' => 'पुत्री/पत्नी'][$gender] ?? 'पुत्र/पुत्री/पत्नी',
            'guardian' => (string) ($r['guardian_name'] ?: '—'),
            'reporter_id' => (string) $r['reporter_code'],
            'designation' => (string) $r['designation'],
            'reporter_type' => (string) (Reporter::TYPES[$r['reporter_type']] ?? $r['reporter_type']),
            'area' => implode(', ', array_filter([$area && $area !== $district ? $area : null, $district, $state])) ?: 'संस्थान द्वारा तय क्षेत्र',
            'district' => (string) ($district ?? ''),
            'state' => (string) ($state ?? ''),
            'bureau' => (string) ($ctx['bureau'] ?? ''),
            'beat' => (string) ($ctx['beat'] ?? ''),
            'mobile' => strlen($mob) === 10 ? substr($mob, 0, 5) . ' ' . substr($mob, 5) : (string) ($r['mobile'] ?? ''),
            'email' => (string) ($ctx['email'] ?? ''),
            'address' => (string) ($r['address'] ?? ''),
            'joining_date' => $r['joining_date'] ? hindi_date($r['joining_date']) : '',
            'valid_until' => ($v = $doc['valid_until'] ?: $r['valid_until']) ? hindi_date($v) : '',
            'end_date' => hindi_date($endDate),
            'issue_date' => hindi_date($doc['issued_at']),
            'doc_no' => (string) $doc['doc_no'],
            'site_name' => (string) setting('site_name'),
            'verify_url' => route('verify'),
            'signatory' => (string) setting('signatory_name', ''),
            'signatory_designation' => (string) setting('signatory_designation', 'प्रधान संपादक'),
        ];
    }

    /** टेम्पलेट + मान → ['title' => सादा, 'body' => सुरक्षित HTML] */
    public static function render(string $type, array $values): array
    {
        [$title, $body] = self::template($type);
        return ['title' => self::fill($title, $values, false), 'body' => self::toHtml($body, $values)];
    }

    /** शीर्षक: सादा टेक्स्ट */
    private static function fill(string $text, array $v, bool $html): string
    {
        $text = (string) preg_replace_callback('/\[\[([a-z_]+)\|(.*?)\]\]/u', static fn($m) => trim((string) ($v[$m[1]] ?? '')) !== '' ? $m[2] : '', $text);
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static function ($m) use ($v, $html) {
            if (!array_key_exists($m[1], $v)) {
                return $m[0]; // अनजान variable जैसा लिखा वैसा
            }
            return $html ? "\x01" . $m[1] . "\x02" : (string) $v[$m[1]];
        }, $text);
    }

    /** सामग्री: पहले सब escape, फिर गाढ़ा/सूची/पैराग्राफ़; variable के मान अलग से escape होकर गाढ़े */
    private static function toHtml(string $body, array $v): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $marked = self::fill($body, $v, true);
        $esc = e($marked);
        $esc = (string) preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $esc);
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($esc)) ?: [] as $para) {
            $lines = explode("\n", $para);
            $list = array_filter($lines, static fn($l) => preg_match('/^\s*(-|\d+[.)])\s+/u', $l));
            if ($list && count($list) === count($lines)) {
                $ordered = (bool) preg_match('/^\s*\d/', $lines[0]);
                $items = array_map(static fn($l) => '<li>' . preg_replace('/^\s*(-|\d+[.)])\s+/u', '', $l) . '</li>', $lines);
                $out .= ($ordered ? '<ol>' : '<ul>') . implode('', $items) . ($ordered ? '</ol>' : '</ul>');
            } elseif ($list) {
                // पैराग्राफ़ के बाद सूची (जैसे "शर्तें:" फिर 1. 2. 3.)
                $head = [];
                $items = [];
                foreach ($lines as $l) {
                    if (preg_match('/^\s*(-|\d+[.)])\s+/u', $l)) {
                        $items[] = '<li>' . preg_replace('/^\s*(-|\d+[.)])\s+/u', '', $l) . '</li>';
                    } elseif ($items) {
                        $items[] = '<li>' . $l . '</li>';
                    } else {
                        $head[] = $l;
                    }
                }
                $ordered = (bool) preg_match('/^\s*\d/', (string) current($list));
                $out .= ($head ? '<p>' . implode('<br>', $head) . '</p>' : '') . ($ordered ? '<ol>' : '<ul>') . implode('', $items) . ($ordered ? '</ol>' : '</ul>');
            } else {
                $out .= '<p>' . implode('<br>', $lines) . '</p>';
            }
        }
        // variable के मान (escape करके, गाढ़े)
        $plain = ['honorific', 'relation', 'site_name', 'verify_url', 'signatory_designation'];
        return (string) preg_replace_callback("/\x01([a-z_]+)\x02/", static fn($m) => in_array($m[1], $plain, true) ? e((string) $v[$m[1]]) : '<b>' . e((string) $v[$m[1]]) . '</b>', $out);
    }

    /** जारी दस्तावेज़ की सामग्री: पहले से सुरक्षित हो तो वही, वरना अभी के टेम्पलेट से बनाकर सुरक्षित */
    public static function forDocument(array $doc, array $ctx, bool $refresh = false): array
    {
        if (!$refresh && !empty($doc['content'])) {
            $c = json_decode((string) $doc['content'], true);
            if (is_array($c) && isset($c['title'], $c['body'])) {
                return $c;
            }
        }
        $c = self::render((string) $doc['type'], self::values($ctx));
        db()->update('reporter_documents', ['content' => json_encode($c, JSON_UNESCAPED_UNICODE)], 'id = ?', [$doc['id']]);
        return $c;
    }

    /** सेटिंग पेज पर प्रीव्यू के लिए नमूना मान */
    public static function sample(): array
    {
        $s = array_map(static fn($x) => $x[1], self::VARS);
        $s['site_name'] = (string) setting('site_name', $s['site_name']);
        $s['issue_date'] = hindi_date(date('Y-m-d'));
        return $s;
    }
}
