<?php
require_once __DIR__ . '/../../includes/booking_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed.'], 405);
}
$user = current_user();
if (!$user || $user['role'] !== 'CUSTOMER') {
    json_response(['error' => 'You are not authorized to book an appointment.'], 403);
}
try {
    csrf_check();
    $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$serviceId || $serviceId < 1) {
        throw new InvalidArgumentException('Please select a service.');
    }
    $stmt = $pdo->prepare('SELECT id, user_id, name, email, mobile, address FROM customers WHERE user_id = ?');
    $stmt->execute([(int)$user['id']]);
    $customer = $stmt->fetch();
    if (!$customer) {
        json_response(['error' => 'Customer profile was not found.'], 404);
    }
    $created = create_appointment(
        $pdo,
        $customer + ['user_id' => (int)$user['id']],
        (int)$serviceId,
        (string)($_POST['appointment_date'] ?? ''),
        (string)($_POST['appointment_time'] ?? '')
    );
    json_response([
        'success' => true,
        'message' => 'Appointment successfully booked.',
        'appointment' => [
            'number' => $created['appointment_number'],
            'service' => $created['service']['name'],
            'price' => $created['service']['price'],
            'date' => $created['date'],
            'start_time' => $created['start_time'],
            'end_time' => $created['end_time'],
            'worker' => $created['worker']['name'],
        ],
    ], 201);
} catch (InvalidArgumentException $ex) {
    json_response(['error' => $ex->getMessage()], 422);
} catch (DomainException $ex) {
    json_response(['error' => $ex->getMessage()], 409);
} catch (Throwable $ex) {
    error_log('Appointment creation failed: ' . $ex->getMessage());
    json_response(['error' => 'The booking could not be saved. Please try again.'], 500);
}
