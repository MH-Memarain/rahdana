<?php

declare(strict_types=1);

namespace Rahdana\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Paya\Core\Request;
use Paya\Core\Response;
use Rahdana\Support\Api;
use Rahdana\Support\DatabaseProvider;
use Rahdana\Support\Fields;

final class StatsController
{
    public function __construct(private DatabaseProvider $provider) {}

    public function summary(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $db = $this->provider->get();
            $userId = Api::userId($request);
            $user = $db->fetchOne('SELECT timezone FROM users WHERE id = ?', [$userId]);
            $zone = new DateTimeZone((string) ($user['timezone'] ?? 'UTC'));
            $date = $request->query('date');
            $date = $date === null
                ? (new DateTimeImmutable('now', $zone))->format('Y-m-d')
                : Fields::date(['date' => $date], 'date', true);

            $overall = $db->fetchOne(
                "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'done'), 0) AS done, COALESCE(SUM(status = 'pending'), 0) AS pending, COALESCE(SUM(status = 'in_progress'), 0) AS in_progress FROM tasks WHERE user_id = ?",
                [$userId]
            );
            $today = $db->fetchOne(
                "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'done'), 0) AS done, COALESCE(SUM(estimated_minutes), 0) AS planned_minutes FROM tasks WHERE user_id = ? AND plan_date = ?",
                [$userId, $date]
            );
            $checkin = $db->fetchOne(
                'SELECT report_date AS date, energy, available_minutes, completion, note FROM daily_checkins WHERE user_id = ? AND report_date = ?',
                [$userId, $date]
            );
            $recent = $db->fetchAll(
                'SELECT report_date AS date, energy, available_minutes, completion FROM daily_checkins WHERE user_id = ? AND report_date <= ? ORDER BY report_date DESC LIMIT 7',
                [$userId, $date]
            );
            return Response::json(['data' => [
                'date' => $date,
                'tasks' => $overall,
                'today' => $today,
                'checkin' => $checkin,
                'recent_checkins' => $recent,
            ]]);
        });
    }

    /** Portable, user-scoped export for a future LLM; never sends data to an external provider. */
    public function context(Request $request): Response
    {
        $db = $this->provider->get();
        $userId = Api::userId($request);
        $user = $db->fetchOne('SELECT name, timezone FROM users WHERE id = ?', [$userId]);
        $checkins = $db->fetchAll(
            'SELECT report_date AS date, energy, available_minutes, completion, note FROM daily_checkins WHERE user_id = ? ORDER BY report_date DESC LIMIT 90',
            [$userId]
        );
        $tasks = $db->fetchAll(
            'SELECT title, priority, status, estimated_minutes, due_date, plan_date, start_time, notes FROM tasks WHERE user_id = ? ORDER BY updated_at DESC LIMIT 200',
            [$userId]
        );
        return Response::json(['data' => [
            'schema' => 'rahdana.context.v1',
            'generated_at' => gmdate('c'),
            'profile' => $user,
            'checkins' => $checkins,
            'tasks' => $tasks,
            'limits' => ['checkins' => 90, 'tasks' => 200],
        ]])->withHeader('Cache-Control', 'no-store');
    }
}
