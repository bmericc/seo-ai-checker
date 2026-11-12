<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\Llms\LlmsTxtGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sitemap sayfalarindan uretilen llms.txt taslagi (bkz. LlmsTxtGenerator).
 * Salt okunur: hicbir sey kaydetmez, kullanici taslagi indirip kendi
 * sitesine yukler - bu yuzden domain'e erisimi olan herkes gorebilir.
 */
class LlmsTxtController extends Controller
{
    public function __construct(private readonly LlmsTxtGenerator $generator)
    {
    }

    public function show(Request $request, Domain $domain): View
    {
        $this->ensureCanAccessDomain($request, $domain);
        $domain->load('fact');

        $pages = $this->generator->activePages($domain);

        return view('domains.llms-txt', [
            'domain' => $domain,
            'pageCount' => $pages->count(),
            'scannedCount' => $pages->whereNotNull('onpage_checked_at')->count(),
            'content' => $pages->isEmpty() ? null : $this->generator->generate($domain, $pages),
        ]);
    }

    public function download(Request $request, Domain $domain): Response
    {
        $this->ensureCanAccessDomain($request, $domain);

        return response($this->generator->generate($domain, $this->generator->activePages($domain)), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="llms.txt"',
        ]);
    }
}
