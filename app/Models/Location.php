<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** लोकेशन: देश → राज्य → मंडल → ज़िला → तहसील → ब्लॉक → शहर → मोहल्ला (एक ही टेबल) */
final class Location extends Model
{
    protected static string $table = 'locations';
    protected static array $fillable = ['parent_id', 'type', 'name', 'name_en', 'slug', 'path', 'code', 'is_popular', 'show_in_menu', 'status',
        'sort_order', 'meta_title', 'meta_description'];

    public const TYPES = [
        'country' => 'देश', 'state' => 'राज्य / केंद्र शासित प्रदेश', 'division' => 'मंडल', 'district' => 'ज़िला',
        'tehsil' => 'तहसील', 'block' => 'ब्लॉक', 'city' => 'शहर / क़स्बा', 'locality' => 'मोहल्ला / गाँव',
    ];
    /** छोटे नाम (बैज के लिए) */
    public const SHORT = ['country' => 'देश', 'state' => 'राज्य', 'division' => 'मंडल', 'district' => 'ज़िला', 'tehsil' => 'तहसील', 'block' => 'ब्लॉक', 'city' => 'शहर', 'locality' => 'मोहल्ला'];
    /** URL में न आने वाले स्तर */
    public const NO_URL = ['country', 'division'];
    public const STATUSES = ['active' => 'चालू', 'inactive' => 'बंद'];

    /** किस स्तर के नीचे कौन-से स्तर आ सकते हैं (ऊपर से नीचे का क्रम) */
    public static function childTypes(?string $parentType): array
    {
        $order = array_keys(self::TYPES);
        if ($parentType === null) {
            return ['country'];
        }
        $i = array_search($parentType, $order, true);
        return $i === false ? [] : array_slice($order, $i + 1);
    }

    /** ऊपर तक के सभी पूर्वज (देश से शुरू) */
    public static function ancestors(array $loc): array
    {
        $out = [];
        $seen = [];
        $pid = $loc['parent_id'];
        while ($pid !== null && !isset($seen[(int) $pid])) {
            $seen[(int) $pid] = true;
            $p = static::find((int) $pid);
            if (!$p) {
                break;
            }
            array_unshift($out, $p);
            $pid = $p['parent_id'];
        }
        return $out;
    }
}
