<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Jobs\RunKeywordCheck as RunKeywordCheckJob;
use App\Models\Keyword;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('run_keyword_check')]
#[Description('Runs a live check for a tracked keyword in the background: Google position, AI Overview presence and citation, on-page SEO and Lighthouse. Takes 1-3 minutes; read the result with list_keywords. Requires the "write" permission.')]
class RunKeywordCheck extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword_id' => $schema->integer()->description('Keyword id from list_keywords.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $request->validate(['keyword_id' => ['required', 'integer']]);

        $keyword = Keyword::with('domain')->find((int) $request->get('keyword_id'));
        if ($keyword === null || !$keyword->isVisibleTo($user)) {
            throw new ToolError(__('Anahtar kelime bulunamadı. list_keywords ile id\'leri görebilirsiniz.'));
        }
        $this->ensureCanEdit($user, $keyword->domain);

        RunKeywordCheckJob::dispatch($keyword->id);

        return Response::json([
            'queued' => true,
            'keyword' => $keyword->keyword,
            'domain' => $keyword->domain->domain,
            'message' => __('Kontrol sıraya alındı. 1-3 dakika sonra list_keywords ile sonuca bakın.'),
        ]);
    }
}
