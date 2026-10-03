<?php
require_once __DIR__ . '/../../includes/booking_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed.'], 405);
}
try {
    $slot = slot_for_service($pdo, (int)($_GET['service_id'] ?? 0), (string)($_GET['date'] ?? ''), (string)($_GET['time'] ?? ''));
    json_response([
        'available' => true,
        'date' => $slot['date'],
        'start_time' => $slot['start_time'],
        'end_time' => $slot['end_time'],
        'worker' => ['id' => $slot['worker']['id'], 'name' => $slot['worker']['name']],
    ]);
} catch (InvalidArgumentException $ex) {
    json_response(['available' => false, 'error' => $ex->getMessage()], 422);
} catch (DomainException $ex) {
    json_response(['available' => false, 'error' => $ex->getMessage()], 409);
}
