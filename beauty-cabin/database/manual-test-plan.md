# The Beauty Cabin Test Cases

This is the acceptance test suite for the PHP application. Run cases against a disposable development database; do not use real Aadhaar numbers or customer data. Mark each case Pass/Fail and record the test date and tester.

## Test Setup

1. Import `schema.sql`, then `seed.sql` into MySQL 8.0.16 or newer.
2. Configure PHP `pdo_mysql`, database connection environment variables, and start the app.
3. Sign in as owner using `useradmin` / `12345678`; change the password before sharing the test environment.
4. Create test-only accounts and workers. Give at least two active workers the same service for assignment and overlap tests.
5. Use future dates for normal booking cases and create isolated test data for each case.

## Accounts and Roles

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| AUTH-01 | Customer registration | Submit a valid name, unique username/email, mobile, address, and password. | One `CUSTOMER` user and one linked customer profile are created; password is hashed. |
| AUTH-02 | Duplicate registration | Repeat registration with an existing username, then with an existing email. | Both submissions are rejected; no partial user/profile records are added. |
| AUTH-03 | Customer login | Sign in once by username and once by email; sign out after each. | Customer is routed to their dashboard; session ID changes at login; sign-out clears authentication. |
| AUTH-04 | Staff login routing | Sign in as seeded owner, manager, and worker. | Each account is routed to its matching role dashboard. |
| AUTH-05 | Invalid/inactive login | Try an incorrect password, then deactivate a test account and try its correct password. | Both attempts are rejected without revealing which credential was wrong. |
| AUTH-06 | Session timeout | Sign in, leave the session inactive for more than 30 minutes, then visit a protected route. | Authentication is cleared and the user is asked to sign in again. |
| AUTH-07 | Change password | Change a role account password, then try the old and new passwords. | Old password fails; new password succeeds; stored value remains a password hash. |
| AUTH-08 | One owner/manager | Attempt to insert or create a second owner and a second manager. | Database uniqueness/business rule rejects the duplicate role account. |
| AUTH-09 | Role isolation | As customer, worker, and manager, request pages outside each role's permissions. | Server returns HTTP 403; protected data is not rendered. Manager cannot open owner settings/manager account. |
| AUTH-10 | CSRF protection | Submit a state-changing form without a token and with an invalid token. | Request is rejected with HTTP 400 and no data changes. |

## Services and Salon Settings

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| SVC-01 | Seed data | Import seed and inspect services, owner, and working hours. | Exactly one owner; six named services, configured prices/durations, seven weekdays, and contact settings exist. |
| SVC-02 | Service CRUD | As owner and manager, add a service, edit its price/duration/description, and deactivate/reactivate it. | Changes persist; public pages show active services and booking uses current DB values. |
| SVC-03 | Service deletion safety | Try deleting a service with appointments or worker qualifications, then an unreferenced service. | Referenced service is preserved/deactivated; unreferenced service may be deleted. |
| SET-01 | Salon contact details | As owner, update salon name, address, phone, and email. | Homepage and contact page display the saved values; manager cannot edit settings. |
| SET-02 | Opening hours | Set normal, Saturday, Sunday, and closed-day hours; submit invalid/reversed times. | Valid values save; invalid values are rejected; display and booking validation follow saved hours. |

## Worker and Manager Management

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| STAFF-01 | Create worker | Add worker with unique login, test Aadhaar, joining date, status, password, and multiple active services. | Worker account/profile and qualifications save together; generated codes follow `W001`, `W002`, etc. |
| STAFF-02 | Edit worker | Change profile and service qualifications while leaving password and Aadhaar blank. | Profile/qualifications update; existing password and Aadhaar remain unchanged. |
| STAFF-03 | Aadhaar privacy | View owner/manager worker and manager screens and edit forms. | Only masked Aadhaar is displayed; full number is not prefilled. |
| STAFF-04 | Worker deletion | Delete a worker with appointment history. | Login is removed, qualifications cascade, prior appointments remain with `worker_id` cleared. |
| STAFF-05 | Worker qualification | Deactivate a worker or service and attempt automatic/manual assignment. | Inactive or unqualified worker/service is never assigned. |
| STAFF-06 | Manager lifecycle | Owner creates, edits, and deletes manager; attempt creating a second manager. | At most one manager exists; only owner can manage that account. |

