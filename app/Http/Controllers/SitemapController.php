<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Route;

class SitemapController extends Controller
{
    /**
     * Generate sitemap XML
     * 
     * Note: Only includes static routes. Dynamic routes (e.g., /users/{id}) are 
     * discovered by search engines through natural crawling and internal links.
     */
    public function show(Request $request): Response
    {
        $pages = Route::where('public', 1)->get();
        
        return response()
            ->view('pages.sitemap', [
                'pages' => $pages,
                'date' => now()->toDateString(),
                'root' => $request->root()
            ])
            ->header('Content-Type', 'text/xml');
    }
}
