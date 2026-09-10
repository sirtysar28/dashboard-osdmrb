<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifikasi generik dengan desain HTML mengikuti template aplikasi
 * (warna mengikuti tema yang diatur Administrator Utama).
 */
class AppNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $type,
        public string $title,
        public string $greeting,
        public array $lines = [],
        public array $fields = [],
        public ?string $actionUrl = null,
        public ?string $actionText = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Dashboard Biro OSDMRB] '.$this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.app-notification',
            with: [
                'type' => $this->type,
                'title' => $this->title,
                'greeting' => $this->greeting,
                'lines' => $this->lines,
                'fields' => $this->fields,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText ?? 'Buka Aplikasi',
                'primary' => Setting::get('theme_primary', '#163d4f'),
                'accent' => Setting::get('theme_accent', '#e8a13c'),
                'appName' => config('app.name', 'Dashboard Biro OSDMRB'),
                'year' => now()->year,
            ],
        );
    }
}