## Booking and Availability

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| BOOK-01 | Customer data prefill | Sign in as customer and open booking. | Name, email, mobile, and address come from the logged-in customer's profile and cannot be changed through hidden fields. |
| BOOK-02 | Valid automatic booking | Choose an active service, open future date, and available time. | Booking is created with computed service end time, a qualified available worker, unique `APT-YYYYMMDD-NNNN` number, `PENDING` status, and initial history row. |
| BOOK-03 | Invalid service/date/time | Submit inactive/unknown service, malformed/past date, malformed time, or a time in the past today. | Request is rejected and no appointment is created. |
| BOOK-04 | Salon hours boundary | Book a slot starting at opening; book a duration ending exactly at closing; try a slot ending after closing or on a closed day. | In-hours boundary slots are accepted; outside/closed-day slots are rejected. |
| BOOK-05 | Worker qualification | Assign a worker only to a different service and request this service. | Worker is excluded; request succeeds only if another qualified active worker is available. |
| BOOK-06 | Overlap rejection | Existing appointment is 10:00–11:00; request 10:30–11:30 for that worker/service. | Overlap is detected using `new_start < existing_end AND new_end > existing_start`; conflicting worker is not assigned. |
| BOOK-07 | Touching boundary | Existing appointment is 10:00–11:00; request 11:00–12:00. | The intervals do not conflict; a qualified worker may be assigned. |
| BOOK-08 | No worker available | Make all qualified workers unavailable for the requested duration. | User receives a friendly unavailable message; no appointment/history row is created. |
| BOOK-09 | Earliest available slot | Request earliest availability for a selected date/service with busy and free workers. | First chronological 30-minute candidate that fits the complete service duration is returned with an eligible worker; selecting it fills the form. |
| BOOK-10 | Concurrent booking | Submit two simultaneous requests for the same worker/date/overlapping interval. | At most one conflicting assignment commits; no overlapping active appointments are stored. |
| BOOK-11 | Database transaction rollback | Force an appointment/history insert failure in a disposable DB. | No partial appointment remains; transaction rolls back and a safe error is returned. |
| BOOK-12 | Price/duration source | Change service price and duration in management, then book it. | Booking end time reflects stored duration; displayed price comes from the current service row, not hardcoded booking logic. |

## Dashboards and Appointment Lifecycle

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| VIEW-01 | Customer isolation | Create appointments for two customers; view each customer dashboard/history. | Each customer sees only their own rows, worker, status, service, time, and price. |
| VIEW-02 | Customer cancellation | Cancel own future pending and confirmed bookings; try another customer's booking, past booking, and completed booking. | Only eligible owned future bookings can be cancelled; actor and status change are recorded in history. |
| VIEW-03 | Manager appointment filters | Filter by date, worker, service, and status separately and together. | Results match every supplied valid filter. |
| VIEW-04 | Owner appointment access | As owner, view/filter/change status/reassign and delete a test appointment. | Owner can perform manager operations; deletion removes linked history as defined by schema. |
| VIEW-05 | Worker isolation | Create assignments for two workers; view worker dashboard and appointment history as each worker. | Each worker sees only their assigned appointments and required customer contact details. |
| VIEW-06 | Worker completion authorization | Try completing another worker's booking, own pending booking, and own future confirmed booking. | All are rejected; no history changes occur. |
| VIEW-07 | Worker completion success | Mark own ended confirmed booking completed. | Status becomes `COMPLETED`; a history row records the worker user as actor. |
| VIEW-08 | Manager/owner reassignment | Assign/reassign to active qualified worker with no overlap; try inactive, unqualified, or conflicting worker. | Valid assignment succeeds; invalid assignment is rejected without altering the original worker. |
| VIEW-09 | Status history | Confirm, cancel, complete, and mark no-show using permitted transitions. | Every successful change adds exactly one history row with old/new status and actor; disallowed transitions fail. |
| VIEW-10 | Schedule de-duplication | Give a worker multiple service qualifications and several appointments; view manager/owner schedule. | Each appointment appears once under its worker, with correct status/time/customer/service. |

## Security and Deployment Checks

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| SEC-01 | SQL injection input | Submit SQL syntax in login, registration, filters, service fields, and appointment IDs. | Input is treated as data; no unauthorized rows/actions or SQL errors. |
| SEC-02 | XSS output | Save HTML/script-like text in names/descriptions/messages, then view it. | Text is escaped and does not execute in the browser. |
| SEC-03 | Direct role route | Request each protected URL without a session and with the wrong role. | Anonymous user is redirected to sign-in; authenticated wrong role receives HTTP 403. |
| SEC-04 | Secret exposure | Inspect rendered pages, logs returned to clients, and repository config. | No passwords, full Aadhaar values, or raw SQL/PDO errors are exposed. |
| OPS-01 | Public smoke test | With DB configured, request `/`, `/services.php`, `/contact.php`, `/login.php`, and `/register.php`. | Each route returns HTTP 200 with expected page content and no PHP warning/fatal error. |
| OPS-02 | Database integrity | Query for duplicate active worker overlaps and orphaned foreign keys after tests. | No overlapping active worker appointments or orphaned relationships remain. |

A suite run is **blocked** until the PHP runtime reports `pdo_mysql` in `PDO::getAvailableDrivers()` and the configured MySQL instance accepts the application credentials. Record blocked cases as **Blocked**, not **Pass**.
