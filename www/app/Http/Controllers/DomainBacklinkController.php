<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchDomainBacklinks;
use App\Models\Domain;
use App\Services\DataForSeo\BacklinksFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Domain sayfasindaki "Backlink" sekmesinin "Simdi yenile" aksiyonu.
 * Veri aylik zamanlanmis komutla da cekilir (bkz.
 * RunMonthlyBacklinkFetch); bu controller API'yi kendisi cagirmaz, isi
 * kuyruga birakir (FetchDomainBacklinks).
 */
class DomainBacklinkController extends Controller
{
    public function refresh(Request $request, Domain $domain, BacklinksFetcher $fetcher): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $back = redirect()->to(route('domains.show', $domain) . '#tab-backlinks');

        if (!$fetcher->isConfigured()) {
            return $back->with('flash', ['type' => 'error', 'message' => __('DataForSEO yapılandırılmamış; backlink verisi çekilemiyor.')]);
        }

        if ($domain->backlinks_requested_at !== null) {
            return $back->with('flash', ['type' => 'success', 'message' => __('Backlink verisi zaten yenileniyor.')]);
        }

        $domain->forceFill(['backlinks_requested_at' => now()])->save();
        FetchDomainBacklinks::dispatch($domain->id);

        return $back->with('flash', ['type' => 'success', 'message' => __('Backlink verisi arka planda yenileniyor; bir iki dakika sonra sayfayı yenileyin.')]);
    }
}
