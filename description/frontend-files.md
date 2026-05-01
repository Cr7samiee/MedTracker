# Frontend File Guide

This guide explains how the frontend is organized and what each frontend file does.

The frontend is plain:

- HTML for page structure
- CSS for layout and styling
- vanilla JavaScript for fetch calls, routing, and DOM updates

## How The Frontend Works Overall

The frontend is page-based, not component-based.

Each page:

- renders a screen for one role or one task
- loads shared styles from `css/style.css` and `css/dashboard.css`
- often contains page-specific inline `<script>` code
- uses `fetch()` to call PHP endpoints in `../backend/...`

The shared JavaScript helper is `frontend/js/script.js`.

It handles:

- role normalization
- route protection
- sidebar navigation rendering
- session logout
- dynamic username rendering

### Shared Snippet

```js
protectRoute(allowedRoles) {
    const normalizedRole = this.normalizeRole(this.getCurrentUser().role);
    if (allowed.length && !allowed.includes(normalizedRole)) {
        window.location.href = this.getDashboardRoute(normalizedRole);
        return false;
    }
    return true;
}
```

This is the main pattern that keeps users in the correct portal.

## Shared Frontend Files

### `frontend/js/script.js`

Purpose:

- global app helper for every page
- stores portal rules
- reads `localStorage`
- decides where a user should go based on role

Main responsibilities:

- `getCurrentUser()`
- `getDashboardRoute()`
- `renderRoleNav()`
- `protectRoute()`
- `logout()`
- signup form validation and submit logic

Why it matters:

- this is the glue between authentication and page routing

### `frontend/css/style.css`

Purpose:

- global design system
- colors, spacing, buttons, typography, landing-page layout

What lives here:

- CSS variables like `--primary`, `--text-main`, `--surface-solid`
- button styles
- navbar styles
- landing page sections
- base typography and reset rules

Snippet:

```css
:root {
    --primary: #4F46E5;
    --secondary: #10B981;
    --text-main: #0F172A;
}
```

This file is the base theme of the whole app.

### `frontend/css/dashboard.css`

Purpose:

- shared dashboard layout for patient, health worker, and admin pages

What lives here:

- sidebar
- top bar
- dashboard grid
- cards
- schedule list
- shared portal layout

Snippet:

```css
.dashboard-layout {
    display: flex;
    min-height: 100vh;
}
```

This file makes the portals look consistent even though each page also has inline styles.

## Public / Entry Pages

### `frontend/index.html`

Purpose:

- landing page for the project
- introduces MedTracker and sends users to auth flow

Typical use:

- first page a visitor sees

### `frontend/role_selection.html`

Purpose:

- lets a new user choose which role they want before signup

Why it exists:

- signup form changes depending on role
- this page makes role selection explicit first

### `frontend/login.html`

Purpose:

- login screen for all roles

How it works:

- collects phone and password
- posts to `backend/auth/login.php`
- stores user data in `localStorage`
- redirects to the correct dashboard

### `frontend/signup.html`

Purpose:

- registration page for admin, health worker, or patient

How it works:

- role is usually passed in query string
- `script.js` shows the right fields
- submits to `backend/auth/signup.php`

Important behavior:

- patient can provide doctor code
- health worker must provide post
- patient must provide relation

### `frontend/forgot_password.html`

Purpose:

- starts password reset flow

How it works:

- collects phone number
- requests OTP/email process from `backend/auth/forgot_password.php`

### `frontend/reset_password.html`

Purpose:

- completes reset with OTP and new password

How it works:

- sends phone, OTP, and new password to `backend/auth/reset_password.php`

## Patient Portal Pages

### `frontend/dashboard.html`

Purpose:

- patient home page
- summary of today’s schedule, adherence, low stock, and water tracking

What it shows:

- today’s doses
- remaining/taken/cleared filters
- water tracking ring
- quick actions like log dose, skip, snooze, refill

Important behavior:

- fetches schedule/adherence data
- lets the patient clear completed cards from view without deleting history
- shows snoozed and skipped metadata

Main UI idea:

```html
<div class="schedule-list" id="patientDashboardSchedule">
    <p class="text-muted">Loading today's doses...</p>
</div>
```

