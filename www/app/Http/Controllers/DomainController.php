<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainCheckRunner;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['domain' => ['required', 'string']]);

        $domain = DomainHelper::fromFreeText($request->string('domain')->toString());

        if ($domain === null) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Gecerli bir domain girin (ornek: example.com).']);
        }

        $existing = Domain::query()->where('domain', $domain)->first();
        if ($existing !== null) {
            return redirect()
                ->route('domains.show', $existing)
                ->with('flash', ['type' => 'error', 'message' => sprintf('%s zaten kayitli.', $domain)]);
        }

        $record = Domain::query()->create(['domain' => $domain]);

        return redirect()
            ->route('domains.show', $record)
            ->with('flash', ['type' => 'success', 'message' => sprintf('%s eklendi.', $domain)]);
    }

    public function show(Domain $domain): View
    {
        $domain->load([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'latestDomainCheck',
        ]);

        return view('domains.show', ['domain' => $domain]);
    }

    public function check(Domain $domain, DomainCheckRunner $checkRunner): RedirectResponse
    {
        $checkRunner->run($domain);

        return redirect()
            ->route('domains.show', $domain)
            ->with('flash', ['type' => 'success', 'message' => 'Site kontrolu tamamlandi.']);
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        $domain->delete();

        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => 'Domain ve tum kayitli anahtar kelimeleri silindi.']);
    }
}
