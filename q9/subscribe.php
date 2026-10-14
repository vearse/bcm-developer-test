<?php

class ApiTimeout extends RuntimeException {}

interface NewsletterApi
{
    public function create(string $email, string $idempotencyKey): array;
    public function find(string $email): ?array;
}

function mask_email(string $e)
{
    return (string) preg_replace('/^(.).*(@.*)$/', '$1***$2', $e);
}

function backoff(int $attempt)
{
    return min(30, 2 ** ($attempt - 1)) + mt_rand(0, 250) / 1000;
}

function subscribe(string $rawEmail, NewsletterApi $api, array &$store, callable $sleep, callable $log, int $maxAttempts = 4): array
{
    $email = strtolower(trim($rawEmail));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $log('signup.invalid', ['email' => mask_email($rawEmail)]);
        return ['status' => 'invalid', 'attempts' => 0];
    }

    $rec =& $store[$email];
    $rec ??= ['status' => 'pending', 'key' => bin2hex(random_bytes(16)), 'attempts' => 0];
    if ($rec['status'] !== 'pending') {
        return ['status' => $rec['status'], 'attempts' => $rec['attempts']];
    }

    for ($n = 1; $n <= $maxAttempts; $n++) {
        $rec['attempts']++;
        try {
            if ($rec['attempts'] > 1 && $api->find($email) !== null) {
                $rec['status'] = 'subscribed';
                $log('signup.reconciled', ['email' => mask_email($email), 'attempt' => $rec['attempts']]);
                break;
            }
            $res = $api->create($email, $rec['key']);
        } catch (ApiTimeout) {
            $log('signup.timeout', ['email' => mask_email($email), 'attempt' => $rec['attempts']]);
            $sleep(backoff($n));
            continue;
        }

        $code = $res['status'];
        if (in_array($code, [200, 201, 409], true)) {
            $rec['status'] = 'subscribed';
            $log('signup.ok', ['email' => mask_email($email), 'http' => $code, 'attempt' => $rec['attempts']]);
            break;
        }
        if ($code === 400 || $code === 422) {
            $rec['status'] = 'failed';
            $log('signup.rejected', ['email' => mask_email($email), 'http' => $code]);
            break;
        }
        $log('signup.retry', ['email' => mask_email($email), 'http' => $code, 'attempt' => $rec['attempts']]);
        $wait = $code === 429 ? (float) ($res['retry_after'] ?? backoff($n)) : backoff($n);
        $sleep($wait);
    }

    return ['status' => $rec['status'], 'attempts' => $rec['attempts']];
}
