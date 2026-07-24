<?php

namespace Tests\Unit;

use App\Services\Llm\LlmVisibilityPrompt;
use PHPUnit\Framework\TestCase;

class LlmVisibilityPromptTest extends TestCase
{
    /**
     * "yunanistan" gibi ciplak bir ulke adi gonderildiginde modelin soru
     * sorup aciklama istemesi (canli test edildi) sorununu cozmek icin
     * kelime bir sorguya sarmalanir - bkz. LlmVisibilityPrompt yorumu.
     */
    public function test_user_prompt_wraps_the_keyword_into_a_source_seeking_question(): void
    {
        $this->assertSame(
            'yunanistan konusunda hangi web sitelerine bakmalıyım?',
            LlmVisibilityPrompt::userPrompt('yunanistan'),
        );
    }

    public function test_system_prompt_forbids_clarifying_questions(): void
    {
        $this->assertStringContainsString('soru sorma', LlmVisibilityPrompt::SYSTEM_PROMPT);
    }
}
