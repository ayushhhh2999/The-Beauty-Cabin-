# The Beauty Cabin

The Beauty Cabin is a PHP and MySQL salon booking and management application. It includes customer registration and booking, role-based dashboards, automatic qualified-worker assignment, schedule conflict checks, appointment history, salon hours, and service management.

## Stack and requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, and sessions enabled
- MySQL 8.0.16+ (InnoDB, generated columns, and enforced `CHECK` constraints)
- Apache through XAMPP/WAMP/LAMP, or PHP's built-in development server
- Bootstrap 5.3 and Google Fonts are loaded from CDNs

No PHP framework or JavaScript application framework is used.

## Install with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel. If MySQL will not start, check for another MySQL service already using port 3306.
2. Put `beauty-cabin` in XAMPP's `htdocs` folder, or use the PHP built-in server from the workspace root as shown below.
3. Open `http://localhost/phpmyadmin/`. If prompted, use the MySQL account configured on your machine. A fresh XAMPP installation commonly uses `root` with a blank password; an installed Windows `MySQL80` service may have a separate root password.
4. In phpMyAdmin, use **Import** to import `database/schema.sql`, then import `database/seed.sql`. The schema creates and selects `beauty_cabin_v2`.
5. Configure the connection environment variables if your MySQL host, database, username, or password differs from the defaults.
6. Visit `http://localhost/beauty-cabin/`.

For a phpMyAdmin `401 Unauthorized`, verify Apache is running and use the port configured for Apache (commonly 80 or 8080). A phpMyAdmin login prompt is separate from the salon's application login. Do not assume credentials for an existing `MySQL80` Windows service are the XAMPP defaults.

## Run from PowerShell

From the workspace folder containing `beauty-cabin`:

```powershell
$env:BEAUTY_CABIN_DB_HOST = '127.0.0.1'
$env:BEAUTY_CABIN_DB_NAME = 'beauty_cabin_v2'
$env:BEAUTY_CABIN_DB_USER = 'root'
$env:BEAUTY_CABIN_DB_PASS = ''
php -S localhost:8000 -t .\beauty-cabin
```

Open `http://localhost:8000/`. Change the database password environment variable to the password configured for your MySQL account. Do not commit database credentials. In PowerShell, these variables apply to the current terminal session.

For the WinGet PHP package used in this workspace, PHP may not load an INI file by default. Enable the packaged PDO-MySQL driver explicitly:

```powershell
$phpExt = Join-Path (Split-Path (Get-Command php).Source) 'ext'
php -d extension_dir=$phpExt -d extension=php_pdo_mysql.dll -S localhost:8000 -t .\beauty-cabin
```

If PHP is already configured with `pdo_mysql` (for example, XAMPP PHP), use `php -S localhost:8000` from inside the project folder; the application detects its base path automatically.

## Default owner

- Username: `useradmin`
- Password: `12345678`
- Sign-in page: `/login.php`

The seed stores a PHP `password_hash()` bcrypt hash, never the plaintext password. Change this default before exposing the application to a network. Generate a new hash with `php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`, then update the `users.password` value for `username = 'useradmin'` in `beauty_cabin_v2`. Keep the generated hash private.

## Roles

- **OWNER**: dashboards, appointments, customers, manager account, workers, services, and salon settings.
- **MANAGER**: dashboards, appointments, customers, workers, services, and schedules. Cannot access owner configuration or the manager-account page.
- **WORKER**: sees only appointments assigned to their worker profile and may complete their own ended, confirmed appointments.
- **CUSTOMER**: registers, books, views their own appointments, and may cancel eligible future bookings.

All sign-ins use the shared `/login.php` page. Accounts are routed to their role dashboard after authentication. Protected routes check roles server-side and reject unauthorized roles with HTTP 403.

## How booking works

A customer chooses an active service, date, and half-hour start time. The server loads the service duration from MySQL, validates the date against `working_hours`, then loads active workers qualified through `worker_services` and their non-cancelled appointments for that date. It checks interval overlap using `new_start < existing_end AND new_end > existing_start` and assigns the first available worker. The customer does not select a worker.

