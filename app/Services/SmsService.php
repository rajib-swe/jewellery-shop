<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends SMS through a configurable HTTP gateway.
 *
 * Bangladesh SMS providers all speak the same shape: POST a form-encoded body
 * with a sender id and a destination, then return a status string. That is the
 * only part of the contract written down here, so switching from one provider to
 * another is a URL and a set of credentials rather than a code change.
 *
 * The `log` driver is the default: with no gateway configured the message body
 * is written to the log instead of being sent, so a local run and a nightly
 * scheduler tick can never quietly post to a real paid gateway.
 */
class SmsService
{
    /**
     * @return array{sent: bool, driver: string, recipient: string, response?: mixed}
     */
    public function send(string $phone, string $message): array
    {
        $phone = $this->normalisePhone($phone);
        $driver = (string) config('sms.driver');

        if ($driver === 'log') {
            Log::info('SMS (log driver, not sent)', [
                'to' => $phone,
                'body' => $message,
            ]);

            return ['sent' => false, 'driver' => $driver, 'recipient' => $phone];
        }

        if ($driver !== 'http') {
            throw new RuntimeException("Unknown SMS driver [{$driver}].");
        }

        $response = Http::asForm()
            ->timeout((int) config('sms.timeout', 20))
            ->withBasicAuth(
                (string) config('sms.api_key'),
                (string) config('sms.api_secret'),
            )
            ->post((string) config('sms.endpoint'), [
                'to' => $phone,
                'from' => (string) config('sms.sender_id'),
                'text' => $message,
            ]);

        if (! $response->successful()) {
            Log::warning('SMS gateway rejected the message', [
                'to' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return [
            'sent' => $response->successful(),
            'driver' => $driver,
            'recipient' => $phone,
            'response' => $response->json(),
        ];
    }

    /**
     * Bangladesh numbers are dialled as 8801XXXXXXXXX internationally but are
     * stored locally as 01XXXXXXXXX. Anything that already carries the country
     * code is left alone.
     */
    public function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+880'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+8801'.$digits;
        }

        return $phone;
    }
}
