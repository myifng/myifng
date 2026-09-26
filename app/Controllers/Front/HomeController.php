<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/** वेबसाइट का होम पेज। Phase 5 में पूरा न्यूज़ होमपेज (होमपेज बिल्डर से) बनेगा। */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('front/coming-soon');
    }
}
