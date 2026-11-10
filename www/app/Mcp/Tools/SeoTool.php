<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\McpScopes;
use App\Models\Domain;
use App\Models\User;
use App\Support\Domain as DomainHelper;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Registrar;

/**
 * Tum MCP tool'larinin ortak yetki kurallari:
 * - Uye yalnizca kendi domainlerini, admin tum domainleri gorur
 *   (Domain::isVisibleTo ile ayni kural).
 * - Okuma tool'lari "read" (ya da paketin "mcp:use") scope'unu, yazma
 *   tool'lari "write" scope'unu ister; scope'u olmayan tool listede de
 *   gorunmez.
 * - adminOnly tool'lar yalnizca admin kullanicilara listelenir.
 */
abstract class SeoTool extends Tool
{
    /** "write" scope'u gerektirir. */
    protected bool $writes = false;

    /** Yalnizca admin. */
    protected bool $adminOnly = false;

    public function shouldRegister(): bool
    {
        $user = $this->user();

        if ($user === null || !$user->isApproved()) {
            return false;
        }

        if ($this->adminOnly && !$user->is_admin) {
            return false;
        }

        return $this->writes ? $this->canWrite($user) : $this->canRead($user);
    }

    protected function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Cagri aninda da kontrol edilir: tool listesi istemcide onbellekte
     * kalmis olabilir.
     */
    protected function authorizedUser(): User
    {
        if (!$this->shouldRegister()) {
            throw new ToolError($this->writes
                ? __('Bu işlem için "write" izni gerekiyor. Bağlantıyı yazma izniyle yeniden yetkilendirin.')
                : __('Bu işlem için yetkiniz yok.'));
        }

        return $this->user();
    }

    protected function canRead(User $user): bool
    {
        return $user->tokenCan(McpScopes::READ)
            || $user->tokenCan(McpScopes::WRITE)
            || $user->tokenCan(Registrar::OAUTH_SCOPE);
    }

    protected function canWrite(User $user): bool
    {
        return $user->tokenCan(McpScopes::WRITE);
    }

    /**
     * @return Builder<Domain>
     */
    protected function visibleDomains(User $user): Builder
    {
        return Domain::query()->when(!$user->is_admin, fn (Builder $q) => $q->where('user_id', $user->id));
    }

    /**
     * Domain'i id'si ya da adiyla bulur. Admin'de ayni isim birden fazla
     * uyede kayitliysa once kendi kaydini, yoksa hata ile id'leri doner.
     */
    protected function resolveDomain(User $user, mixed $reference): Domain
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            throw new ToolError(__('Domain belirtilmedi.'));
        }

        if (ctype_digit($reference)) {
            $domain = $this->visibleDomains($user)->whereKey((int) $reference)->first();
            if ($domain !== null) {
                return $domain;
            }
        }

        $name = DomainHelper::fromFreeText($reference) ?? strtolower($reference);
        $matches = $this->visibleDomains($user)->where('domain', $name)->get();

        if ($matches->isEmpty()) {
            throw new ToolError(__(':domain bulunamadı. list_domains ile kayıtlı domainlerinizi görebilirsiniz.', ['domain' => $reference]));
        }

        if ($matches->count() > 1) {
            $own = $matches->firstWhere('user_id', $user->id);
            if ($own !== null) {
                return $own;
            }

            throw new ToolError(__(':domain birden fazla üyede kayıtlı; id ile belirtin: :ids', [
                'domain' => $name,
                'ids' => $matches->pluck('id')->implode(', '),
            ]));
        }

        return $matches->first();
    }

    protected static function iso(mixed $date): ?string
    {
        return $date?->toIso8601String();
    }
}
