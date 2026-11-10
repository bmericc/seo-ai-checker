<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\ReportShare;
use App\Services\Report\ReportShareService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_report_link')]
#[Description('Creates a read-only public link to a domain\'s SEO report that can be sent to a client without an account. Choose sections and expiry; links can be revoked from the domain page. Requires the "write" permission.')]
class CreateReportLink extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'sections' => $schema->array()->items($schema->string()->enum(ReportShare::SECTIONS))
                ->description('Report sections to include. Default: all ('.implode(', ', ReportShare::SECTIONS).').'),
            'expires_in_days' => $schema->integer()->enum([7, 30, 90, 0])->description('Days until the link expires; 0 = never. Default 30.'),
            'title' => $schema->string()->description('Optional heading shown on the report, e.g. "October report for ACME".'),
        ];
    }

    public function handle(Request $request, ReportShareService $shares): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'in:'.implode(',', ReportShare::SECTIONS)],
            'expires_in_days' => ['nullable', 'integer', 'in:7,30,90,0'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $days = array_key_exists('expires_in_days', $data) && $data['expires_in_days'] !== null
            ? (int) $data['expires_in_days']
            : 30;

        $share = $shares->create(
            $domain,
            $user,
            $data['sections'] ?? null,
            $days === 0 ? null : $days,
            $data['title'] ?? null,
        );

        return Response::json([
            'url' => $share->url(),
            'sections' => $share->sections,
            'expires_at' => self::iso($share->expires_at),
        ]);
    }
}
