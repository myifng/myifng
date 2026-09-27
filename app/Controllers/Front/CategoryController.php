<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** श्रेणी पेज: /category/{slug} (उप-श्रेणी की ख़बरें मुख्य में भी) */
final class CategoryController extends FrontController
{
    public function show(Request $request, string $slug): Response
    {
        $cat = Category::firstWhere('slug', $slug);
        if (!$cat || $cat['status'] !== 'active') {
            throw new HttpException(404);
        }
        $parent = $cat['parent_id'] ? Category::find((int) $cat['parent_id']) : null;
        \App\Services\AdService::setContext([(int) $cat['id'], (int) ($parent['id'] ?? 0)]);
        $siblingsOf = $parent ? (int) $parent['id'] : (int) $cat['id'];
        $subs = db()->all("SELECT name, slug FROM {p}categories WHERE parent_id = ? AND status = 'active' ORDER BY sort_order", [$siblingsOf]);
        [$w, $p] = NewsQuery::categoryWhere((int) $cat['id']);
        $items = NewsQuery::page($w, $p, $this->page($request->int('page', 1)));
        $crumbs = [['होम', url()]];
        if ($parent) {
            $crumbs[] = [$parent['name'], route('category', ['slug' => $parent['slug']])];
        }
        $crumbs[] = [$cat['name'], null];
        $chips = [];
        if ($subs) {
            $top = $parent ?: $cat;
            $chips[] = ['सभी', route('category', ['slug' => $top['slug']]), !$parent];
            foreach ($subs as $s) {
                $chips[] = [$s['name'], route('category', ['slug' => $s['slug']]), $s['slug'] === $cat['slug']];
            }
        }
        $canonical = route('category', ['slug' => $cat['slug']]) . ($items->page > 1 ? '?page=' . $items->page : '');
        return $this->listing([
            'heading' => $cat['name'], 'desc' => $cat['description'], 'crumbs' => $crumbs, 'chips' => $chips, 'items' => $items,
            'banner' => $cat['image'], 'color' => $cat['color'], 'icon' => $cat['icon'],
            'seo' => [
                'title' => ($cat['meta_title'] ?: $cat['name'] . ' समाचार') . ($items->page > 1 ? ' - पेज ' . $items->page : ''),
                'description' => $cat['meta_description'] ?: ($cat['description'] ?: $cat['name'] . ' की ताज़ा ख़बरें, ' . setting('site_name') . ' पर।'),
                'canonical' => $canonical, 'image' => $cat['image'], 'jsonld' => SeoService::breadcrumbs($crumbs),
            ],
        ]);
    }
}
