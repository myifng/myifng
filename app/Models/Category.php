<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** श्रेणी (2 स्तर: मुख्य → उप-श्रेणी) */
final class Category extends Model
{
    protected static string $table = 'categories';
    protected static array $fillable = ['parent_id', 'name', 'slug', 'description', 'icon', 'image', 'color', 'meta_title', 'meta_description',
        'show_in_menu', 'show_on_home', 'sort_order', 'status', 'language_id'];

    public const STATUSES = ['active' => 'चालू', 'inactive' => 'बंद'];

    /** मुख्य श्रेणियाँ और उनके नीचे उप-श्रेणियाँ (एडमिन सूची/ड्रॉपडाउन) */
    public static function tree(bool $activeOnly = false): array
    {
        $rows = static::db()->all('SELECT * FROM {p}categories' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY sort_order, name');
        $out = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $out[(int) $r['id']] = $r + ['children' => []];
            }
        }
        foreach ($rows as $r) {
            if ($r['parent_id'] !== null && isset($out[(int) $r['parent_id']])) {
                $out[(int) $r['parent_id']]['children'][] = $r;
            }
        }
        return array_values($out);
    }

    /** ड्रॉपडाउन के लिए: [id => 'खेल', id => '— क्रिकेट'] */
    public static function options(): array
    {
        $o = [];
        foreach (static::tree() as $c) {
            $o[(int) $c['id']] = $c['name'];
            foreach ($c['children'] as $ch) {
                $o[(int) $ch['id']] = '— ' . $ch['name'];
            }
        }
        return $o;
    }
}
