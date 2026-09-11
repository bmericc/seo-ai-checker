<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Haftalik ozet e-postasi - SendWeeklySeoReportJob tarafindan, kullanicinin
 * kendi locale'i zaten App::setLocale() ile ayarlanmis bir kuyruk isi
 * icinde senkron olarak gonderilir (bkz. o job'un yorumu). Bu Mailable'in
 * kendisi ShouldQueue DEGIL - locale'in kuyruk-sonrasi asenkron render
 * sirasinda ("hangi kullanici icindi?" bilgisi kaybolmadan) dogru
 * uygulanmasini garantilemenin en basit yolu, gonderimin zaten kuyrukta
 * calisan job'un icinde, ayni PHP surecinde/senkron yapilmasidir.
 */
final class WeeklySeoReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $domainReports  bkz. WeeklySeoReportBuilder::build()
     */
    public function __construct(
        public readonly User $user,
        public readonly array $domainReports,
        public readonly string $periodStart,
        public readonly string $periodEnd,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Haftalık SEO Durum Raporu (:start - :end)', ['start' => $this->periodStart, 'end' => $this->periodEnd]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-seo-report',
            with: [
                'user' => $this->user,
                'domainReports' => $this->domainReports,
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
            ],
        );
    }
}
