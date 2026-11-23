<?php

namespace App\Services\Notifications;

use App\Mail\GenericNotification;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emailuri catre clienti si catre magazin, cu jurnal de livrare. Un email esuat nu opreste
 * niciodata operatiunea care l-a declansat (comanda ramane plasata).
 */
class Notifier
{
    /** @param list<string> $recipients */
    public function send(array $recipients, string $subject, string $html, string $context, string $relatedType = '', int $relatedId = 0, ?int $userId = null, array $attachments = []): bool
    {
        $recipients = array_values(array_unique(array_filter($recipients)));
        if ($recipients === []) {
            return false;
        }

        $status = 'sent';
        $error = null;

        try {
            Mail::to($recipients)->send(new GenericNotification($subject, $html, $attachments));
        } catch (Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 1000);
            report($e);
        }

        EmailLog::create([
            'user_id' => $userId, 'to_email' => implode(', ', $recipients), 'subject' => mb_substr($subject, 0, 250),
            'context' => $context, 'status' => $status, 'error_message' => $error,
            'related_type' => $relatedType ?: null, 'related_id' => $relatedId ?: null,
        ]);

        return $status === 'sent';
    }
}
