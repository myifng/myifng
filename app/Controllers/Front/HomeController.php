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
            'isHome' => true, 'autoBanner' => !HomeRenderer::hasBreakingBanner(),
            'seo' => [
                'title' => null, 'canonical' => url(), 'description' => setting('seo_home_description') ?: (setting('site_description') ?: setting('tagline')),
                'full_title' => setting('seo_home_title') ?: null,
                'jsonld' => SeoService::website(),
            ],
        ]);
    }
}
