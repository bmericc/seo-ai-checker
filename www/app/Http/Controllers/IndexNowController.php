<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\IndexNow\IndexNowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IndexNowController extends Controller
{
    public function __construct(private readonly IndexNowService $indexNow)
    {
    }

    /**
     * Kullanicinin sitesinin kokune yukleyecegi anahtar dosyasini indirir.
     */
    public function keyFile(Request $request, Domain $domain): Response
    {
        $this->ensureCanEditDomain($request, $domain);

        $key = $this->indexNow->ensureKey($domain);

        return response($key, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.txt"', $key),
        ]);
    }

    public function regenerate(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $this->indexNow->regenerateKey($domain);

        return $this->back($domain, 'success', __('Yeni IndexNow anahtarı oluşturuldu. Yeni dosyayı sitenize yükleyin.'));
    }

    public function verify(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $result = $this->indexNow->verifyKeyFile($domain);

        return $this->back($domain, $result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function submit(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $data = $request->validate([
            'source' => ['required', 'in:sitemap,manual'],
            'urls' => ['nullable', 'string', 'max:200000'],
        ]);

        $urls = $data['source'] === 'sitemap'
            ? $this->indexNow->sitemapUrls($domain)
            : preg_split('/\s+/', (string) ($data['urls'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        $submission = $this->indexNow->submit($domain, $urls, $data['source']);

        return $this->back(
            $domain,
            $submission->ok ? 'success' : 'error',
            $submission->ok
                ? trans_choice('{1} 1 URL bildirildi.|[2,*] :count URL bildirildi.', $submission->url_count, ['count' => $submission->url_count]) . ' ' . $submission->message
                : (string) $submission->message,
        );
    }

    private function back(Domain $domain, string $type, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('domains.show', $domain) . '#tab-indexnow')
            ->with('flash', ['type' => $type, 'message' => $message]);
    }
}