The earliest-slot endpoint tests candidate starts chronologically in 30-minute steps and includes the full service duration before accepting a slot. Booking and manager/owner reassignments share a MySQL named lock per date; appointment insertion and initial status-history creation use a transaction. An unavailable or conflicting request is rejected without creating an appointment.

## Main routes

- Public: `/`, `/services.php`, `/contact.php`, `/register.php`, `/login.php`
- Customer: `/customer/dashboard.php`, `/booking.php`, `/appointments.php`, `/account.php`
- Worker: `/worker/dashboard.php`, `/worker/appointments.php`
- Manager: `/manager/dashboard.php`, `/manager/appointments.php`, `/manager/workers.php`, `/manager/services.php`, `/manager/customers.php`, `/manager/schedules.php`
- Owner: `/owner/dashboard.php`, `/owner/appointments.php`, `/owner/schedules.php`, `/owner/workers.php`, `/owner/services.php`, `/owner/customers.php`, `/owner/manager.php`, `/owner/settings.php`

Legacy `/admin/*` routes redirect authenticated owner/manager accounts to the equivalent role pages. Authentication, CSRF, output-escaping, and database helpers are centralized in `includes/`.

## API

Availability endpoints return JSON and do not create records:

- `GET /api/availability/check.php?service_id=1&date=YYYY-MM-DD&time=HH:MM`
- `GET /api/availability/earliest.php?service_id=1&date=YYYY-MM-DD`

Appointment creation requires a logged-in customer and a valid session CSRF token:

- `POST /api/appointments/create.php` with `service_id`, `appointment_date`, `appointment_time`, and `csrf_token`

The booking page uses these endpoints for availability, earliest-slot selection, and confirmation. Management changes use authenticated, CSRF-protected POST forms.

## Database

- `database/schema.sql` creates `beauty_cabin_v2` and tables for users, customers, the single manager profile, workers, services, worker qualifications, appointments, status history, working hours, and salon settings.
- `database/seed.sql` creates the one default owner, six service records, default weekly hours, and placeholder salon contact settings.

Import the schema before the seed. The legacy `database/beauty_cabin.sql` belongs to the previous application design and is not used by the current code.

## Manual verification

See [`database/manual-test-plan.md`](database/manual-test-plan.md) for the functional scenarios covering authentication, role isolation, service and worker management, automatic assignment, conflicts, earliest availability, and status changes.

## Security and deployment notes

PDO prepared statements are used for user input; passwords are hashed and verified with PHP password APIs; login regenerates the session ID; sessions have HTTP-only/SameSite cookies and an inactivity timeout; forms use CSRF tokens; output is escaped; role checks happen on the server; and Aadhaar values are masked in staff listings. Use HTTPS, change the seeded owner password, set a dedicated least-privilege MySQL account, protect PHP error logs, and back up the database before production use. Do not store real Aadhaar data unless your privacy and retention requirements are addressed.

The contact page validates and acknowledges submissions but does not deliver email. Configure a mail transport before relying on it for customer inquiries.

## Troubleshooting

- **Database connection unavailable:** confirm the chosen MySQL instance is running, the database `beauty_cabin_v2` exists, and `BEAUTY_CABIN_DB_*` values match that instance.
- **Access denied for `root`:** use the credentials configured for the running MySQL service. XAMPP's default user is not necessarily the same as the Windows `MySQL80` service account.
- **phpMyAdmin 401 or unavailable:** start Apache, check its port in XAMPP, and open `/phpmyadmin/` on that port. This is separate from the PHP development server on port 8000.
- **No worker available:** create an active worker, assign the selected active service to that worker, and ensure the chosen time is inside that date's salon hours and does not overlap another booking.
- **No earliest time:** verify active workers qualify for the service and the salon is open on the selected weekday.
- **Role page returns 403:** sign in with an account of the role required for that route.
