# PHP File Guide

This guide explains the PHP backend in simple terms.

The backend is split into four kinds of files:

- config
- auth endpoints
- API endpoints
- helper/utility files
- cron automation

The project uses plain PHP with PDO and JSON responses.

## Common Backend Pattern

Most API files follow this same shape:

```php
session_start();
header('Content-Type: application/json');
require_once '../config/config.php';

// validate session / input
// run PDO query
// echo json_encode([...]);
```

That means every endpoint usually does four things:

1. start session
2. connect to DB
3. validate user and inputs
4. return JSON

## 1. Config

### `backend/config/config.php`

Purpose:

- central app bootstrap for backend files
- creates the PDO connection
- defines timezone
- stores SMTP and Twilio configuration

Key snippet:

```php
date_default_timezone_set('Asia/Kathmandu');
$pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
$pdo->exec("SET time_zone = '+05:45'");
```

Why it matters:

- almost every PHP file depends on this
- keeps PHP time and MySQL time aligned

## 2. Authentication Files

### `backend/auth/login.php`

Purpose:

- authenticates users by phone and password
- starts the session
- returns role and user identity to the frontend

Main work:

- finds user by phone
- verifies password hash
- saves session values

### `backend/auth/signup.php`

Purpose:

- creates new user accounts

Main work:

- validates role-specific fields
- generates user IDs like admin/worker/user IDs
- hashes password
- inserts into `users`
- links patient with doctor if doctor code was provided

Snippet:

```php
$stmt = $pdo->prepare("INSERT INTO users (id, role, name, phone, email, password_hash, plain_password, post, relation, worker_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
```

### `backend/auth/forgot_password.php`

Purpose:

- starts password reset
- creates OTP/token
- emails OTP using PHPMailer

Main work:

- finds user by phone
- stores `reset_token` and expiry
- sends email to registered email address

### `backend/auth/reset_password.php`

Purpose:

- completes reset with phone + OTP + new password

Main work:

- checks token and expiry
- updates `password_hash`
- clears reset fields

## 3. Core Scheduling and Reminder Helpers

### `backend/api/schedule_utils.php`

Purpose:

- this is the heart of the medicine scheduling system

What it handles:

- schema auto-fixes for `medicines` and `intake_logs`
- dose count from frequency
- custom patient times
- course vs ongoing medicine
- create future schedule rows
- resync future rows after time changes
- cleanup orphan pending logs

Key snippet:

```php
function medtracker_dose_count_from_frequency(string $frequency): int
{
    if (strpos($normalized, '3x') !== false) return 3;
    if (strpos($normalized, '2x') !== false) return 2;
    return 1;
}
```

Why it matters:

- almost all medicine behavior depends on this file

### `backend/api/reminder_settings_utils.php`

Purpose:

- stores and reads admin reminder rules for `1x`, `2x`, and `3x`

What it handles:

- default reminder settings
- creates `reminder_settings` table
- maps frequency to scenario key
- caches settings
- saves changed settings

Key snippet:

```php
return [
    'scenario_key' => '1x_daily',
    'upcoming_minutes' => 5,
    'missed_minutes' => 15,
];
```

### `backend/api/notification_utils.php`

Purpose:

- notification engine for email and SMS

What it handles:

- `notification_logs` table
- duplicate protection using `event_key`
- email sending via PHPMailer
- SMS sending via Twilio
- phone normalization to E.164
- system log inserts

Key snippet:

```php
if ($eventKey && medtracker_notification_already_logged($pdo, $eventKey, $channel)) {
    $results[$channel] = ['status' => 'SKIPPED', 'message' => 'Duplicate notification prevented.'];
}
```

### `backend/api/assignment_utils.php`

Purpose:

- caregiver and health-worker linking helper

What it handles:

- ensures `worker_code`
- creates `caregiver_patient`
- generates doctor share code
- links patient to health worker
- checks whether worker can access a patient

Key snippet:

```php
function medtracker_generate_worker_code(string $workerId): string
{
    return 'HW-' . preg_replace('/[^A-Za-z0-9]/', '', strtoupper($workerId));
}
```

### `backend/api/worker_prescription_utils.php`

Purpose:

- helper for worker-owned prescription lookup

What it does:

- fetches a medicine only if the current worker is allowed to manage it

### `backend/api/water_utils.php`

Purpose:

- helper functions for hydration tracking

What it handles:

- water schema setup
- daily goal value
- patient water map over a date range

### `backend/api/adherence_utils.php`

Purpose:

- helper functions for adherence analytics

What it handles:

- logging overuse events
- reading overuse logs
- building reusable date series for charts

### `backend/api/audit_utils.php`

Purpose:

- audit trail helper

What it handles:

- creates `audit_logs`
- records who changed what and when

Key snippet:

```php
function medtracker_log_audit_event(
    PDO $pdo,
    string $actorUserId,
    string $actorRole,
    string $actionKey,
    string $entityType,
    ?string $entityId = null,
    ?string $targetUserId = null,
    array $details = []
): void
```

