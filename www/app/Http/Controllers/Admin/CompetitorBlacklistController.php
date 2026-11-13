<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitorBlacklist;
use App\Support\Domain as DomainHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Genel rakip kara listesi (Ayarlar sayfasi, sadece admin). Birden cok
 * domain tek seferde virgul, bosluk ya da satir sonuyla ayrilarak
 * eklenebilir; URL yazilirsa host'u alinir.
 */
class CompetitorBlacklistController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['domains' => ['required', 'string', 'max:10000']]);

        $added = [];
        $invalid = [];
        foreach (preg_split('/[\s,;]+/u', $data['domains'], -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            $host = DomainHelper::fromFreeText($entry);
            if ($host === null) {
                $invalid[] = $entry;
                continue;
            }

            $row = CompetitorBlacklist::query()->firstOrCreate(['domain' => $host], ['added_by_user_id' => $request->user()->id]);
            if ($row->wasRecentlyCreated) {
                $added[] = $host;
            }
        }

        $message = $added === []
            ? __('Yeni domain eklenmedi (zaten listede).')
            : __('Kara listeye eklendi: :domains', ['domains' => implode(', ', $added)]);
        if ($invalid !== []) {
            $message .= ' ' . __('Geçersiz: :items', ['items' => implode(', ', $invalid)]);
        }

        return redirect()
            ->route('settings')
            ->with('flash', ['type' => $invalid !== [] && $added === [] ? 'error' : 'success', 'message' => $message]);
    }

    public function destroy(CompetitorBlacklist $entry): RedirectResponse
    {
        $entry->delete();

        return redirect()
            ->route('settings')
            ->with('flash', ['type' => 'success', 'message' => __(':domain kara listeden çıkarıldı.', ['domain' => $entry->domain])]);
    }
}
