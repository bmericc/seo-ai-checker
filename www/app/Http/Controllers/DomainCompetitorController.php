<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CompetitorBlacklist;
use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Services\Competitors\CompetitorManager;
use App\Support\Domain as DomainHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rakip secimi: SERP kesfindeki bir domain'i "rakip olarak sec" ya da
 * "rakip degil" olarak isaretleme, elle rakip ekleme, kaldirma ve
 * rakibin anahtar kelimelerini yeniden cekme. Hepsi domain'de yazma
 * yetkisi ister.
 */
class DomainCompetitorController extends Controller
{
    public function store(Request $request, Domain $domain, CompetitorManager $manager): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);

        $data = $request->validate([
            'competitor_domain' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(DomainCompetitor::STATUSES)],
            'source' => ['nullable', Rule::in([DomainCompetitor::SOURCE_SERP, DomainCompetitor::SOURCE_MANUAL])],
        ]);

        $host = DomainHelper::fromFreeText($data['competitor_domain']);
        if ($host === null) {
            return $this->back($domain, 'error', __('Geçerli bir domain girin (örnek: rakip.com).'));
        }
        if (DomainHelper::equals($host, $domain->domain)) {
            return $this->back($domain, 'error', __('Kendi domaininizi rakip olarak ekleyemezsiniz.'));
        }
        if (($data['status'] ?? DomainCompetitor::TRACKED) === DomainCompetitor::TRACKED && CompetitorBlacklist::matches($host)) {
            return $this->back($domain, 'error', __(':domain genel rakip kara listesinde; rakip olarak eklenemez.', ['domain' => $host]));
        }

        $competitor = $manager->upsert(
            $domain,
            $host,
            $data['status'] ?? DomainCompetitor::TRACKED,
            $data['source'] ?? DomainCompetitor::SOURCE_MANUAL,
            $request->user(),
        );

        if ($competitor->isTracked()) {
            return $this->back($domain, 'success', __(':domain rakip olarak eklendi; öne çıktığı anahtar kelimeler arka planda çekiliyor.', ['domain' => $host]));
        }

        return $this->back($domain, 'success', __(':domain rakip değil olarak işaretlendi ve keşif listesinden gizlendi.', ['domain' => $host]));
    }

    public function update(Request $request, Domain $domain, DomainCompetitor $competitor, CompetitorManager $manager): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);
        abort_unless($competitor->domain_id === $domain->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(DomainCompetitor::STATUSES)]]);

        $manager->upsert($domain, $competitor->competitor_domain, $data['status'], $competitor->source, $request->user());

        return $this->back($domain, 'success', __(':domain güncellendi.', ['domain' => $competitor->competitor_domain]));
    }

    public function destroy(Request $request, Domain $domain, DomainCompetitor $competitor): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);
        abort_unless($competitor->domain_id === $domain->id, 404);

        $competitor->delete();

        return $this->back($domain, 'success', __(':domain rakip listesinden kaldırıldı.', ['domain' => $competitor->competitor_domain]));
    }

    public function refresh(Request $request, Domain $domain, DomainCompetitor $competitor, CompetitorManager $manager): RedirectResponse
    {
        $this->ensureCanEditDomain($request, $domain);
        abort_unless($competitor->domain_id === $domain->id, 404);
        abort_unless($competitor->isTracked(), 422);

        if (!$manager->requestKeywords($competitor)) {
            return $this->back($domain, 'success', __(':domain için anahtar kelimeler zaten çekiliyor.', ['domain' => $competitor->competitor_domain]));
        }

        return $this->back($domain, 'success', __(':domain için anahtar kelimeler yeniden çekiliyor.', ['domain' => $competitor->competitor_domain]));
    }

    private function back(Domain $domain, string $type, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('domains.show', $domain) . '#tab-competitors')
            ->with('flash', ['type' => $type, 'message' => $message]);
    }
}
