<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    public function index()
    {
        $body = "User-agent: facebookexternalhit\n"
            . "Allow: /\n"
            . "\n"
            . "User-agent: *\n"
            . "Disallow: /auth/login\n"
            . "Disallow: /forgot-password\n"
            . "Disallow: /reset-password/\n"
            . "\n"
            . 'Sitemap: ' . url('sitemap.xml') . "\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
