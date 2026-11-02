<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ExceptionOccurred extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $content;

    /**
     * Create a new message instance.
     *
     * @param  array<string, mixed>  $content
     */
    public function __construct(array $content)
    {
        $this->content = $content;

        // Ensure this mailable is dispatched to a queue if configured or by default
        $queue = config('error-mailer.queue', 'default');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        // Fetch values from config
        $emailsTo = self::parseEmails(config('error-mailer.to'));
        $ccEmails = self::parseEmails(config('error-mailer.cc'));
        $bccEmails = self::parseEmails(config('error-mailer.bcc'));
        $fromSender = config('error-mailer.from');

        $rawSubject = config('error-mailer.subject');
        $subject = is_string($rawSubject) && trim($rawSubject) !== ''
            ? $rawSubject
            : 'Error on '.config('app.name', 'Laravel').' ('.config('app.env', 'production').')';
        $subject = $this->buildSubjectWithMessage($subject);

        $rawView = config('error-mailer.view', 'error-mailer::emails.exception');
        $view = is_string($rawView) && trim($rawView) !== ''
            ? $rawView
            : 'error-mailer::emails.exception';

        if (is_string($fromSender) && trim($fromSender) !== '') {
            $this->from(trim($fromSender));
        }

        if ($emailsTo !== []) {
            $this->to($emailsTo);
        }

        if ($ccEmails !== []) {
            $this->cc($ccEmails);
        }

        if ($bccEmails !== []) {
            $this->bcc($bccEmails);
        }

        return $this->subject($subject)
            ->view($view)
            ->with('content', $this->content);
    }

    /**
     * Parse emails from a string or array into an array of emails.
     *
     * @return array<int, string>
     */
    public static function parseEmails(mixed $emails): array
    {
        if (is_array($emails)) {
            return array_values(array_filter(array_map(
                static fn (mixed $email): string => is_string($email) ? trim($email) : '',
                $emails
            )));
        }

        if (is_string($emails)) {
            return array_values(array_filter(array_map('trim', explode(',', $emails))));
        }

        return [];
    }

    /**
     * Build a subject with the exception message and a deterministic hash.
     */
    private function buildSubjectWithMessage(string $subject): string
    {
        $rawMessage = (string) ($this->content['message'] ?? '');
        $normalizedMessage = trim((string) preg_replace('/[\r\n]+/', ' ', $rawMessage));
        $shortMessage = Str::limit($normalizedMessage, 100);
        $hash = substr(sha1($rawMessage), 0, 10);

        $finalSubject = trim($subject);
        if ($shortMessage !== '') {
            $finalSubject .= ': '.$shortMessage;
        }

        if (config('error-mailer.include_hash_in_subject', false)) {
            $finalSubject .= ' ['.$hash.']';
        }

        return $finalSubject;
    }
}
