# MedTracker Notebook

This notebook is the quick map for the whole project.

It is meant for understanding the codebase before demo, viva, bug fixing, or documentation writing.

## What This Project Is

MedTracker is a multi-role medicine tracking system with:

- patient portal
- health worker portal
- admin dashboard
- medicine scheduling and intake logging
- reminder automation by email and SMS
- hydration tracking
- adherence analytics and PDF reporting

## Main Folders

- `frontend/`
  - all HTML pages
  - shared CSS
  - shared browser JavaScript
- `backend/`
  - PHP auth APIs
  - PHP business-logic APIs
  - config and DB connection
  - reminder cron runner
- `backend/db/database.sql`
  - base schema for tables

## How The System Flows

### 1. Login and Role Routing

- The browser saves `role`, `user_id`, and `name` in `localStorage`.
- `frontend/js/script.js` decides which dashboard the user should reach.

```js
getDashboardRoute(role) {
    if (normalizedRole === 'admin') return 'admin_dashboard.html';
    if (normalizedRole === 'health worker' || normalizedRole === 'doctor') return 'doctor_dashboard.html';
    return 'dashboard.html';
}
```

### 2. Health Worker Prescribes Medicine

- A health worker opens `frontend/doctor_schedule.html`.
- The form submits to `backend/api/add_prescription.php`.
- The backend stores the medicine row in `medicines`.
- The patient later sets real clock times.

```php
$stmt = $pdo->prepare("INSERT INTO medicines (...) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
```

### 3. Patient Sets Reminder Times

- Patient opens `frontend/medications.html`.
- Time changes go to `backend/api/update_patient_schedule_times.php`.
- Backend updates `custom_times_json`, then rebuilds future pending logs.

```php
medtracker_resync_single_medicine_schedule($pdo, $updatedMedicine);
```

### 4. Daily Schedule Is Generated

- `backend/api/schedule_utils.php` is the schedule engine.
- It turns medicine frequency plus patient times into `intake_logs`.
- `backend/api/get_schedule.php` returns today’s rows for dashboard and schedule pages.

```php
foreach ($medicines as $medicine) {
    medtracker_create_schedule_logs($pdo, $medicine);
}
```

### 5. Reminder Runner Sends Alerts

- Windows Task Scheduler runs `backend/cron/run_reminders.php`.
- The runner checks pending doses.
- It sends:
  - upcoming reminder
  - due-now reminder
  - missed reminder
- If admin enabled it, missed doses are auto-marked skipped only at the missed stage.

```php
if ($now >= $missedAt) {
    $missedRows[] = $row;
    continue;
}
```

### 6. Notification Delivery

- `backend/api/notification_utils.php` sends email with PHPMailer and SMS with Twilio.
- Every send is stored in `notification_logs`.
- Duplicate event keys stop double notifications.

```php
if ($eventKey && medtracker_notification_already_logged($pdo, $eventKey, $channel)) {
    $results[$channel] = ['status' => 'SKIPPED', 'message' => 'Duplicate notification prevented.'];
}
```

### 7. Reports and Admin Analytics

- Patient report data comes from `backend/api/get_patient_adherence.php`.
- Admin analytics come from `backend/api/get_admin_overview.php`.
- Both power charts, tables, and PDF summaries on the frontend.

## Reading Order

If you want to understand the app fast, read in this order:

1. `frontend/js/script.js`
2. `backend/config/config.php`
3. `backend/api/schedule_utils.php`
4. `backend/api/get_schedule.php`
5. `backend/api/notification_utils.php`
6. `backend/cron/run_reminders.php`
7. `backend/api/get_patient_adherence.php`
8. `backend/api/get_admin_overview.php`

## Detailed Guides

- `description/frontend-files.md`
  - explains all frontend `.html`, `.js`, and `.css` files
- `description/php-files.md`
  - explains all main PHP files and helper files

## Scope Note

These notes focus on project-owned files inside `frontend/` and `backend/`.

They intentionally do not document:

- `vendor/`
- generated composer files
- log files
- outside SQL dumps in `Downloads`