This is the base of admin/system activity history.

## 4. Main Data APIs

### `backend/api/get_schedule.php`

Purpose:

- builds today’s medicine schedule payload for patient, worker, or admin views

How it works:

- ensures schedule schema exists
- creates missing schedule rows
- joins `medicines`, `intake_logs`, and `users`
- returns today’s effective schedule rows

Key snippet:

```php
COALESCE(l.snooze_until, l.scheduled_time) AS effective_time
```

Important idea:

- reminder time can shift if the user snoozed the dose

### `backend/api/get_patient_adherence.php`

Purpose:

- patient analytics API

What it returns:

- adherence rate
- late doses
- low stock alerts
- overuse alerts
- water summaries
- weekly overview
- late-by-medicine
- missed reasons
- refill adherence
- wellness summary

Key snippet:

```php
if ($minutesLate <= $lateGraceMinutes) {
    continue;
}
```

This is how late-dose tracking is calculated.

### `backend/api/get_admin_overview.php`

Purpose:

- admin analytics API

What it returns:

- user counts by role
- intake usage stats
- low stock total
- system logs
- notification delivery chart
- most missed medicines
- audit feed
- active users and prescriber activity

### `backend/api/get_admin_users.php`

Purpose:

- returns users for admin management table

What it does:

- admin-only
- returns rows from `users`

### `backend/api/get_account_context.php`

Purpose:

- returns account/profile details for the current session

What it does:

- reads session user
- fetches DB record
- may include assigned worker context

### `backend/api/get_patients.php`

Purpose:

- returns patient list for health worker pages

What it does:

- typically filters to patients linked to the worker
- supports patient selection in doctor portal

### `backend/api/get_worker_prescription.php`

Purpose:

- loads one worker-created prescription for edit mode

What it does:

- validates worker session
- checks worker ownership/access
- returns medicine row for form prefill

### `backend/api/get_reminder_settings.php`

Purpose:

- admin-only read endpoint for reminder rule panel

What it does:

- returns current `reminder_settings` rows

## 5. Write / Action APIs

### `backend/api/add_prescription.php`

Purpose:

- creates a new prescription from health worker or patient side

What it does:

- validates fields
- checks assignment rules
- inserts into `medicines`
- may notify patient
- logs audit event

Key snippet:

```php
INSERT INTO medicines (
    patient_id, prescriber_id, name, dosage, type, quantity, frequency,
    custom_times_json, custom_times_effective_at, treatment_mode,
    start_date, duration_days, end_date, instructions
)
```

### `backend/api/update_worker_prescription.php`

Purpose:

- edits an existing worker-owned prescription

What it does:

- validates worker access
- updates medicine plan fields
- preserves compatible patient times if possible
- resyncs future schedule logs

### `backend/api/delete_worker_prescription.php`

Purpose:

- deletes a worker-owned prescription

What it does:

- verifies worker ownership
- deletes the medicine row
- cascades intake logs through foreign keys

### `backend/api/update_prescription_state.php`

Purpose:

- worker can pause, resume, or stop a prescription

What it changes:

- `prescription_status`
- `paused_at`
- `stopped_at`
- `stop_reason`

Why it matters:

- stopped or paused medicines should not continue generating reminders

### `backend/api/update_patient_schedule_times.php`

Purpose:

- patient time editor API

What it does:

- validates medicine belongs to patient
- saves `custom_times_json`
- saves `custom_times_effective_at`
- rebuilds future pending rows
- logs audit entry

Key snippet:

```php
$updateStmt = $pdo->prepare(
    "UPDATE medicines
     SET custom_times_json = ?, custom_times_effective_at = ?
     WHERE id = ? AND patient_id = ?"
);
```

### `backend/api/update_dosage.php`

Purpose:

- patient-side dosage edit for self-added medicines

Important rule:

- doctor-assigned prescriptions cannot be edited here

### `backend/api/update_stock.php`

Purpose:

- refill stock count

What it does:

- validates medicine and stock amount
- checks access rules
- blocks stopped medicines
- increments quantity
- logs audit event

### `backend/api/update_reminder_settings.php`

Purpose:

- saves admin reminder panel changes

What it does:

- validates admin session
- writes updated values to `reminder_settings`
- clears cache
- logs audit event

### `backend/api/log_intake.php`

Purpose:

- marks a dose as taken or skipped

What it handles:

- taken time
- skip reason
- late logging cases
- may log overuse conditions

### `backend/api/snooze_intake.php`

Purpose:

- snoozes a pending dose

What it changes:

- `snooze_until`
- `snooze_count`

Why it matters:

- reminder runner uses `effective_time`, not only original scheduled time

### `backend/api/log_water_intake.php`

Purpose:

- updates daily water intake

What it does:

- add water amount
- reset daily intake
- refresh hydration totals

### `backend/api/link_caregiver.php`

Purpose:

- patient links to health worker using doctor code

What it does:

- validates worker code
- inserts into `caregiver_patient`

### `backend/api/unlink_caregiver.php`

Purpose:

- removes patient-health worker link

What it does:

- supports patient or worker unlink actions
- deletes row from `caregiver_patient`

### `backend/api/delete_medicine.php`

Purpose:

- admin medicine deletion endpoint

What it does:

- loads medicine context
- deletes medicine
- writes audit trail

### `backend/api/admin_delete_user.php`

Purpose:

- admin deletes a user account

Important safeguards:

- cannot delete own admin account
- records audit event

### `backend/api/admin_reset_user_password.php`

Purpose:

- admin resets another user’s password

What it does:

- updates password hash
- clears reset token
- can notify user
- logs audit entry

## 6. Cron / Automation

### `backend/cron/run_reminders.php`

Purpose:

- the automation runner that Windows Task Scheduler calls

What it does:

1. opens lock file so two runs do not overlap
2. ensures schema
3. creates missing schedule rows
4. finds pending doses near reminder windows
5. sends upcoming, due-now, and missed alerts
6. auto-skips only when missed window is reached

Key snippet:

```php
$candidateStmt = $pdo->prepare(
    "SELECT ... COALESCE(l.snooze_until, l.scheduled_time) AS effective_time
     FROM intake_logs l
     INNER JOIN medicines m ON m.id = l.medicine_id
     WHERE l.status = 'Pending'"
);
```

Why it matters:

- this is the file that makes reminders automatic

## 7. File-by-File Summary Table

| File | Main Job |
|---|---|
| `backend/config/config.php` | DB, timezone, SMTP, Twilio bootstrap |
| `backend/auth/login.php` | user login |
| `backend/auth/signup.php` | account creation |
| `backend/auth/forgot_password.php` | start reset flow |
| `backend/auth/reset_password.php` | finish reset flow |
| `backend/api/schedule_utils.php` | schedule engine |
| `backend/api/reminder_settings_utils.php` | admin reminder rule helper |
| `backend/api/notification_utils.php` | email/SMS sending and logs |
| `backend/api/assignment_utils.php` | doctor-patient linking |
| `backend/api/worker_prescription_utils.php` | worker prescription access checks |
| `backend/api/water_utils.php` | water helper functions |
| `backend/api/adherence_utils.php` | overuse/date-series helpers |
| `backend/api/audit_utils.php` | audit log helper |
| `backend/api/get_schedule.php` | today schedule API |
| `backend/api/get_patient_adherence.php` | patient analytics API |
| `backend/api/get_admin_overview.php` | admin analytics API |
| `backend/api/get_admin_users.php` | admin user list |
| `backend/api/get_account_context.php` | current account context |
| `backend/api/get_patients.php` | worker patient list |
| `backend/api/get_worker_prescription.php` | single prescription for edit |
| `backend/api/get_reminder_settings.php` | admin reminder settings read |
| `backend/api/add_prescription.php` | create prescription |
| `backend/api/update_worker_prescription.php` | edit worker prescription |
| `backend/api/delete_worker_prescription.php` | delete worker prescription |
| `backend/api/update_prescription_state.php` | pause/resume/stop prescription |
| `backend/api/update_patient_schedule_times.php` | patient time setup/edit |
| `backend/api/update_dosage.php` | patient dosage edit |
| `backend/api/update_stock.php` | refill stock |
| `backend/api/update_reminder_settings.php` | save admin reminder settings |
| `backend/api/log_intake.php` | log taken/skipped dose |
| `backend/api/snooze_intake.php` | snooze dose |
| `backend/api/log_water_intake.php` | add/reset water intake |
| `backend/api/link_caregiver.php` | link patient to health worker |
| `backend/api/unlink_caregiver.php` | remove care link |
| `backend/api/delete_medicine.php` | admin deletes medicine |
| `backend/api/admin_delete_user.php` | admin deletes user |
| `backend/api/admin_reset_user_password.php` | admin resets password |
| `backend/cron/run_reminders.php` | reminder automation runner |

## Best Backend Reading Order

If you want to understand the backend fast, read in this order:

1. `backend/config/config.php`
2. `backend/api/schedule_utils.php`
3. `backend/api/get_schedule.php`
4. `backend/api/update_patient_schedule_times.php`
5. `backend/api/log_intake.php`
6. `backend/api/notification_utils.php`
7. `backend/cron/run_reminders.php`
8. `backend/api/get_patient_adherence.php`
9. `backend/api/get_admin_overview.php`

## Most Important Mental Model

The backend really runs on five tables working together:

- `users`
- `medicines`
- `intake_logs`
- `notification_logs`
- `water_logs`

And these helper files control most behavior:

- `schedule_utils.php`
- `notification_utils.php`
- `assignment_utils.php`
- `reminder_settings_utils.php`
- `audit_utils.php`

If you understand those, the rest of the project becomes much easier to read.
