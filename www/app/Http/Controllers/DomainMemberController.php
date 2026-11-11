<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Domain'i baska hesaplarla paylasma (bkz. DomainMember). Uyeleri yalnizca
 * sahip (ya da admin) yonetir; uye kendi uyeligini silerek paylasimdan
 * ayrilabilir.
 */
class DomainMemberController extends Controller
{
    public function store(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanManageDomain($request, $domain);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permission' => ['required', Rule::in(DomainMember::PERMISSIONS)],
        ]);

        $email = strtolower(trim($data['email']));
        $back = redirect()->route('domains.show', $domain)->with('open_members_modal', true);

        if ($email === strtolower((string) $domain->user?->email)) {
            return $back->with('flash', ['type' => 'error', 'message' => __('Domain sahibi zaten tam yetkili.')]);
        }

        $existingUser = User::query()->where('email', $email)->first();

        $member = $domain->members()->where('email', $email)->first();
        $domain->members()->updateOrCreate(
            ['email' => $email],
            [
                'user_id' => $existingUser?->id,
                'permission' => $data['permission'],
                'invited_by_user_id' => $member?->invited_by_user_id ?? $request->user()->id,
            ],
        );

        $message = $existingUser !== null
            ? __(':email ile paylaşıldı.', ['email' => $email])
            : __(':email henüz kayıtlı değil; bu adresle Google ile giriş yaptığında domaini görecek.', ['email' => $email]);

        return $back->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function update(Request $request, Domain $domain, DomainMember $member): RedirectResponse
    {
        $this->ensureCanManageDomain($request, $domain);
        abort_unless($member->domain_id === $domain->id, 404);

        $data = $request->validate([
            'permission' => ['required', Rule::in(DomainMember::PERMISSIONS)],
        ]);

        $member->update(['permission' => $data['permission']]);

        return redirect()
            ->route('domains.show', $domain)
            ->with('open_members_modal', true)
            ->with('flash', ['type' => 'success', 'message' => __(':email için yetki güncellendi.', ['email' => $member->email])]);
    }

    public function destroy(Request $request, Domain $domain, DomainMember $member): RedirectResponse
    {
        abort_unless($member->domain_id === $domain->id, 404);

        $leaving = $member->user_id !== null && $member->user_id === $request->user()->id;
        abort_unless($leaving || $domain->canBeManagedBy($request->user()), 403);

        $member->delete();

        if ($leaving) {
            return redirect()
                ->route('dashboard')
                ->with('flash', ['type' => 'success', 'message' => __(':domain paylaşımından ayrıldınız.', ['domain' => $domain->domain])]);
        }

        return redirect()
            ->route('domains.show', $domain)
            ->with('open_members_modal', true)
            ->with('flash', ['type' => 'success', 'message' => __(':email artık bu domaine erişemez.', ['email' => $member->email])]);
    }
}
