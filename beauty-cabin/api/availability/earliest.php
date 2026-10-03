<?php
require_once __DIR__ . '/../../includes/booking_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed.'], 405);
}
try {
    $slot = earliest_slot($pdo, (int)($_GET['service_id'] ?? 0), (string)($_GET['date'] ?? ''));
    json_response([
        'available' => true,
        'date' => $slot['date'],
        'start_time' => $slot['start_time'],
        'end_time' => $slot['end_time'],
        'service' => ['id' => $slot['service']['id'], 'name' => $slot['service']['name'], 'price' => $slot['service']['price']],
        'worker' => ['id' => $slot['worker']['id'], 'name' => $slot['worker']['name']],
    ]);
} catch (InvalidArgumentException $ex) {
    json_response(['available' => false, 'error' => $ex->getMessage()], 422);
} catch (DomainException $ex) {
    json_response(['available' => false, 'error' => $ex->getMessage()], 409);
}
