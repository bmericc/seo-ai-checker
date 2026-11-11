@php
    $llmMentions = $domain->fact?->llm_mentions;
    $llmMentionsCheckedAt = $domain->fact?->llm_mentions_checked_at;
    $platformLabels = [
        'google' => __('Google AI (AI Overview / AI Mode)'),
        'chat_gpt' => 'ChatGPT',
    ];
@endphp

@if ($dataForSeoConfigured || $llmMentions)
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('AI Mention Verisi') }}</h3>
                <div class="text-secondary small">
                    {{ __('Prompt çalıştırmadan, DataForSEO\'nun topladığı AI cevaplarında domain\'in kaç kez geçtiği.') }}
                    @if ($llmMentionsCheckedAt)
                        · {{ __('Son güncelleme: :date', ['date' => $llmMentionsCheckedAt->format('Y-m-d H:i')]) }}
                    @endif
                </div>
            </div>
            @if (auth()->user()->is_admin && $dataForSeoConfigured)
                <div class="card-actions">
                    <form method="post" action="{{ route('domains.llm-mentions.fetch', $domain) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-refresh icon"></i> {{ $llmMentions ? __('Yenile') : __('Getir') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        @if (!$llmMentions)
            <div class="card-body">
                <p class="text-secondary mb-0">{{ __('Henüz AI mention verisi getirilmedi.') }}</p>
            </div>
        @else
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($llmMentions['platforms'] ?? [] as $platform => $data)
                        <div class="col-md-6">
                            <h4 class="mb-2">
                                {{ $platformLabels[$platform] ?? $platform }}
                                <span class="text-secondary small fw-normal">({{ strtoupper((string) ($data['language_code'] ?? '')) }}, {{ __('konum kodu') }} {{ $data['location_code'] ?? '-' }})</span>
                            </h4>
                            @if ($platform === 'chat_gpt')
                                <p class="text-secondary small mb-2">{{ __('DataForSEO ChatGPT mention verisini yalnızca ABD / İngilizce için sunuyor.') }}</p>
                            @endif

                            @if ($data['error'] ?? null)
                                <p class="text-danger small mb-0">{{ $data['error'] }}</p>
                            @else
                                <div class="d-flex gap-4 mb-3">
                                    <div>
                                        <div class="text-secondary small">{{ __('Mention') }}</div>
                                        <div class="h2 mb-0">{{ $data['mentions'] !== null ? number_format($data['mentions'], 0, ',', '.') : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-secondary small" data-bs-toggle="tooltip" title="{{ __('Domain\'in geçtiği soruların tahmini aylık AI arama hacmi toplamı') }}">{{ __('AI arama hacmi') }}</div>
                                        <div class="h2 mb-0">{{ $data['ai_search_volume'] !== null ? number_format($data['ai_search_volume'], 0, ',', '.') : '-' }}</div>
                                    </div>
                                </div>

                                @if (!empty($data['top_questions']))
                                    <div class="text-secondary small mb-1">{{ __('Domain\'in geçtiği öne çıkan sorular') }}</div>
                                    <ul class="list-unstyled small mb-0">
                                        @foreach ($data['top_questions'] as $question)
                                            <li class="mb-1">
                                                @if ($question['cited'])
                                                    <span class="badge bg-success-lt" data-bs-toggle="tooltip" title="{{ __('Domain kaynak olarak gösterilmiş') }}">{{ __('kaynak') }}</span>
                                                @endif
                                                {{ $question['question'] }}
                                                @if ($question['ai_search_volume'] !== null)
                                                    <span class="text-secondary">· {{ number_format($question['ai_search_volume'], 0, ',', '.') }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-secondary small mb-0">{{ __('Bu platformda domain\'in geçtiği bir cevap bulunamadı.') }}</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
