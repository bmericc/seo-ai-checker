<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddDomain;
use App\Mcp\Tools\AddKeyword;
use App\Mcp\Tools\CheckAiAnswer;
use App\Mcp\Tools\CreateReportLink;
use App\Mcp\Tools\ExplorePrompt;
use App\Mcp\Tools\GetAiCheckPrompts;
use App\Mcp\Tools\GetDomain;
use App\Mcp\Tools\GetIndexNowStatus;
use App\Mcp\Tools\GetPromptExploration;
use App\Mcp\Tools\ListAiChecks;
use App\Mcp\Tools\ListDomains;
use App\Mcp\Tools\ListKeywords;
use App\Mcp\Tools\RunDomainCheck;
use App\Mcp\Tools\RunKeywordCheck;
use App\Mcp\Tools\SubmitIndexNow;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('SEO AI Checker')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    SEO AI Checker tracks websites' classic SEO and their visibility in AI search
    (Google AI Overviews, ChatGPT, Claude, Gemini, Perplexity).

    - Start with list_domains, then get_domain for health issues and how to fix them,
      and list_keywords for rankings and AI Overview citations.
    - Members see their own domains plus domains other members shared with them
      (list_domains shows "access": owner, write or read; write tools fail on
      read-only shares); admins see every member's domains.
    - Checks run in the background: after run_domain_check / run_keyword_check /
      explore_prompt, wait a minute or two before reading the result again.
    - Manual AI visibility check (no API keys): call get_ai_check_prompts (with the
      keyword if the user named one - a new keyword is added first, without one
      every tracked keyword is returned), answer each prompt yourself following its
      system_instruction, answer the follow_up_prompt, then record it with
      check_ai_answer using your own assistant name (claude, chatgpt, grok…).
      Free-form questions are not checked manually. list_ai_checks shows history.
    - Write tools need the "write" permission. Nothing can be deleted through MCP.
    - Reply to the user in their language; tool messages may be in Turkish.
    MARKDOWN)]
class SeoCheckerServer extends Server
{
    protected array $tools = [
        ListDomains::class,
        GetDomain::class,
        ListKeywords::class,
        GetIndexNowStatus::class,
        AddDomain::class,
        AddKeyword::class,
        RunDomainCheck::class,
        RunKeywordCheck::class,
        SubmitIndexNow::class,
        CreateReportLink::class,
        ExplorePrompt::class,
        GetPromptExploration::class,
        CheckAiAnswer::class,
        ListAiChecks::class,
        GetAiCheckPrompts::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
