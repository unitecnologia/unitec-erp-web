<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrcamentoEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<array{path: string, name: string}>  $fileAttachments
     */
    public function __construct(
        public string $messageBody,
        public string $subjectLine,
        public array $fileAttachments = [],
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $fromAddress = $this->fromAddress ?: config('mail.from.address');
        $fromName = $this->fromName ?: config('mail.from.name');

        return new Envelope(
            from: filled($fromAddress) ? new Address($fromAddress, (string) $fromName) : null,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.orcamento',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return collect($this->fileAttachments)
            ->filter(fn (array $attachment): bool => is_file($attachment['path'] ?? ''))
            ->map(fn (array $attachment): Attachment => Attachment::fromPath($attachment['path'])
                ->as($attachment['name']))
            ->all();
    }
}
