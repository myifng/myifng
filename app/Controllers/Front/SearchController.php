<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\NewsQuery;

/** खोज (/search?q=) और ताज़ा ख़बरें (/latest) */
final class SearchController extends FrontController
{
    public function search(Request $request): Response
    {
        \App\Services\AnalyticsService::context(['type' => 'search']);
        $q = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags($request->str('q'))), 0, 100));
        $cat = max(0, $request->int('category'));
        $items = null;
        $msg = 'ऊपर कुछ लिखकर खोजें।';
        if (mb_strlen($q) >= 2) {
            [$w, $p] = NewsQuery::searchWhere($q, $cat);
            $items = NewsQuery::page($w, $p, $this->page($request->int('page', 1)), 20, 50);
            $msg = '“' . $q . '” से जुड़ी कोई ख़बर नहीं मिली। दूसरे शब्द आज़माएँ।';
        } elseif ($q !== '') {
            $msg = 'कम से कम 2 अक्षर लिखें।';
        }
        return $this->listing([
            'heading' => $q !== '' ? 'खोज: “' . $q . '”' : 'ख़बरें खोजें', 'crumbs' => [['होम', url()], ['खोज', null]],
            'items' => $items, 'empty' => $msg, 'search' => ['q' => $q, 'category' => $cat, 'categories' => Category::options()],
            'desc' => $items ? num($items->total) . ' नतीजे' : null,
            'seo' => ['title' => $q !== '' ? 'खोज: ' . $q : 'खोज', 'robots' => 'noindex,follow', 'canonical' => route('search')],
        ]);
    }

    public function latest(Request $request): Response
    {
        $items = NewsQuery::page('1=1', [], $this->page($request->int('page', 1)));
        return $this->listing([
            'heading' => 'ताज़ा ख़बरें', 'crumbs' => [['होम', url()], ['ताज़ा ख़बरें', null]], 'items' => $items,
            'seo' => ['title' => 'ताज़ा ख़बरें' . ($items->page > 1 ? ' - पेज ' . $items->page : ''), 'description' => setting('site_name') . ' की सबसे नई ख़बरें।',
                'canonical' => route('latest') . ($items->page > 1 ? '?page=' . $items->page : '')],
        ]);
    }
}
