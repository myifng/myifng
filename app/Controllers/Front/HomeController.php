<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\HomeRenderer;
use App\Services\SeoService;

/** होमपेज: होमपेज बिल्डर के सेक्शन से */
final class HomeController extends FrontController
{
    public function index(Request $request): Response
    {
        return $this->view('front/home', [
            'sections' => HomeRenderer::render(),
            'isHome' => true,
            'seo' => [
                'title' => null, 'canonical' => url(), 'description' => setting('site_description') ?: setting('tagline'),
                'jsonld' => SeoService::website(),
            ],
        ]);
    }
}
