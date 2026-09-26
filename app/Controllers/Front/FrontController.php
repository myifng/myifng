<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Response;
use App\Services\NewsQuery;

/** वेबसाइट के सूची वाले पेजों का साझा आधार: साइडबार और listing टेम्पलेट */
abstract class FrontController extends Controller
{
    /** साइडबार: ताज़ा + सबसे ज़्यादा पढ़ी (5 मिनट कैश) */
    protected function sidebar(): array
    {
        return cache()->remember('home.sidebar', 300, static fn() => ['latest' => NewsQuery::latest(8), 'popular' => NewsQuery::mostRead(7, 5)]);
    }

    /**
     * श्रेणी/लोकेशन/टॉपिक/टैग/ताज़ा/खोज: एक ही टेम्पलेट
     * $d: heading, desc, crumbs [[नाम, url]], chips [[नाम, url, active]], items (Paginator), seo, banner, color, actions (HTML), empty
     */
    protected function listing(array $d): Response
    {
        return $this->view('front/listing', $d + [
            'desc' => null, 'crumbs' => [], 'chips' => [], 'banner' => null, 'color' => null, 'actions' => '', 'search' => null,
            'empty' => 'अभी यहाँ कोई ख़बर नहीं है।', 'side' => $this->sidebar(),
        ]);
    }

    /** बंद/न मिला: 404 */
    protected function page(int $p): int
    {
        return max(1, min($p, 500));
    }
}
