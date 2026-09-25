# Homecare

Homecare management: patient intake and assessment, staff and driver duty
scheduling, attendance, medication, vitals, notes, documents and discharge.

This is the rebuild of the legacy `homecare-mgmt` app (Laravel 5.7, class-based
React, DynamoDB). It follows the architecture, UI and conventions of
`webemr_multitenant`.

## Stack

| Layer    | Choice |
|----------|--------|
| Backend  | Laravel 12, PHP 8.2+, `mongodb/laravel-mongodb` 5, `php-open-source-saver/jwt-auth` 2 |
| Frontend | React 18, Vite 7, MUI 5, Redux Toolkit, react-hook-form + yup, TanStack Table, React Router 7 |
| Data     | MongoDB: one master database plus one database per homecare center |
| Messaging| Twilio WhatsApp templates (queued), Firebase (video-call push) |

Laravel 12 is used instead of the EMR's 11 because every Laravel 11 release
now has unpatched security advisories. The application structure is the same.

## Local setup

Requirements: PHP 8.2+ with the `mongodb` extension **2.4 or newer**, Composer,
Node 20+, and a MongoDB server or Atlas cluster.

```bash
composer install
npm install
cp .env.example .env        # then fill in DB_DSN (and Twilio/Firebase if needed)
php artisan key:generate
php artisan jwt:secret
npm run dev                 # or: npm run build
php artisan serve
php artisan queue:work      # sends WhatsApp and push notifications
```

Open http://127.0.0.1:8000 and register a homecare center, or migrate the
legacy data (Phase 2).

## How it fits together

### Tenancy

- **Master database** (`homecare_master`): `users` (every login),
  `homecares` (the tenant registry), reference data (countries, regions,
  cities, nationalities, ICD codes, service packages), queue jobs.
- **Tenant databases** (`homecare_<homecare_id>`): everything else, such as
  patients, schedules, notes and roles. Collection names match the legacy
  DynamoDB tables.
- `ResolveTenant` (route alias `tenant`) switches tenant models to the signed-in
  user's database, using the `homecare_id` on their account. The tenant is
  never taken from a client-supplied header.
- Tenant models extend `App\Models\TenantModel`; master models set
  `$connection = 'mongodb_master'`.
- `php artisan homecare:provision {homecare_id}|--all` creates collections,
  indexes (`App\Tenancy\TenantSchema`) and default roles. It is safe to re-run.

### Permissions

- The catalogue lives in code, in `App\Support\Permissions`, with names like
  `patients.view`. Roles, and the permissions each role holds, are editable per
  homecare center.
- **Every route checks its permission on the server**:
  `->middleware('permission:patients.manage')`. The frontend's `PermissionGate`
  and `usePermission` only hide UI.
- `admin` and the homecare owner account always hold every permission.

### APIs

- `routes/api.php` → `/api/v1/*`, used by the new web app. Responses use the
  `{ success, message, data }` envelope.
- `routes/legacy.php` → `/api/*`: the old mobile app's endpoints, with the same
  URLs, field names and response shapes. **Never change a legacy contract.** Put
  improvements under v1; the legacy controller then adapts to the same service.

### Adding a module

1. Model(s) in `app/Models` (tenant data extends `TenantModel`), with indexes in `TenantSchema`.
2. Business logic in `app/Services`; validation in `app/Http/Requests`.
3. v1 controller and routes with `permission:` middleware.
4. Legacy controller and routes, if the mobile app uses the module.
5. Frontend: `src/api/<module>.js`, pages in `src/components/<module>/`,
   a slice in `src/store/` if state is shared, a route in `AppRoutes.jsx`, and
   an entry in `layouts/navConfig.js`.
6. Tests in `tests/Feature` (v1 and legacy contract) and `tests/Unit`.

### Shared services

Code that the legacy controllers repeated lives in one place:

| Service | Replaces |
|---------|----------|
| `WhatsAppService` + `SendWhatsAppTemplate` job | inline Twilio calls in every scheduling endpoint (now queued) |
| `FileStorageService` | copy-to-`public/`, push-to-S3, delete-folder in every upload endpoint |
| `SequenceService` | `count() + 1` lead and patient numbers (atomic counters in `counters`) |
| `UserDirectoryService` | per-row user lookups (one `whereIn` per list) and unscoped role queries |
| `ScheduleConflictService` | three copies of the nested overlap checks |
| `ReferenceDataService::internationalNumber()` | country phone-code lookups before every message |
| `App\Support\TimeFormat` | the 24h → 12h conversion copied into each notification |

Domain services (`PatientService`, `DutyScheduleService`, `MedicationService`,
...) are shared by the v1 and legacy controllers.

## Deliberate changes from the legacy app

- The legacy forgot-password flow is disabled. It returned the OTP to the
  client and let anyone reset any account's password. Admins reset staff
  passwords until a verified OTP flow is added.
- Tokens expire: `JWT_TTL` (default 24 h) for the web, and `JWT_LEGACY_TTL`
  (default 30 days) for legacy mobile logins. Legacy tokens never expired.
- Homecare IDs are made unique. The legacy slug check could give two
  centers the same ID.
- Not ported: the Apurvi screening module, Boardroom, appointment auth and
  Plivo SMS. These were copied from other projects and are unused. Plivo
  was the only channel for sign-in/out, pickup/drop, discharge and new
  staff username messages, so those notifications are no longer sent.
- The Redis patient cache is gone. Its keys were shared by every center
  except two, so one center could be served another's patients.
- Queries on users (master database) are always scoped to the homecare.
  Several legacy queries picked "the admin" or a driver from any center.
- Endpoints that updated "every field in the request" (patient details,
  assessments, reschedules, vitals) now only accept the model's fields.
- Recording a second medication session for a medicine on the same day
  wrote to a field named `medicine_type`; it now goes to its session.
- `get-patients-vital-signs-bydate` and `get-staff-notes-by-date` ignored
  the date and returned everything; they now filter by it.
- Walk-in patients got a hard-coded `+91` prefix; the country's code is used.
- Uploaded file names are sanitised before they become storage keys.
- New staff store their ID number as `identity_number` (legacy `id`
  clashes with MongoDB's `id` alias for `_id`).
