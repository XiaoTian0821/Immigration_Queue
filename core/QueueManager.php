<?php
/**
 * QueueManager - automatic queue numbering, status transitions, statistics.
 *
 * Queue number format: <2-char service prefix><3-digit serial>  e.g. P001
 * Serials restart per service every operating date (daily reset is automatic).
 */
class QueueManager
{
    /** Return today's operating date (Y-m-d, server local time). */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /** Next serial for a service on a date (MAX existing + 1). */
    private static function nextSerial(int $serviceId, string $date): int
    {
        $max = (int)Database::value(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(queue_number, 2) AS UNSIGNED)), 0)
             FROM queues WHERE service_id = ? AND operating_date = ?',
            [$serviceId, $date]
        );
        return $max + 1;
    }

    /**
     * Issue a queue number.
     * @param int $serviceId
     * @param int|null $applicantId
     * @param int|null $officerId optional issuing officer
     * @return array the created queue row
     */
    public static function issue(int $serviceId, ?int $applicantId = null, ?int $officerId = null): array
    {
        $svc = Database::row('SELECT * FROM immigration_services WHERE id = ? AND active = 1', [$serviceId]);
        if ($svc === null) {
            throw new RuntimeException('Immigration service not found or inactive.');
        }

        $date   = self::today();
        $serial = self::nextSerial($serviceId, $date);
        $number = $svc['code'] . str_pad((string)$serial, 3, '0', STR_PAD_LEFT);

        // Safety net against race-condition duplicates
        $exists = (int)Database::value(
            'SELECT COUNT(*) FROM queues WHERE service_id = ? AND operating_date = ? AND queue_number = ?',
            [$serviceId, $date, $number]
        );
        if ($exists > 0) {
            $serial = self::nextSerial($serviceId, $date);
            $number = $svc['code'] . str_pad((string)$serial, 3, '0', STR_PAD_LEFT);
        }

        Database::run(
            'INSERT INTO queues (queue_number, service_id, applicant_id, assigned_officer_id, status, operating_date)
             VALUES (?,?,?,?, "waiting", ?)',
            [$number, $serviceId, $applicantId, $officerId, $date]
        );
        $queueId = (int)Database::lastInsertId();

        self::logAction($queueId, 'issued', $officerId, 'Queue ' . $number . ' issued');

        return Database::row('SELECT * FROM queues WHERE id = ?', [$queueId]);
    }

    /** Call the next waiting applicant for a service and assign to a counter. */
    public static function callNext(int $serviceId, int $counterId, int $officerId): ?array
    {
        $date = self::today();
        $q = Database::row(
            'SELECT * FROM queues WHERE service_id = ? AND operating_date = ? AND status = "waiting"
             ORDER BY issued_at ASC, id ASC LIMIT 1',
            [$serviceId, $date]
        );
        if ($q === null) {
            return null;
        }
        $q = self::setStatus($q, 'called', [
            'counter_id'          => $counterId,
            'assigned_officer_id' => $officerId,
            'called_at'           => date('Y-m-d H:i:s'),
        ], $officerId, 'Called to counter ' . Database::value('SELECT counter_number FROM counters WHERE id = ?', [$counterId]));

        // Mark counter busy
        Database::run('UPDATE counters SET status = "busy" WHERE id = ?', [$counterId]);
        return $q;
    }

    /** Mark a called queue as serving. */
    public static function markServing(int $queueId, int $officerId): array
    {
        $q = self::get($queueId);
        if ($q === null) {
            throw new RuntimeException('Queue record not found.');
        }
        if (!in_array($q['status'], ['called', 'serving'], true)) {
            throw new RuntimeException('Only called queues can be marked serving (status: ' . $q['status'] . ').');
        }
        return self::setStatus($q, 'serving', ['serving_at' => date('Y-m-d H:i:s')], $officerId, 'Marked serving');
    }

    /** Complete a queue; frees the counter. */
    public static function complete(int $queueId, int $officerId): array
    {
        $q = self::get($queueId);
        if ($q === null) {
            throw new RuntimeException('Queue record not found.');
        }
        if (!in_array($q['status'], ['serving', 'called', 'waiting'], true)) {
            throw new RuntimeException('Queue cannot be completed from status: ' . $q['status']);
        }
        $q = self::setStatus($q, 'completed', ['completed_at' => date('Y-m-d H:i:s')], $officerId, 'Service completed');
        if (!empty($q['counter_id'])) {
            Database::run('UPDATE counters SET status = "available" WHERE id = ?', [$q['counter_id']]);
        }
        return $q;
    }

    /** Skip a queue (applicant not present). */
    public static function skip(int $queueId, int $officerId): array
    {
        $q = self::get($queueId);
        if (!in_array($q['status'], ['waiting', 'called'], true)) {
            throw new RuntimeException('Queue cannot be skipped from status: ' . $q['status']);
        }
        return self::setStatus($q, 'skipped', [], $officerId, 'Applicant skipped');
    }

    /** Cancel a queue record with a reason. */
    public static function cancel(int $queueId, int $officerId, string $reason = ''): array
    {
        $q = self::get($queueId);
        if (in_array($q['status'], ['completed', 'cancelled'], true)) {
            throw new RuntimeException('Queue already closed: ' . $q['status']);
        }
        $q = self::setStatus($q, 'cancelled', [
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => mb_substr($reason, 0, 250),
        ], $officerId, 'Cancelled: ' . ($reason ?: 'no reason'));
        if (!empty($q['counter_id'])) {
            Database::run('UPDATE counters SET status = "available" WHERE id = ?', [$q['counter_id']]);
        }
        return $q;
    }

    /** Recall: put a serving queue back to called so it can be called again. */
    public static function recall(int $queueId, int $officerId): array
    {
        $q = self::get($queueId);
        if ($q['status'] !== 'serving') {
            throw new RuntimeException('Only a serving queue can be recalled.');
        }
        return self::setStatus($q, 'called', ['serving_at' => null], $officerId, 'Recalled current number');
    }

    /** Reset queue numbering: closes today's open queues into a reset state.
     *  Serials are date-scoped, so numbering for tomorrow starts at 001 automatically.
     *  This call archives still-open queues as cancelled. */
    public static function resetDay(int $officerId, string $reason = 'New operating day'): int
    {
        $date = self::today();
        $open = Database::select(
            'SELECT id, counter_id FROM queues WHERE operating_date = ? AND status IN ("waiting","called","serving")',
            [$date]
        );
        foreach ($open as $o) {
            try {
                self::cancel((int)$o['id'], $officerId, $reason . ' (day reset)');
            } catch (Throwable) {
                // ignore individual failures
            }
        }
        Security::log('queue_reset', count($open) . ' open queues closed at day reset');
        return count($open);
    }

    /** Status transition helper + log. */
    private static function setStatus(array $q, string $newStatus, array $extra, ?int $officerId, string $details): array
    {
        $fields = ['status' => $newStatus] + $extra;
        $sets   = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $sets[]   = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = (int)$q['id'];
        Database::run('UPDATE queues SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

        self::logAction((int)$q['id'], $newStatus, $officerId, $details);
        return Database::row('SELECT * FROM queues WHERE id = ?', [(int)$q['id']]);
    }

    public static function get(int $id): array
    {
        $q = Database::row('SELECT * FROM queues WHERE id = ?', [$id]);
        if ($q === null) {
            throw new RuntimeException('Queue record not found.');
        }
        return $q;
    }

    private static function logAction(int $queueId, string $action, ?int $userId, string $details = ''): void
    {
        try {
            Database::run(
                'INSERT INTO queue_logs (queue_id, user_id, action, details) VALUES (?,?,?,?)',
                [$queueId, $userId, $action, mb_substr($details, 0, 250)]
            );
        } catch (Throwable $e) {
            error_log('queue log failed: ' . $e->getMessage());
        }
    }

    /** Status counts for an operating date. */
    public static function statusCounts(?string $date = null): array
    {
        $date ??= self::today();
        $rows = Database::select(
            'SELECT status, COUNT(*) c FROM queues WHERE operating_date = ? GROUP BY status',
            [$date]
        );
        $out = ['waiting' => 0, 'called' => 0, 'serving' => 0, 'completed' => 0, 'skipped' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['c'];
        }
        $out['total'] = array_sum($out);
        $out['available_counters'] = (int)Database::value(
            'SELECT COUNT(*) FROM counters WHERE active = 1 AND status = "available"'
        );
        $out['active_counters'] = (int)Database::value('SELECT COUNT(*) FROM counters WHERE active = 1');
        return $out;
    }

    /** Estimated waiting time in minutes for a specific queue record. */
    public static function estimateWait(array $q): int
    {
        if (!in_array($q['status'], ['waiting', 'called', 'serving'], true)) {
            return 0;
        }
        $ahead = (int)Database::value(
            'SELECT COUNT(*) FROM queues q2
             WHERE q2.service_id = ? AND q2.status IN ("waiting","called","serving")
               AND q2.operating_date = ? AND (q2.id < ?)',
            [$q['service_id'], $q['operating_date'], $q['id']]
        );
        $est = (int)Database::value('SELECT est_minutes FROM immigration_services WHERE id = ?', [$q['service_id']]) ?: 15;
        return $ahead * $est;
    }

    /** Data for the public display screen for a service. */
    public static function displayData(int $serviceId): array
    {
        $date = self::today();
        $svc  = Database::row('SELECT * FROM immigration_services WHERE id = ?', [$serviceId]);
        $current = Database::row(
            'SELECT q.*, c.counter_number, s.name service_name
             FROM queues q
             LEFT JOIN counters c ON c.id = q.counter_id
             JOIN immigration_services s ON s.id = q.service_id
             WHERE q.service_id = ? AND q.operating_date = ? AND q.status = "serving"
             ORDER BY q.serving_at DESC, q.id DESC LIMIT 1',
            [$serviceId, $date]
        );
        $nexts = Database::select(
            'SELECT q.*, c.counter_number
             FROM queues q LEFT JOIN counters c ON c.id = q.counter_id
             WHERE q.service_id = ? AND q.operating_date = ? AND q.status = "called"
             ORDER BY q.called_at DESC, q.id DESC LIMIT 3',
            [$serviceId, $date]
        );
        $completed = Database::select(
            'SELECT q.* FROM queues q
             WHERE q.service_id = ? AND q.operating_date = ? AND q.status = "completed"
             ORDER BY q.completed_at DESC, q.id DESC LIMIT 5',
            [$serviceId, $date]
        );
        $waiting = (int)Database::value(
            'SELECT COUNT(*) FROM queues WHERE service_id = ? AND operating_date = ? AND status = "waiting"',
            [$serviceId, $date]
        );
        return [
            'service'     => $svc,
            'current'     => $current,
            'nexts'       => $nexts,
            'completed'   => $completed,
            'waiting'     => $waiting,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /** Latest display activity across all services (for auto-rotate screens). */
    public static function lastActivity(): ?array
    {
        $row = Database::row(
            'SELECT service_id FROM queues WHERE operating_date = ? AND status IN ("called","serving")
             ORDER BY GREATEST(called_at, serving_at, issued_at) DESC, id DESC LIMIT 1',
            [self::today()]
        );
        return $row ? ['service_id' => (int)$row['service_id']] : null;
    }
}
