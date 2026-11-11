<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anahtar kelime kontrolundeki AI gorunurluk sonuclari (checks.llm_visibility)
 * ile Prompt Explorer / manuel MCP kontrolleri (prompt_explorations) ayni isi
 * iki ayri yerde tutuyordu. Tek kaynak prompt_explorations olur: otomatik
 * kontroller check_id ile kendi check'ine baglanir, eski veri buraya tasinir.
 */
return new class extends Migration
{
    private const PROMPT_TEMPLATE = '%s konusunda hangi web sitelerine bakmalıyım?';

    public function up(): void
    {
        Schema::table('prompt_explorations', function (Blueprint $table) {
            // Otomatik kelime kontrolunden geldiyse o check; check silinirse
            // kayit kelime gecmisinde kalir.
            $table->foreignId('check_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
        });

        DB::table('checks')
            ->join('keywords', 'keywords.id', '=', 'checks.keyword_id')
            ->whereNotNull('checks.llm_visibility')
            ->select('checks.id', 'checks.keyword_id', 'checks.llm_visibility', 'checks.created_at', 'checks.updated_at', 'keywords.domain_id', 'keywords.keyword')
            ->orderBy('checks.id')
            ->chunk(200, function ($checks) {
                $rows = [];
                foreach ($checks as $check) {
                    $results = json_decode((string) $check->llm_visibility, true);
                    if (!is_array($results) || $results === []) {
                        continue;
                    }

                    $prompt = collect($results)->pluck('prompt')->filter()->first()
                        ?? sprintf(self::PROMPT_TEMPLATE, $check->keyword);

                    $rows[] = [
                        'domain_id' => $check->domain_id,
                        'user_id' => null,
                        'keyword_id' => $check->keyword_id,
                        'check_id' => $check->id,
                        'prompt' => $prompt,
                        'providers' => json_encode(array_keys($results)),
                        'results' => json_encode($results, JSON_UNESCAPED_UNICODE),
                        'completed_at' => $check->created_at,
                        'created_at' => $check->created_at,
                        'updated_at' => $check->updated_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('prompt_explorations')->insert($rows);
                }
            });

        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('llm_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->json('llm_visibility')->nullable()->after('ai_overview_note');
        });

        DB::table('prompt_explorations')
            ->whereNotNull('check_id')
            ->orderBy('id')
            ->chunk(200, function ($explorations) {
                foreach ($explorations as $exploration) {
                    DB::table('checks')->where('id', $exploration->check_id)->update(['llm_visibility' => $exploration->results]);
                }
            });

        DB::table('prompt_explorations')->whereNotNull('check_id')->delete();

        Schema::table('prompt_explorations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_id');
        });
    }
};
