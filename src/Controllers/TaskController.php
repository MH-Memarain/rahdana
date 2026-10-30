<?php

declare(strict_types=1);

namespace Rahdana\Controllers;

use InvalidArgumentException;
use Paya\Core\Request;
use Paya\Core\Response;
use Rahdana\Support\Api;
use Rahdana\Support\DatabaseProvider;
use Rahdana\Support\Fields;

final class TaskController
{
    public function __construct(private DatabaseProvider $provider) {}

    public function index(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $sql = 'SELECT id, title, notes, priority, status, estimated_minutes, due_date, plan_date, start_time, created_at, updated_at FROM tasks WHERE user_id = ?';
            $params = [Api::userId($request)];
            $date = $request->query('plan_date');
            if ($date !== null) {
                $sql .= ' AND plan_date = ?';
                $params[] = Fields::date(['plan_date' => $date], 'plan_date', true);
            }
            $status = $request->query('status');
            if ($status !== null) {
                $sql .= ' AND status = ?';
                $params[] = Fields::choice(['status' => $status], 'status', ['pending', 'in_progress', 'done']);
            }
            $sql .= ' ORDER BY (plan_date IS NULL), plan_date ASC, (start_time IS NULL), start_time ASC, id DESC LIMIT 200';
            return Response::json(['data' => $this->provider->get()->fetchAll($sql, $params)]);
        });
    }

    public function show(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $task = $this->find(Api::userId($request), Fields::id($request->route('id')));
            return $task === null
                ? Response::error('NOT_FOUND', 'Task not found.', 404)
                : Response::json(['data' => $task]);
        });
    }

    public function create(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $body = $request->all();
            self::keys($body);
            $values = self::values($body, true);
            $db = $this->provider->get();
            $db->execute(
                'INSERT INTO tasks (user_id, title, notes, priority, status, estimated_minutes, due_date, plan_date, start_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [Api::userId($request), $values['title'], $values['notes'], $values['priority'], $values['status'], $values['estimated_minutes'], $values['due_date'], $values['plan_date'], $values['start_time']]
            );
            return Response::json(['data' => $this->find(Api::userId($request), (int) $db->lastInsertId())], 201);
        });
    }

    public function update(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $id = Fields::id($request->route('id'));
            $userId = Api::userId($request);
            $existing = $this->find($userId, $id);
            if ($existing === null) {
                return Response::error('NOT_FOUND', 'Task not found.', 404);
            }
            $body = $request->all();
            self::keys($body);
            if ($body === []) {
                throw new InvalidArgumentException('At least one field is required.');
            }
            $values = self::values($body, false);
            $date = array_key_exists('plan_date', $values) ? $values['plan_date'] : $existing['plan_date'];
            $start = array_key_exists('start_time', $values) ? $values['start_time'] : $existing['start_time'];
            if ($start !== null && $date === null) {
                throw new InvalidArgumentException('plan_date is required when start_time is set.');
            }
            $updates = [];
            $params = [];
            foreach ($values as $key => $value) {
                $updates[] = $key . ' = ?';
                $params[] = $value;
            }
            $params[] = $id;
            $params[] = $userId;
            $this->provider->get()->execute(
                'UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
            return Response::json(['data' => $this->find($userId, $id)]);
        });
    }

    public function delete(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $affected = $this->provider->get()->execute(
                'DELETE FROM tasks WHERE id = ? AND user_id = ?',
                [Fields::id($request->route('id')), Api::userId($request)]
            );
            return $affected === 0
                ? Response::error('NOT_FOUND', 'Task not found.', 404)
                : Response::noContent();
        });
    }

    private function find(int $userId, int $id): ?array
    {
        return $this->provider->get()->fetchOne(
            'SELECT id, title, notes, priority, status, estimated_minutes, due_date, plan_date, start_time, created_at, updated_at FROM tasks WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    private static function keys(array $body): void
    {
        Api::only($body, ['title', 'notes', 'priority', 'status', 'estimated_minutes', 'due_date', 'plan_date', 'start_time']);
    }

    private static function values(array $body, bool $creating): array
    {
        $result = [];
        if ($creating || array_key_exists('title', $body)) {
            $result['title'] = Fields::text($body, 'title', 200, true);
        }
        if ($creating || array_key_exists('notes', $body)) {
            $result['notes'] = Fields::text($body, 'notes', 4000);
        }
        if ($creating || array_key_exists('priority', $body)) {
            $result['priority'] = Fields::choice(['priority' => $body['priority'] ?? 'medium'], 'priority', ['low', 'medium', 'high']);
        }
        if ($creating || array_key_exists('status', $body)) {
            $result['status'] = Fields::choice(['status' => $body['status'] ?? 'pending'], 'status', ['pending', 'in_progress', 'done']);
        }
        if ($creating || array_key_exists('estimated_minutes', $body)) {
            $result['estimated_minutes'] = Fields::number(['estimated_minutes' => $body['estimated_minutes'] ?? 30], 'estimated_minutes', 5, 1440);
        }
        foreach (['due_date', 'plan_date'] as $key) {
            if ($creating || array_key_exists($key, $body)) {
                $result[$key] = Fields::date($body, $key);
            }
        }
        if ($creating || array_key_exists('start_time', $body)) {
            $result['start_time'] = Fields::clock($body, 'start_time');
        }
        if (($result['start_time'] ?? null) !== null && ($result['plan_date'] ?? null) === null && $creating) {
            throw new InvalidArgumentException('plan_date is required when start_time is set.');
        }
        return $result;
    }
}
