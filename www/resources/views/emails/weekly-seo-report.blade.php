<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Haftalık SEO Durum Raporu') }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:#1a2233;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background:#206bc4; padding:20px 28px;">
                            <span style="color:#fff; font-size:20px; font-weight:600;">{{ __('Haftalık SEO Durum Raporu') }}</span><br>
                            <span style="color:#d6e6fb; font-size:13px;">{{ $periodStart }} — {{ $periodEnd }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 4px;">
                            <p style="margin:0; font-size:14px; color:#4b5563;">{{ __('Merhaba :name,', ['name' => $user->name]) }}</p>
                        </td>
                    </tr>

                    @foreach ($domainReports as $report)
                        @php
                            $domain = $report['domain'];
                            $fact = $report['fact'];
                            $domainCheck = $report['domain_check'];
                            $agg = $report['aggregates'];

                            $securityHeaders = $fact?->security_headers ?? [];
                            $aiCrawlers = $fact?->ai_crawlers ?? [];
                            $sitemapData = $domainCheck?->sitemap ?? [];
                            $llmsTxt = $fact?->llms_txt ?? [];
                            $blockedCrawlers = collect($aiCrawlers['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed']);

                            $trend = static function (?float $current, ?float $previous, bool $higherIsBetter = true): string {
                                if ($current === null || $previous === null || $current == $previous) {
                                    return '';
                                }
                                $improved = $higherIsBetter ? $current > $previous : $current < $previous;

                                return $improved ? ' <span style="color:#2fb344;">&#9650;</span>' : ' <span style="color:#d63939;">&#9660;</span>';
                            };
                        @endphp
                        <tr>
                            <td style="padding:20px 28px 8px;">
                                <div style="font-size:16px; font-weight:600; border-top:1px solid #e5e7eb; padding-top:16px;">
                                    {{ $domain->domain }}
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 28px 8px;">
                                <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">{{ __('Site Kontrolü') }}</div>
                                @if (!($aiCrawlers['found'] ?? false))
                                    <span style="display:inline-block; background:#eef0f2; color:#4b5563; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('robots.txt yok — hepsi açık') }}</span>
                                @elseif ($blockedCrawlers->isEmpty())
                                    <span style="display:inline-block; background:#e6f8ea; color:#2fb344; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __("AI crawler'lar: hepsi açık") }}</span>
                                @else
                                    <span style="display:inline-block; background:#fdf1da; color:#f59f00; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __("AI crawler'lar: :count engelli", ['count' => $blockedCrawlers->count()]) }}</span>
                                @endif

                                @if ($sitemapData['found'] ?? false)
                                    <span style="display:inline-block; background:#e6f8ea; color:#2fb344; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('Sitemap: geçerli') }}</span>
                                @else
                                    <span style="display:inline-block; background:#fdf1da; color:#f59f00; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('Sitemap yok') }}</span>
                                @endif

                                @if ($llmsTxt['found'] ?? false)
                                    <span style="display:inline-block; background:#e6f8ea; color:#2fb344; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('llms.txt var') }}</span>
                                @else
                                    <span style="display:inline-block; background:#eef0f2; color:#4b5563; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('llms.txt yok') }}</span>
                                @endif

                                @if ($securityHeaders['http_redirects_to_https'] ?? false)
                                    <span style="display:inline-block; background:#e6f8ea; color:#2fb344; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('HTTPS zorunlu') }}</span>
                                @else
                                    <span style="display:inline-block; background:#fdf1da; color:#f59f00; font-size:12px; padding:3px 8px; border-radius:4px; margin:0 4px 4px 0;">{{ __('HTTPS yönlendirmesi yok') }}</span>
                                @endif
                            </td>
                        </tr>

                        @if (!empty($report['drift_changes']))
                            <tr>
                                <td style="padding:0 28px 8px;">
                                    <div style="background:#fdf1da; border-radius:6px; padding:10px 14px; font-size:13px; color:#7c4a03;">
                                        <strong>{{ __('Bir önceki kontrole göre değişenler:') }}</strong>
                                        <ul style="margin:6px 0 0; padding-left:18px;">
                                            @foreach ($report['drift_changes'] as $change)
                                                <li>{{ $change }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td style="padding:4px 28px 8px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; color:#374151;">
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4;">{{ __('Ortalama SERP pozisyonu') }}</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4; text-align:right; font-weight:600;">
                                            {{ $agg['avg_position'] ?? '—' }}{!! $trend($agg['avg_position'], $agg['avg_previous_position'], higherIsBetter: false) !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4;">{{ __('Ortalama E-E-A-T skoru') }}</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4; text-align:right; font-weight:600;">
                                            {{ $agg['avg_eeat'] ?? '—' }}{!! $trend($agg['avg_eeat'], $agg['avg_previous_eeat']) !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4;">{{ __('Ortalama Lighthouse Performans') }}</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4; text-align:right; font-weight:600;">
                                            {{ $agg['avg_lighthouse_performance'] ?? '—' }}{!! $trend($agg['avg_lighthouse_performance'], $agg['avg_previous_lighthouse_performance']) !!}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4;">{{ __('AI Overview: kaynakta / gözlemlenen') }}</td>
                                        <td style="padding:6px 0; border-bottom:1px solid #f1f2f4; text-align:right; font-weight:600;">
                                            {{ $agg['ai_overview_cited_count'] }} / {{ $agg['ai_overview_present_count'] }}
                                        </td>
                                    </tr>
                                    @if ($agg['llm_checked_count'] > 0)
                                        <tr>
                                            <td style="padding:6px 0;">{{ __('AI Görünürlük Kontrolü (ChatGPT/Claude/Gemini)') }}</td>
                                            <td style="padding:6px 0; text-align:right; font-weight:600;">
                                                @foreach ($agg['llm_pass_counts'] as $provider => $passCount)
                                                    {{ $provider }}: {{ $passCount }}/{{ $agg['llm_checked_count'] }}@if (!$loop->last), @endif
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>

                        @if (!empty($report['keywords']))
                            <tr>
                                <td style="padding:4px 28px 20px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:12px; color:#374151; border-collapse:collapse;">
                                        <tr style="background:#f8f9fa; color:#6b7280;">
                                            <td style="padding:6px 8px; text-align:left;">{{ __('Anahtar kelime') }}</td>
                                            <td style="padding:6px 8px; text-align:right;">{{ __('Sıralama') }}</td>
                                            <td style="padding:6px 8px; text-align:center;">AI Overview</td>
                                            <td style="padding:6px 8px; text-align:right;">E-E-A-T</td>
                                        </tr>
                                        @foreach ($report['keywords'] as $kw)
                                            <tr style="border-top:1px solid #f1f2f4;">
                                                <td style="padding:6px 8px;">{{ $kw['keyword'] }}</td>
                                                <td style="padding:6px 8px; text-align:right;">
                                                    {{ $kw['position'] ?? '—' }}{!! $trend($kw['position'], $kw['previous_position'], higherIsBetter: false) !!}
                                                </td>
                                                <td style="padding:6px 8px; text-align:center;">
                                                    @if ($kw['ai_overview_present'])
                                                        {{ $kw['ai_overview_target_cited'] ? __('kaynakta') : __('var, kaynakta değil') }}
                                                    @else
                                                        {{ __('yok') }}
                                                    @endif
                                                </td>
                                                <td style="padding:6px 8px; text-align:right;">
                                                    {{ $kw['eeat_score'] ?? '—' }}{!! $trend($kw['eeat_score'], $kw['previous_eeat_score']) !!}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @endforeach

                    <tr>
                        <td style="padding:16px 28px 24px; border-top:1px solid #e5e7eb;">
                            <a href="{{ route('dashboard') }}" style="display:inline-block; background:#206bc4; color:#fff; text-decoration:none; font-size:13px; padding:9px 16px; border-radius:6px;">
                                {{ __('Panele Git') }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