This page is the patient’s daily control center.

### `frontend/medications.html`

Purpose:

- full list of medicines for the patient
- medicine search
- time setup and editing
- stock overview

What it does:

- lists active medicines
- lets patient search by medicine name
- lets patient open time editor and set `1`, `2`, or `3` daily times
- reflects doctor frequency as dose count per day

Why it matters:

- this is where patient-defined reminder times are managed

### `frontend/schedule.html`

Purpose:

- calendar-like list of daily doses

What it does:

- shows today’s intake rows
- supports mark taken
- supports skip with reason
- supports snooze
- shows effective time when a dose was snoozed

Why it matters:

- this page is closer to raw `intake_logs`

### `frontend/reports.html`

Purpose:

- patient analytics and PDF export

What it shows:

- adherence summary
- weekly and monthly trends
- late-dose charts
- missed reasons
- water + medicine wellness summary
- refill adherence

Libraries used here:

- Chart.js for graphs
- jsPDF for PDF export

Snippet:

```html
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
```

### `frontend/add_medication.html`

Purpose:

- patient self-add page for medicines they want to manage personally

How it differs from doctor prescribing:

- patient becomes the source of the medicine
- still uses same scheduling model

### `frontend/settings.html`

Purpose:

- profile and account-related settings

Likely uses:

- personal account info
- linked caregiver/doctor management
- portal-specific settings

## Health Worker Pages

### `frontend/doctor_dashboard.html`

Purpose:

- main health worker portal

What it shows:

- patient queue
- adherence overview
- patient search
- prescription cards with actions

Actions supported:

- edit prescription
- delete prescription
- pause
- resume
- stop

Why it matters:

- this is where health worker operational work happens

### `frontend/doctor_schedule.html`

Purpose:

- prescription desk

What it does:

- health worker selects patient
- enters medicine name, dosage, type, quantity, frequency, instructions
- chooses treatment mode: course or ongoing
- submits to backend API

Important project rule:

- doctor chooses frequency only
- patient later chooses actual reminder times

Snippet:

```html
<select id="scheduleTime" class="form-control" required>
    <option value="1x daily">Once a day</option>
    <option value="2x daily">Twice a day</option>
    <option value="3x daily">Three times a day</option>
</select>
```

## Admin Pages

### `frontend/admin_dashboard.html`

Purpose:

- admin control center

What it shows:

- user management
- system analytics
- reminder rules panel
- SMS/email delivery dashboard
- audit trail
- most missed medicines

Why it matters:

- this page is for operations and oversight, not daily medicine use

## File-by-File Summary Table

| File | Main Responsibility |
|---|---|
| `frontend/index.html` | public landing page |
| `frontend/role_selection.html` | role choice before signup |
| `frontend/login.html` | login form |
| `frontend/signup.html` | account creation |
| `frontend/forgot_password.html` | request reset OTP |
| `frontend/reset_password.html` | complete password reset |
| `frontend/dashboard.html` | patient daily dashboard |
| `frontend/medications.html` | patient medicine management |
| `frontend/schedule.html` | patient dose-by-dose schedule view |
| `frontend/reports.html` | patient reports and PDF export |
| `frontend/add_medication.html` | self-add medicine page |
| `frontend/settings.html` | account/settings page |
| `frontend/doctor_dashboard.html` | health worker operations page |
| `frontend/doctor_schedule.html` | prescribe medicine form |
| `frontend/admin_dashboard.html` | admin analytics and management |
| `frontend/js/script.js` | shared routing/session JS |
| `frontend/css/style.css` | global design system |
| `frontend/css/dashboard.css` | shared dashboard layout |

## Best Frontend Reading Order

If you want to learn the frontend step by step:

1. `frontend/js/script.js`
2. `frontend/css/style.css`
3. `frontend/css/dashboard.css`
4. `frontend/login.html`
5. `frontend/dashboard.html`
6. `frontend/medications.html`
7. `frontend/schedule.html`
8. `frontend/doctor_dashboard.html`
9. `frontend/doctor_schedule.html`
10. `frontend/admin_dashboard.html`
