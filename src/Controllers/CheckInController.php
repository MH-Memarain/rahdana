<?php

declare(strict_types=1);

namespace Rahdana\Controllers;

use Paya\Core\Request;
use Paya\Core\Response;
use Rahdana\Support\Api;
use Rahdana\Support\DatabaseProvider;
use Rahdana\Support\Fields;

final class CheckInController
{
    public function __construct(private DatabaseProvider $provider) {}

    public function save(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $body = $request->all();
            Api::only($body, ['date', 'energy', 'availableHours', 'completion', 'note']);
            $date = Fields::date($body, 'date', true);
            $energy = Fields::number($body, 'energy', 1, 5);
            $availableHours = Fields::number($body, 'availableHours', 0, 24);
            $completion = Fields::choice($body, 'completion', ['none', 'partial', 'all']);
            $note = Fields::text($body, 'note', 2000);

            $db = $this->provider->get();
            $db->execute(
                'INSERT INTO daily_checkins (user_id, report_date, energy, available_minutes, completion, note) VALUES (?, ?, ?, ?, ?, ?) ' .
                'ON DUPLICATE KEY UPDATE energy = VALUES(energy), available_minutes = VALUES(available_minutes), completion = VALUES(completion), note = VALUES(note)',
                [Api::userId($request), $date, $energy, $availableHours * 60, $completion, $note]
            );
            return Response::json(['data' => $db->fetchOne(
                'SELECT report_date AS date, energy, available_minutes, completion, note, updated_at FROM daily_checkins WHERE user_id = ? AND report_date = ?',
                [Api::userId($request), $date]
            )]);
        });
    }

    public function latest(Request $request): Response
    {
        $value = $this->provider->get()->fetchOne(
            'SELECT report_date AS date, energy, available_minutes, completion, note, updated_at FROM daily_checkins WHERE user_id = ? ORDER BY report_date DESC LIMIT 1',
            [Api::userId($request)]
        );
        return Response::json(['data' => $value]);
    }

    public function index(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $from = $request->query('from');
            $to = $request->query('to');
            $sql = 'SELECT report_date AS date, energy, available_minutes, completion, note, updated_at FROM daily_checkins WHERE user_id = ?';
            $params = [Api::userId($request)];
            if ($from !== null) {
                $sql .= ' AND report_date >= ?';
                $params[] = Fields::date(['from' => $from], 'from', true);
            }
            if ($to !== null) {
                $sql .= ' AND report_date <= ?';
                $params[] = Fields::date(['to' => $to], 'to', true);
            }
            $sql .= ' ORDER BY report_date DESC LIMIT 180';
            return Response::json(['data' => $this->provider->get()->fetchAll($sql, $params)]);
        });
    }
}
