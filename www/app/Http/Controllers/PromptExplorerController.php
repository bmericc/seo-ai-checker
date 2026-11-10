<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\PromptExploration;
use App\Services\Llm\PromptExplorer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Yapay zeka API anahtarlari (ve dolayisiyla maliyet) admin'e ait oldugu
 * icin "Yapay zeka gorunurlugu" sekmesi gibi yalnizca admin kullanabilir.
 */
class PromptExplorerController extends Controller
{
    public function store(Request $request, Domain $domain, PromptExplorer $explorer): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);
        $this->ensureIsAdmin($request);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:' . PromptExplorer::MAX_PROMPT_LENGTH],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['string'],
        ]);

        try {
            $exploration = $explorer->start($domain, $request->user(), $data['prompt'], $data['providers'] ?? null);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->to(route('domains.show', $domain) . '#tab-ai')
                ->withInput()
                ->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('domains.prompts.show', [$domain, $exploration]);
    }

    public function show(Request $request, Domain $domain, PromptExploration $exploration): View
    {
        $this->ensureCanAccessDomain($request, $domain);
        $this->ensureIsAdmin($request);
        abort_unless($exploration->domain_id === $domain->id, 404);

        $exploration->setRelation('domain', $domain);

        return view('domains.prompt-exploration', [
            'domain' => $domain,
            'exploration' => $exploration,
            'history' => $domain->promptExplorations()->whereKeyNot($exploration->id)->limit(10)->get(),
        ]);
    }
}
