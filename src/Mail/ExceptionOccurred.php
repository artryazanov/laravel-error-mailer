<?php

namespace Artryazanov\ErrorMailer\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ExceptionOccurred extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $content;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $content)
    {
        $this->content = $content;

        // Ensure this mailable is dispatched to a queue if configured or by default
        $this->onQueue(config('error-mailer.queue', 'default'));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Fetch values from config
        $emailsTo = $this->parseEmails(config('error-mailer.to'));
        $ccEmails = $this->parseEmails(config('error-mailer.cc'));
        $bccEmails = $this->parseEmails(config('error-mailer.bcc'));
        $fromSender = config('error-mailer.from');

        $subject = config('error-mailer.subject');
        $subject = $this->buildSubjectWithMessage($subject);

        $view = config('error-mailer.view', 'error-mailer::emails.exception');

        return $this->from($fromSender)
            ->to($emailsTo)
            ->cc($ccEmails)
            ->bcc($bccEmails)
            ->subject($subject)
            ->view($view)
            ->with('content', $this->content);
    }

    /**
     * Parse emails from a string or array into an array of emails.
     */
    private function parseEmails($emails): array
    {
        if (is_array($emails)) {
            return array_filter($emails);
        }

        if (is_string($emails)) {
            return array_filter(array_map('trim', explode(',', $emails)));
        }

        return [];
    }

    /**
     * Build a subject with the exception message and a deterministic hash.
     * Assumes $this->content['message'] is always a string.
     */
    private function buildSubjectWithMessage(string $subject): string
    {
        $message = (string) ($this->content['message'] ?? '');
        $shortMessage = Str::limit($message, 100);
        $hash = substr(sha1($message), 0, 10);

        $finalSubject = trim($subject);
        if ($shortMessage !== '') {
            $finalSubject .= ': ' . $shortMessage;
        }

        return $finalSubject . ' [' . $hash . ']';
    }
}
