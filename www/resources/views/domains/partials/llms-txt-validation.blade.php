{{-- Bulunan llms.txt'nin bicim denetimi (bkz. App\Services\Llms\LlmsTxtValidator). Eski kayitlarda $validation null. --}}
@if (is_array($validation ?? null))
    <div class="mt-2 small">
        @if ($validation['valid'] && empty($validation['issues']))
            <span class="text-success"><i class="ti ti-circle-check me-1"></i>{{ __('Biçim uygun') }}</span>
        @endif
        <span class="text-secondary">
            {{ __(':sections bölüm, :links link', ['sections' => $validation['section_count'], 'links' => $validation['link_count']]) }}
        </span>
        @if (!empty($validation['issues']))
            <ul class="list-unstyled mb-0 mt-1">
                @foreach ($validation['issues'] as $issue)
                    <li class="{{ $issue['level'] === 'error' ? 'text-danger' : 'text-warning' }}">
                        <i class="ti {{ $issue['level'] === 'error' ? 'ti-alert-circle' : 'ti-alert-triangle' }} me-1"></i>{{ $issue['message'] }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
