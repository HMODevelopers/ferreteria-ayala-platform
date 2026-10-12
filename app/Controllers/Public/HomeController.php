<?php
declare(strict_types=1);
namespace App\Controllers\Public;
use App\Core\{Controller, Request, Response};
use App\Data\DemoHomeData;
final class HomeController extends Controller {
    public function index(Request $request): Response { return $this->view('public/home', DemoHomeData::all()); }
}
