<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\DomainMember;
use App\Models\User;

/**
 * E-posta sahipligi kanitlanmis bir kullanici icin yapilan islemler:
 * ADMIN_EMAILS'teki adresleri admin + onayli yapar ve hesap yokken o
 * e-postayla paylasilmis domainleri hesaba baglar.
 *
 * Yalnizca e-postasi dogrulanmis kullanici icin cagrilmalidir (Google
 * girisi ya da e-posta dogrulama linki). Sifreyle kayit olup dogrulamamis
 * birine uygulanirsa, baskasinin e-postasini yazan herkes admin olabilir
 * ya da ona paylasilan domainleri alabilir.
 */
class VerifiedUserOnboarding
{
    public function handle(User $user): void
    {
        if (!$user->hasVerifiedEmail()) {
            return;
        }

        if ($this->isBootstrapAdmin($user->email) && (!$user->is_admin || !$user->isApproved())) {
            $user->forceFill([
                'is_admin' => true,
                'approved_at' => $user->approved_at ?? now(),
            ])->save();
        }

        DomainMember::claimPendingFor($user);
    }

    private function isBootstrapAdmin(string $email): bool
    {
        $email = strtolower($email);

        foreach (config('seo.bootstrap_admin_emails', []) as $candidate) {
            if (strtolower($candidate) === $email) {
                return true;
            }
        }

        return false;
    }
}
