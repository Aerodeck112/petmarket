<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Scrie in jurnalul de audit. Nu primeste niciodata parole sau date de card. */
class Audit
{
    public static function log(string $action, ?Model $subject = null, ?string $summary = null, ?array $before = null, ?array $after = null, ?int $clientId = null): AuditLog
    {
        $actor = Auth::user();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'client_id' => $clientId,
            'summary' => $summary,
            'before' => $before ? self::scrub($before) : null,
            'after' => $after ? self::scrub($after) : null,
            'ip' => request()?->ip(),
        ]);
    }

    private static function scrub(array $data): array
    {
        $sensitive = ['password', 'payload', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'wp_password_hash'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '[ascuns]';
            } elseif (is_array($value)) {
                $data[$key] = self::scrub($value);
            }
        }

        return $data;
    }
}
