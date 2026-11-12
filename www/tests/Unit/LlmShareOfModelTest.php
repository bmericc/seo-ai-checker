<?php

namespace Tests\Unit;

use App\Models\PromptExploration;
use App\Services\Analytics\LlmShareOfModel;
use PHPUnit\Framework\TestCase;

class LlmShareOfModelTest extends TestCase
{
    private function exploration(array $results): PromptExploration
    {
        return (new PromptExploration())->forceFill(['results' => $results]);
    }

    public function test_builds_matrix_per_provider_with_own_domain_first(): void
    {
        $explorations = collect([
            $this->exploration([
                'openai' => ['present' => true, 'response' => 'example.com ve rival.com iyi', 'error' => null],
                'anthropic' => ['present' => false, 'response' => 'rival.com öneririm', 'error' => null],
            ]),
            $this->exploration([
                'openai' => ['present' => false, 'response' => 'other.org', 'error' => null, 'citations' => ['https://www.rival.com/x']],
                'anthropic' => ['present' => false, 'response' => null, 'error' => 'timeout'],
            ]),
        ]);

        $result = LlmShareOfModel::analyze('www.example.com', $explorations, ['serp-only.com']);

        $this->assertSame(3, $result['answers']);
        $this->assertSame(['openai' => 2, 'anthropic' => 1], $result['answered']);

        $rows = collect($result['rows'])->keyBy('domain');
        $this->assertSame('example.com', $result['rows'][0]['domain']);
        $this->assertTrue($result['rows'][0]['own']);
        $this->assertSame(['openai' => 50, 'anthropic' => 0], $rows['example.com']['by_provider']);
        $this->assertSame(['openai' => 100, 'anthropic' => 100], $rows['rival.com']['by_provider']);
        $this->assertSame(100, $rows['rival.com']['rate']);
        // SERP rakibi hic anilmasa da satir olarak gorunur.
        $this->assertSame(0, $rows['serp-only.com']['rate']);
        $this->assertSame('rival.com', $result['rows'][1]['domain']);
    }

    public function test_own_subdomains_are_not_listed_as_competitors(): void
    {
        $result = LlmShareOfModel::analyze('example.com', collect([
            $this->exploration(['openai' => ['present' => true, 'response' => 'blog.example.com', 'error' => null]]),
        ]));

        $this->assertSame(['example.com'], array_column($result['rows'], 'domain'));
    }

    public function test_no_answers_returns_empty_rows(): void
    {
        $this->assertSame([], LlmShareOfModel::analyze('example.com', collect())['rows']);
    }
}
