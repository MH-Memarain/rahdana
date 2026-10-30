<?php

declare(strict_types=1);

/**
 * Integration suite; run ONLY against a disposable database.
 * CI supplies DB_* variables and runs bin/migrate.php first.
 */
require __DIR__ . '/../vendor/autoload.php';

if (getenv('RAHDANA_TEST_DB') !== 'yes') {
    fwrite(STDERR, "Set RAHDANA_TEST_DB=yes ONLY for a disposable test database.\n");
    exit(2);
}

putenv('ALLOW_REGISTRATION=true');
$router = require __DIR__ . '/../bootstrap/app.php';

function callApi(string $method, string $path, array $body = [], ?string $token = null): \Paya\Core\Response
{
    global $router;
    $headers = $token === null ? [] : ['Authorization' => 'Bearer ' . $token];
    parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
    return $router->dispatch(new \Paya\Core\Request($method, $path, $headers, $query, $body));
}

function payload(\Paya\Core\Response $response): array
{
    return json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
}

function assertStatus(\Paya\Core\Response $response, int $status, string $label): void
{
    if ($response->statusCode() !== $status) {
        throw new RuntimeException($label . ': expected ' . $status . ', got ' . $response->statusCode() . ' ' . $response->body());
    }
    echo "[PASS] {$label}\n";
}

$suffix = bin2hex(random_bytes(8));
$person1 = ['name' => 'Developer', 'email' => 'user-a-' . $suffix . '@example.test', 'password' => 'long-example-password-A', 'timezone' => 'Asia/Tehran'];
$person2 = ['name' => 'Other', 'email' => 'user-b-' . $suffix . '@example.test', 'password' => 'long-example-password-B', 'timezone' => 'UTC'];

assertStatus(callApi('POST', '/api/v1/auth/register', $person1), 201, 'Register first account');
assertStatus(callApi('POST', '/api/v1/auth/register', $person2), 201, 'Register second account');
assertStatus(callApi('POST', '/api/v1/auth/register', $person1), 409, 'Reject duplicate email');

$login1 = callApi('POST', '/api/v1/auth/login', ['email' => $person1['email'], 'password' => $person1['password']]);
assertStatus($login1, 200, 'Login');
$token1 = payload($login1)['data']['token'];
$token2 = payload(callApi('POST', '/api/v1/auth/login', ['email' => $person2['email'], 'password' => $person2['password']]))['data']['token'];
assertStatus(callApi('GET', '/api/v1/me', [], $token1), 200, 'Me authenticated');
assertStatus(callApi('GET', '/api/v1/me'), 401, 'Me requires token');
assertStatus(callApi('GET', '/api/v1/tasks'), 401, 'Tasks require token');

$created = callApi('POST', '/api/v1/tasks', [
    'title' => '۳۰ دقیقه مطالعه', 'estimated_minutes' => 30,
    'plan_date' => '2026-10-31', 'priority' => 'medium'
], $token1);
assertStatus($created, 201, 'Create task');
$id = (string) payload($created)['data']['id'];
assertStatus(callApi('GET', '/api/v1/tasks/' . $id, [], $token1), 200, 'Read own task');
assertStatus(callApi('GET', '/api/v1/tasks/' . $id, [], $token2), 404, 'Cannot read another user task');
assertStatus(callApi('PATCH', '/api/v1/tasks/' . $id, ['status' => 'done'], $token2), 404, 'Cannot update another user task');
assertStatus(callApi('DELETE', '/api/v1/tasks/' . $id, [], $token2), 404, 'Cannot delete another user task');
assertStatus(callApi('PATCH', '/api/v1/tasks/' . $id, ['status' => 'done'], $token1), 200, 'Complete own task');
assertStatus(callApi('POST', '/api/v1/tasks', ['title' => ''], $token1), 422, 'Reject invalid task');

$checkin = ['date' => '2026-10-31', 'energy' => 2, 'availableHours' => 2, 'completion' => 'partial', 'note' => ''];
assertStatus(callApi('POST', '/api/v1/checkins', $checkin, $token1), 200, 'Save daily report');
$report = payload(callApi('GET', '/api/v1/checkins/latest', [], $token1))['data'];
if ((int) $report['available_minutes'] !== 120 || (int) $report['energy'] !== 2) {
    throw new RuntimeException('Daily check-in values are incorrect.');
}
echo "[PASS] Daily report preserves values\n";
assertStatus(callApi('POST', '/api/v1/checkins', $checkin + ['unknown' => true], $token1), 422, 'Reject unknown checkin fields');
assertStatus(callApi('GET', '/api/v1/stats/summary?date=2026-10-31', [], $token1), 200, 'Stats endpoint');
$stats = payload(callApi('GET', '/api/v1/stats/summary?date=2026-10-31', [], $token1))['data'];
if ((int) $stats['tasks']['done'] < 1 || (int) $stats['today']['planned_minutes'] < 30) {
    throw new RuntimeException('Stats totals are incorrect.');
}
echo "[PASS] Stats aggregates saved task\n";
assertStatus(callApi('GET', '/api/v1/exports/context', [], $token1), 200, 'Portable memory export');
$context = payload(callApi('GET', '/api/v1/exports/context', [], $token1))['data'];
if ($context['schema'] !== 'rahdana.context.v1' || count($context['checkins']) < 1) {
    throw new RuntimeException('Invalid AI-context export.');
}
echo "[PASS] Export structure and data\n";
assertStatus(callApi('POST', '/api/v1/auth/logout', [], $token1), 204, 'Logout');
assertStatus(callApi('GET', '/api/v1/me', [], $token1), 401, 'Revoked token rejected');
assertStatus(callApi('GET', '/api/v1/me', [], $token2), 200, 'Second account still valid');

echo "\nRahdana integration tests passed.\n";
