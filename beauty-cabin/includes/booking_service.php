<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function booking_date(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < date('Y-m-d')) {
        throw new InvalidArgumentException('Please choose a valid date that is not in the past.');
    }
    return $date;
}

function active_service(PDO $pdo, int $serviceId): array
{
    $stmt = $pdo->prepare("SELECT id, name, price, duration_minutes FROM services WHERE id = ? AND status = 'ACTIVE'");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();
    if (!$service) {
        throw new InvalidArgumentException('Please select an active service.');
    }
    return $service;
}

function service_day_schedule(PDO $pdo, DateTimeImmutable $date): array
{
    $weekday = (int)$date->format('N') - 1;
    $stmt = $pdo->prepare('SELECT opens_at, closes_at, is_closed FROM working_hours WHERE weekday = ?');
    $stmt->execute([$weekday]);
    $hours = $stmt->fetch();
    if (!$hours || (bool)$hours['is_closed']) {
        throw new InvalidArgumentException('The salon is closed on the selected date.');
    }
    return $hours;
}

function qualified_workers(PDO $pdo, int $serviceId, string $date): array
{
    $stmt = $pdo->prepare("\n        SELECT w.id, w.worker_code, w.name, a.start_time, a.end_time\n        FROM workers w\n        JOIN users u ON u.id = w.user_id AND u.status = 'ACTIVE'\n        JOIN worker_services ws ON ws.worker_id = w.id AND ws.service_id = ?\n        LEFT JOIN appointments a ON a.worker_id = w.id\n            AND a.appointment_date = ?\n            AND a.status NOT IN ('CANCELLED', 'NO_SHOW')\n        WHERE w.status = 'ACTIVE'\n        ORDER BY w.id, a.start_time");
    $stmt->execute([$serviceId, $date]);
    $workers = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        if (!isset($workers[$id])) {
            $workers[$id] = [
                'id' => $id,
                'worker_code' => $row['worker_code'],
                'name' => $row['name'],
                'appointments' => [],
            ];
        }
        if ($row['start_time'] !== null) {
            $workers[$id]['appointments'][] = [
                'start' => $row['start_time'],
                'end' => $row['end_time'],
            ];
        }
    }
    return array_values($workers);
}

function worker_is_free(array $worker, string $start, string $end): bool
{
    foreach ($worker['appointments'] as $appointment) {
        if ($start < $appointment['end'] && $end > $appointment['start']) {
            return false;
        }
    }
    return true;
}

function slot_for_service(PDO $pdo, int $serviceId, string $dateValue, string $timeValue): array
{
    $date = booking_date($dateValue);
    $service = active_service($pdo, $serviceId);
    $hours = service_day_schedule($pdo, $date);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeValue)) {
        throw new InvalidArgumentException('Please select a valid appointment time.');
    }
    $start = $timeValue . ':00';
    $startDateTime = new DateTimeImmutable($dateValue . ' ' . $start);
    $openingDateTime = new DateTimeImmutable($dateValue . ' ' . $hours['opens_at']);
    $minutesAfterOpening = intdiv($startDateTime->getTimestamp() - $openingDateTime->getTimestamp(), 60);
    if ($minutesAfterOpening < 0 || $minutesAfterOpening % 30 !== 0) {
        throw new InvalidArgumentException('Appointment times must start on a 30-minute interval from opening.');
    }
    $endDateTime = $startDateTime->modify('+' . (int)$service['duration_minutes'] . ' minutes');
    $end = $endDateTime->format('H:i:s');
    if ($start < $hours['opens_at'] || $end > $hours['closes_at']) {
        throw new InvalidArgumentException('The appointment duration falls outside salon working hours.');
    }
    if ($dateValue === date('Y-m-d') && $startDateTime <= new DateTimeImmutable()) {
        throw new InvalidArgumentException('That time has already passed today.');
    }

    foreach (qualified_workers($pdo, $serviceId, $dateValue) as $worker) {
        if (worker_is_free($worker, $start, $end)) {
            return [
                'service' => $service,
                'worker' => $worker,
                'date' => $dateValue,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }
    }
    throw new DomainException('No qualified worker is available at the selected time.');
}

function earliest_slot(PDO $pdo, int $serviceId, string $dateValue): array
{
    $date = booking_date($dateValue);
    $service = active_service($pdo, $serviceId);
    $hours = service_day_schedule($pdo, $date);
    $workers = qualified_workers($pdo, $serviceId, $dateValue);
    $candidate = new DateTimeImmutable($dateValue . ' ' . $hours['opens_at']);
    $closing = new DateTimeImmutable($dateValue . ' ' . $hours['closes_at']);
    $duration = (int)$service['duration_minutes'];

    while ($candidate->modify('+' . $duration . ' minutes') <= $closing) {
        if ($dateValue !== date('Y-m-d') || $candidate > new DateTimeImmutable()) {
            $start = $candidate->format('H:i:s');
            $end = $candidate->modify('+' . $duration . ' minutes')->format('H:i:s');
            foreach ($workers as $worker) {
                if (worker_is_free($worker, $start, $end)) {
                    return [
                        'service' => $service,
                        'worker' => $worker,
                        'date' => $dateValue,
                        'start_time' => $start,
                        'end_time' => $end,
                    ];
                }
            }
        }
        $candidate = $candidate->modify('+30 minutes');
    }
    throw new DomainException('No worker is available for this service on the selected date.');
}

function create_appointment(PDO $pdo, array $customer, int $serviceId, string $dateValue, string $timeValue): array
{
    $lockName = 'beautycabin-' . str_replace('-', '', $dateValue);
    $pdo->beginTransaction();
    $locked = false;
    try {
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$lockName]);
        $locked = (int)$lock->fetchColumn() === 1;
        if (!$locked) {
            throw new RuntimeException('Booking is busy. Please try again.');
        }

        $slot = slot_for_service($pdo, $serviceId, $dateValue, $timeValue);
        $sequenceQuery = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_number, '-', -1) AS UNSIGNED)), 0) + 1 FROM appointments WHERE appointment_number LIKE ?");
        $sequenceQuery->execute(['APT-' . str_replace('-', '', $dateValue) . '-%']);
        $sequence = (int)$sequenceQuery->fetchColumn();
        $appointmentNumber = 'APT-' . str_replace('-', '', $dateValue) . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("\n            INSERT INTO appointments (appointment_number, customer_id, worker_id, service_id,\n                customer_name, customer_email, customer_mobile, customer_address,\n                appointment_date, start_time, end_time, status)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->execute([
            $appointmentNumber,
            (int)$customer['id'],
            (int)$slot['worker']['id'],
            $serviceId,
            $customer['name'],
            $customer['email'],
            $customer['mobile'],
            $customer['address'],
            $dateValue,
            $slot['start_time'],
            $slot['end_time'],
        ]);
        $appointmentId = (int)$pdo->lastInsertId();
        $history = $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)');
        $history->execute([$appointmentId, 'PENDING', (int)$customer['user_id']]);
        $pdo->commit();

        return $slot + [
            'id' => $appointmentId,
            'appointment_number' => $appointmentNumber,
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    } finally {
        if ($locked) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
