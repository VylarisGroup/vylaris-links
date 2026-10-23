<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyReport extends Mailable
{
    /** @param array<int, array{page: \App\Models\Page, stats: array, best: ?\App\Models\Item}> $rows */
    public function __construct(public User $user, public array $rows)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dein Linknest-Wochenbericht');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-report');
    }
}
