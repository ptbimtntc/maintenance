<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CombinedModuleReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        private readonly string $attachmentPath,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Combined Report - '.$this->periodStart->format('F Y'))
            ->view('emails.combined-module-report')
            ->attach(Attachment::fromPath($this->attachmentPath)
                ->as('combined-report-'.$this->periodStart->format('Y-m').'.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    }
}
