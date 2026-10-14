<?php

require __DIR__ . '/subscribe.php';

class FakeApi implements NewsletterApi
{
    public array $records = [];
    public int $creates = 0;
    public array $script = [];
    private array $seenKeys = [];

    public function __construct(private bool $idempotent = true, private int $findLagsFor = 0) {}

    public function create(string $email, string $key): array
    {
        $this->creates++;
        $fault = array_shift($this->script);
        if ($fault === 'timeout_before') {
            throw new ApiTimeout();
        }
        if ($fault === 'rate_limit') {
            return ['status' => 429, 'retry_after' => 3];
        }
        if ($fault === 'server_error') {
            return ['status' => 503];
        }
        if ($fault === 'reject') {
            return ['status' => 422];
        }
        if ($this->idempotent && isset($this->seenKeys[$key])) {
            return ['status' => 200];
        }
        $this->records[] = $email;
        $this->seenKeys[$key] = true;
        if ($fault === 'accept_then_timeout') {
            throw new ApiTimeout();
        }
        return ['status' => 201];
    }

    public function find(string $email): ?array
    {
        if ($this->findLagsFor > 0) {
            $this->findLagsFor--;
            return null;
        }
        return in_array($email, $this->records, true) ? ['email' => $email] : null;
    }
}

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) {
        $fails++;
    }
}

function run(string $email, FakeApi $api, array &$store, ?array &$sleeps = null, ?array &$logs = null): array
{
    $sleeps = [];
    $logs = [];
    return subscribe(
        $email,
        $api,
        $store,
        function ($s) use (&$sleeps) { $sleeps[] = round($s); },
        function ($e, $c) use (&$logs) { $logs[] = [$e, $c]; }
    );
}

$naive = new FakeApi(false);
$naive->script = ['accept_then_timeout'];
try {
    $naive->create('jane@example.com', 'k');
} catch (ApiTimeout) {
    $naive->create('jane@example.com', 'k');
}
check('baseline: blind retry after an accepted-but-timed-out request creates a duplicate', count($naive->records) === 2);

$api = new FakeApi(false);
$api->script = ['accept_then_timeout'];
$store = [];
$r = run('Jane@Example.com ', $api, $store, $sleeps, $logs);
check('timeout after accept (no idempotency): subscribed, exactly 1 record, 1 create call', $r['status'] === 'subscribed' && count($api->records) === 1 && $api->creates === 1);

$api = new FakeApi(true, 1);
$api->script = ['accept_then_timeout'];
$store = [];
$r = run('jane@example.com', $api, $store);
check('timeout after accept + lagging lookup + idempotency key: 1 record, 2 create calls', $r['status'] === 'subscribed' && count($api->records) === 1 && $api->creates === 2);

$api = new FakeApi();
$api->script = ['rate_limit'];
$store = [];
$r = run('jane@example.com', $api, $store, $sleeps);
check('429: waits Retry-After (3s), then succeeds, 1 record', $r['status'] === 'subscribed' && $sleeps === [3.0] && count($api->records) === 1);

foreach (['not-an-email', '', 'a@b', 'jane@@example.com'] as $bad) {
    $api = new FakeApi();
    $store = [];
    $r = run($bad, $api, $store);
    check("invalid email " . var_export($bad, true) . ": status invalid, 0 API calls, nothing stored", $r['status'] === 'invalid' && $api->creates === 0 && $store === []);
}

$api = new FakeApi();
$api->script = ['reject'];
$store = [];
$r = run('jane@example.com', $api, $store);
check('422 from service: failed, no retry', $r['status'] === 'failed' && $api->creates === 1);

$api = new FakeApi();
$api->script = array_fill(0, 4, 'timeout_before');
$store = [];
$r = run('jane@example.com', $api, $store);
$keyBefore = $store['jane@example.com']['key'];
check('4 timeouts: status pending (kept, not dropped), 0 records', $r['status'] === 'pending' && $r['attempts'] === 4 && count($api->records) === 0);
$r = run('jane@example.com', $api, $store);
check('later retry completes: subscribed, 1 record, same idempotency key', $r['status'] === 'subscribed' && count($api->records) === 1 && $store['jane@example.com']['key'] === $keyBefore);

$before = $api->creates;
run('JANE@example.com', $api, $store);
check('double submit after success: no new API call, still 1 record', $api->creates === $before && count($api->records) === 1);

$api = new FakeApi();
$api->script = ['accept_then_timeout'];
$store = [];
run('jane@example.com', $api, $store, $sleeps, $logs);
$dump = json_encode($logs);
check('logs never contain the full email or the idempotency key', !str_contains($dump, 'jane@example.com') && !str_contains($dump, $store['jane@example.com']['key']) && str_contains($dump, 'j***@example.com'));

echo "\n" . ($fails ? "$fails FAILED" : 'ALL PASSED') . "\n";
exit($fails ? 1 : 0);
