<?php

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\Domain;
use App\Models\Keyword;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function ensureCanAccessDomain(Request $request, Domain $domain): void
    {
        abort_unless($domain->isVisibleTo($request->user()), 403);
    }

    protected function ensureCanEditDomain(Request $request, Domain $domain): void
    {
        abort_unless($domain->canBeEditedBy($request->user()), 403);
    }

    protected function ensureCanManageDomain(Request $request, Domain $domain): void
    {
        abort_unless($domain->canBeManagedBy($request->user()), 403);
    }

    protected function ensureCanEditKeyword(Request $request, Keyword $keyword): void
    {
        abort_unless($keyword->domain->canBeEditedBy($request->user()), 403);
    }

    protected function ensureCanAccessKeyword(Request $request, Keyword $keyword): void
    {
        abort_unless($keyword->isVisibleTo($request->user()), 403);
    }

    protected function ensureCanAccessCheck(Request $request, Check $check): void
    {
        abort_unless($check->isVisibleTo($request->user()), 403);
    }

    protected function ensureIsAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
