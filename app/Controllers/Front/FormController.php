<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\FormService;
use App\Services\SeoService;

/** सार्वजनिक फ़ॉर्म, न्यूज़ टिप, शिकायत + ट्रैकिंग, करियर */
final class FormController extends FrontController
{
    private function form(string $slug): array
    {
        return FormService::find($slug) ?? throw new HttpException(404);
    }

    private function formPage(array $form, array $opts = []): Response
    {
        // फ़ॉर्म का HTML पहले: view के अंदर दूसरा render लेआउट की स्थिति बिगाड़ देता है
        return $this->view('front/form', $opts + ['form' => $form, 'formHtml' => FormService::render($form), 'intro' => null, 'side' => $this->sidebar(),
            'seo' => ['title' => $form['title'], 'description' => strip_tags((string) $form['description']) ?: $form['title']]]);
    }

    public function show(Request $request, string $slug): Response
    {
        $form = $this->form($slug);
        if ($form['type'] === 'career') {
            return $this->redirect(route('careers')); // आवेदन हमेशा किसी वैकेंसी से
        }
        return $this->formPage($form);
    }

    public function sendNews(Request $request): Response
    {
        return $this->formPage($this->form('news-tip'), ['icon' => 'fa-camera-retro']);
    }

    public function complaint(Request $request): Response
    {
        return $this->formPage($this->form('complaint'), ['icon' => 'fa-scale-balanced', 'trackLink' => setting('complaint_tracking', '1') === '1']);
    }

    public function submit(Request $request, string $slug): Response
    {
        $form = $this->form($slug);
        $ctx = ['page_url' => back_url()];
        $back = back_url();
        if ($form['type'] === 'career') {
            $job = db()->first("SELECT * FROM {p}jobs WHERE id = ? AND status = 'open' AND (deadline IS NULL OR deadline >= CURDATE())", [$request->int('job_id')]);
            if (!$job) {
                return $this->redirect(route('careers'))->with('danger', 'यह वैकेंसी अब खुली नहीं है।');
            }
            $ctx['job_id'] = (int) $job['id'];
            $back = route('careers.show', ['slug' => $job['slug']]) . '#apply';
        }
        $r = FormService::submit($form, $request, $ctx);
        if (isset($r['errors'])) {
            $input = array_filter($request->post(), static fn($k) => str_starts_with((string) $k, 'f_'), ARRAY_FILTER_USE_KEY);
            return $this->redirect($back . (str_contains($back, '#') ? '' : '#form'))->withErrors($r['errors'])->withInput($input)
                ->with('danger', 'फ़ॉर्म में कुछ जानकारी ठीक करनी है।');
        }
        app('session')->flash('form_done', ['ref' => $r['ref'], 'title' => $form['title'], 'message' => $form['success_message'], 'type' => $form['type']]);
        return $this->redirect(route('form.done', ['slug' => $form['slug']]));
    }

    public function done(Request $request, string $slug): Response
    {
        $d = app('session')->getFlash('form_done');
        if (!$d) {
            return $this->redirect(url());
        }
        return $this->view('front/form-done', ['d' => $d, 'seo' => ['title' => 'धन्यवाद', 'robots' => 'noindex,nofollow']]);
    }

    /** शिकायत की स्थिति: नंबर + रजिस्टर्ड मोबाइल या ईमेल */
    public function track(Request $request): Response
    {
        if (setting('complaint_tracking', '1') !== '1') {
            throw new HttpException(404);
        }
        $found = null;
        $error = null;
        $ref = strtoupper(trim($request->str('ref')));
        if ($request->isPost()) {
            $who = mb_strtolower(trim($request->str('contact')));
            $row = preg_match('/^CMP-\d{4}-\d{5}$/', $ref) ? db()->first("SELECT s.*, f.type FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id WHERE s.ref_no = ? AND f.type = 'complaint'", [$ref]) : null;
            $digits = preg_replace('/\D/', '', $who);
            $match = $row && $who !== '' && ((string) $row['email'] !== '' && mb_strtolower((string) $row['email']) === $who
                || (strlen($digits) >= 10 && substr(preg_replace('/\D/', '', (string) $row['mobile']), -10) === substr($digits, -10)));
            if ($match) {
                $found = $row;
            } else {
                $error = 'शिकायत नंबर और मोबाइल/ईमेल का मेल नहीं मिला। दोनों जाँचकर दोबारा लिखें।';
            }
        }
        $t = FormService::type('complaint');
        return $this->view('front/complaint-track', ['ref' => $ref, 'found' => $found, 'error' => $error, 'statuses' => $t[3],
            'seo' => ['title' => 'शिकायत की स्थिति', 'robots' => 'noindex,follow']]);
    }

    // ---------- करियर ----------
    public function careers(Request $request): Response
    {
        $jobs = db()->all("SELECT * FROM {p}jobs WHERE status = 'open' AND (deadline IS NULL OR deadline >= CURDATE()) ORDER BY created_at DESC");
        return $this->view('front/careers', ['jobs' => $jobs, 'side' => $this->sidebar(), 'seo' => ['title' => 'करियर और इंटर्नशिप',
            'description' => setting('site_name') . ' में नौकरी और इंटर्नशिप के मौके।']]);
    }

    public function job(Request $request, string $slug): Response
    {
        $job = db()->first("SELECT * FROM {p}jobs WHERE slug = ? AND status <> 'draft'", [$slug]);
        if (!$job) {
            throw new HttpException(404);
        }
        $open = $job['status'] === 'open' && (!$job['deadline'] || $job['deadline'] >= date('Y-m-d'));
        $form = $open ? FormService::find('job-application') : null;
        $schema = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $job['title'], 'description' => (string) ($job['description'] ?: $job['title']),
            'datePosted' => date('Y-m-d', strtotime((string) $job['created_at'])), 'employmentType' => ['full_time' => 'FULL_TIME', 'part_time' => 'PART_TIME', 'internship' => 'INTERN', 'freelance' => 'CONTRACTOR', 'contract' => 'CONTRACTOR'][$job['job_type']],
            'hiringOrganization' => ['@type' => 'Organization', 'name' => (string) setting('site_name'), 'sameAs' => url()],
            'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => (string) ($job['location'] ?: setting('address')), 'addressCountry' => 'IN']]];
        if ($job['deadline']) {
            $schema['validThrough'] = date('c', strtotime($job['deadline'] . ' 23:59:59'));
        }
        return $this->view('front/job', ['job' => $job, 'open' => $open, 'formHtml' => $form ? FormService::render($form, ['job_id' => $job['id']]) : '', 'side' => $this->sidebar(),
            'seo' => ['title' => $job['title'] . ' - करियर', 'description' => \App\Helpers\Str::limit(strip_tags((string) $job['description']), 160), 'canonical' => route('careers.show', ['slug' => $slug]),
                'robots' => $open ? 'index,follow' : 'noindex,follow', 'jsonld' => $open ? SeoService::json($schema) : '']]);
    }
}
