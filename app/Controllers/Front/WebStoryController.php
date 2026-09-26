<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmbedService;
use App\Services\MultimediaService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** /web-stories, /web-stories/{slug} (फ़ुलस्क्रीन प्लेयर), /web-stories/{slug}/amp (Google Web Stories) */
final class WebStoryController extends FrontController
{
    public function index(Request $request): Response
    {
        $items = MultimediaService::page('story', '1=1', [], $this->page($request->int('page', 1)), 20);
        return $this->view('front/mm-listing', [
            'kind' => 'story', 'heading' => 'वेब स्टोरी', 'icon' => 'fa-mobile-screen', 'items' => $items, 'chips' => [], 'tall' => true,
            'desc' => 'कुछ ही स्लाइड में पूरी कहानी: टैप करें और देखें।', 'crumbs' => [['होम', url()], ['वेब स्टोरी', null]],
            'seo' => ['title' => 'वेब स्टोरी' . ($items->page > 1 ? ' - पेज ' . $items->page : ''), 'canonical' => route('stories') . ($items->page > 1 ? '?page=' . $items->page : ''),
                'description' => setting('site_name') . ' की वेब स्टोरी: छोटी, तस्वीरों वाली कहानियाँ।'],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        [$s, $slides] = $this->load($slug);
        NewsQuery::hit('web_stories', (int) $s['id']);
        $url = MultimediaService::url('story', $s);
        $next = MultimediaService::list('story', 'x.id <> ?', [$s['id']], 6);
        return $this->view('front/web-story', ['s' => $s, 'slides' => $slides, 'url' => $url, 'next' => $next, 'poster' => $this->poster($s),
            'jsonld' => $this->schema($s, $url)]);
    }

    public function amp(Request $request, string $slug): Response
    {
        [$s, $slides] = $this->load($slug);
        return $this->view('front/web-story-amp', ['s' => $s, 'slides' => $slides, 'url' => MultimediaService::url('story', $s), 'poster' => $this->poster($s),
            'logo' => setting('logo') ? upload_url((string) setting('logo')) : null, 'jsonld' => $this->schema($s, MultimediaService::url('story', $s))]);
    }

    private function load(string $slug): array
    {
        $s = MultimediaService::find('story', $slug) ?? throw new HttpException(404);
        $slides = db()->all('SELECT * FROM {p}web_story_slides WHERE story_id = ? ORDER BY sort_order, id', [$s['id']]);
        if (!$slides) {
            throw new HttpException(404);
        }
        foreach ($slides as &$sl) {
            $sl['href'] = EmbedService::href($sl['cta_url']);
        }
        unset($sl);
        return [$s, $slides];
    }

    private function poster(array $s): string
    {
        return $s['cover'] ? media_url($s['cover'], 'large') : (setting('logo') ? upload_url((string) setting('logo')) : '');
    }

    private function schema(array $s, string $url): string
    {
        return SeoService::json(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => mb_substr((string) $s['title'], 0, 110), 'url' => $url,
            'mainEntityOfPage' => $url, 'image' => array_values(array_filter([$this->poster($s)])), 'datePublished' => date('c', strtotime($s['published_at'])),
            'dateModified' => date('c', strtotime($s['updated_at'])), 'description' => (string) ($s['description'] ?: $s['title']),
            'publisher' => ['@type' => 'NewsMediaOrganization', 'name' => (string) setting('site_name'), 'logo' => setting('logo') ? ['@type' => 'ImageObject', 'url' => upload_url((string) setting('logo'))] : null]]);
    }
}
