# Tossa Workshop — Front-end Employee Intake Module (Build Plan)

**Status:** proposal for review (pre-coding)
**Author:** Tossa Cycling / Claude Code
**Applies to plugin version:** 1.0.x (adds a new isolated module)

This plan describes a **native front-end intake module** built inside the
existing `tossa-workshop` plugin. It deliberately reuses the plugin's existing
architecture (CPTs, factories, search, status engine, notifier, capabilities)
and does **not** use Gravity Forms for the core intake. Gravity Forms remains a
candidate only for a future *public* repair-request form, out of scope here.

---

## 0. Goal & principles

A fast, mobile-first, app-like screen for shop-floor staff to: identify a bike
(scan or search) → see who/what/history → decide → create a repair job (or a
new bike + job) in under ~2 minutes.

Principles (do not violate):
- **Reuse, don't duplicate.** All writes go through existing helpers
  (`Job_Factory`, `ID_Generator`, `QR_Generator`, `Job_Status`, `Field_Kit`,
  `Notifier`). One source of truth for IDs, sanitization and side effects.
- **Minimum data now, details later.** Intake captures only what's needed to
  open the job; mechanics complete the record in admin during inspection.
- **Authenticated staff only.** No public exposure. PII never leaks.
- **Isolated.** The module is additive; the plugin must function fully with it
  removed/disabled.

---

## 1. Scope

### In scope (this module)
- Protected `/workshop-intake/` front-end route.
- Find a bike: manual search (reusing `Bike_Search` AJAX) + entry by internal ID;
  arrival pre-loaded via `?bike=TCB-…` (from a QR scan).
- Bike card: core details, **open-job warning**, last N jobs.
- Create a repair job from an existing bike (short form) → `received`.
- Create a new bike + job when none exists (short form) — requires a new
  `Bike_Factory`.
- Confirmation screen with next actions.
- Optional setting to repoint logged-in QR scans to intake.

### Out of scope (v1 — deferred to phase 2 / later)
- In-browser camera QR scanning (`html5-qrcode`). Native phone camera scanning
  the printed QR already lands staff on intake.
- Photo upload on the front end (capability + media-frame complexity — see §5.3).
- Editing the full bike profile from intake (use the admin screen).
- Public/customer repair-request form (possible Gravity Forms use, separate).

---

## 2. Architecture decisions (fixed)

- **Native, in-plugin module.** No Gravity Forms in the core intake.
- **Standalone rendering** (like the M4 client page), not a theme template, to
  avoid theme CSS conflicts and get the app-like feel. We control a minimal
  HTML shell + our own CSS/JS.
- **No new data model.** Bikes and jobs stay as the existing CPTs + meta. The
  module is a UI/orchestration layer.
- **Writes via admin-post.php** (logged-in users), so handlers run before output
  and redirects (PRG) work — same pattern as `Fleet_Check` and the notifications
  page. The page render is a front-end rewrite route.
- **i18n via gettext** (site/admin locale). Intake is staff-facing, so unlike the
  client status page it does not need per-recipient language switching. ES
  catalog updated as usual.

---

## 3. Routing & access control

### Route
- `GET /workshop-intake/` → query var `tcw_intake=1` (rewrite rule, same pattern
  as `/workshop-scan/` and `/workshop-status/`). Reserved slug.
- `GET /workshop-intake/?bike=TCB-000123` → pre-load that bike's card.
- Search: reuse the existing AJAX endpoint `wp_ajax_tcw_bike_search`
  (`Bike_Search`) — already cap-checked (`tcw_view_bikes`) and nonce-protected.
- Writes: `POST wp-admin/admin-post.php`
  - `action=tcw_intake_create_job`
  - `action=tcw_intake_create_bike` (bike + job)

Rewrite flush: the new rule registers on `init`; the existing version-based
`maybe_flush` (bump `TCW_VERSION`) persists it on the next admin load. Activator
also registers it before its flush for fresh installs.

### Access control
- **Not logged in** → `auth_redirect()` (WordPress login, returns to intake).
- **Logged in, no permission** → polite refusal page ("You do not have
  permission to access the workshop intake.").
- Capability gates:
  - View intake + search + bike card: **`tcw_view_bikes`**.
  - Create job from existing bike: **`tcw_edit_jobs`**.
  - Create new bike: **`tcw_edit_bikes`** (so the "new bike" form/CTA only shows
    to Front Desk / Manager; Mechanic can still scan, view and create jobs).
- Every POST: `wp_verify_nonce` (action-scoped) **and** the matching capability,
  re-checked server-side. Client input never trusted beyond the post ID + nonce.

| Role | Scan/search/view | Create job | Create new bike |
| --- | --- | --- | --- |
| Workshop Manager | ✓ | ✓ | ✓ |
| Front Desk | ✓ | ✓ | ✓ |
| Mechanic | ✓ | ✓ | ✗ |

---

## 4. New classes / files

```
includes/class-intake-page.php      Intake_Page  — route, render (modes), assets
includes/class-intake-actions.php   Intake_Actions — admin-post handlers (POST)
includes/class-bike-factory.php     Bike_Factory — programmatic bike creation (NEW, see §5)
assets/js/intake.js                 search/autocomplete, UI, sticky submit
assets/css/intake.css               mobile-first app-like styling
```

Registered in `Plugin::run()`:
```php
Intake_Page::instance()->register_hooks();      // front-end route + assets
if ( is_admin() ) { /* admin-post handlers register on every load anyway */ }
Intake_Actions::instance()->register_hooks();   // admin_post_* (logged-in)
```

Reused as-is: `Bike_Search`, `Job_Factory`, `Job_Status`, `Job_Status_Taxonomy`,
`ID_Generator`, `QR_Generator`, `Field_Kit`, `Bike_Fields`, `Repair_Job_Fields`,
`Notifier`, `Client_Status_Page::url()`, `Settings_Page`, `Roles`.

---

## 5. `Bike_Factory` (the gap that must be filled first)

Today, a bike's `internal_id` + QR are generated only on the **admin save_post**
path (nonce-gated). A programmatic `wp_insert_post` will NOT mint `TCB-…`/`TCF-…`
or the QR. Mode 3 (create new bike) therefore needs a factory mirroring
`Job_Factory`.

### 5.1 API
```php
Bike_Factory::create( array $args ): int   // returns bike post ID, or 0
```
`$args` (minimal): `bike_type` (customer|fleet), `brand`, `model`, and optional
`owner_name/email/phone/owner_language`, `category`, `frame_size`, `color`,
`serial_number`, `rental_category` (fleet).

### 5.2 Behaviour (mirrors `Job_Factory::create_for_bike`)
1. `wp_insert_post` (publish), title temporary.
2. Persist the provided fields via `Field_Kit::sanitize()` per `Bike_Fields`
   definitions (one source of truth).
3. `internal_id = ID_Generator::generate_bike_id( $type, $rental_category )`.
4. `qr_attachment_id = QR_Generator::generate_for_bike( $id, $internal_id )`.
5. Set readable title `"{brand} {model} ({internal_id})"`.
6. Return ID.

### 5.3 Photo note
Front-end `wp.media` needs the `upload_files` capability, which the workshop
roles do **not** have. **v1 intake omits photo upload** (or a single basic
`<input type=file>` gated behind `upload_files` if a role is granted it later).
Photos are added on the admin screen at inspection. This keeps intake fast and
avoids widening the upload surface. (Open question Q3.)

---

## 6. Page modes & flows

Standalone HTML shell (header = shop name, minimal CSS). Three modes:

### Mode 1 — Find bike
- Big "Scan QR" hint (native camera in v1) + a large search box.
- Search via `Bike_Search` AJAX: internal ID, serial, owner name/email/phone,
  brand/model. Mobile-friendly results list; selecting one loads Mode 2.

### Mode 2 — Bike found (card)
Shows (staff only): internal ID, brand/model, category, size, type
(customer/fleet), owner name/phone/email/language, serial.
- **Open-job warning** (see §7): if an active job exists, show it prominently
  with "Open existing job" vs "Create another anyway".
- Last 3 jobs (job_id, status, date).
- Actions: **Create repair job** · Edit in admin · Print label.

### Mode 3 — Bike not found
- "Create new bike + repair job" → short combined form (Bike_Factory then
  Job_Factory). Required-only fields per §5.1 + problem description, priority.

### Create-job form (from existing bike)
Compact subset: `problem_description`, `client_notes`,
`bike_condition_on_arrival`, `priority`, `promised_completion`,
`assigned_mechanic`, and a **"Send received email?"** toggle. (Accessories /
visible damage optional — keep short.)

### Confirmation screen
```
Repair job created — TCW-2026-0052  (Bike TCB-000123)
[Open job (admin)] [Back to intake] [Print label] [Copy client WhatsApp message]
```
"Copy WhatsApp message" = prefilled text incl. the client status link
(`Client_Status_Page::url()`).

---

## 7. Open-job dedup (high value)

Before/at the bike card, query repair jobs linked to the bike whose status is
**not** in `{delivered, closed, cancelled, declined}` (i.e. still active). If any
exist, surface the most recent with a clear warning and a one-tap link to open
it, plus an explicit "create another anyway". Prevents duplicate jobs — the most
common workshop floor error.

Implementation: `WP_Query` on `tcw_repair_job` with `meta_query bike_id = X` and
a `tax_query` excluding the closed-set statuses (or fetch + filter in PHP).

---

## 8. Security checklist

- Front-end route renders nothing without `is_user_logged_in()` +
  `current_user_can('tcw_view_bikes')`.
- All POST writes: action-scoped nonce + capability re-check; sanitize every
  field via `Field_Kit`; escape all output.
- Status always set server-side via `Job_Status::set()` (client can't choose
  arbitrary status).
- `?bike=` is treated as a lookup key only; bike resolved by `internal_id` meta,
  output escaped.
- No PII in any non-authenticated path (intake is fully gated; public scan page
  behaviour unchanged).
- admin-post handlers `wp_safe_redirect` + `exit` (PRG; no double submit).

---

## 9. Scan-redirect setting (phase 2)

New setting `scan_destination ∈ { intake, admin }`, default **intake**.
- `Scan_Router`: logged-in + `tcw_view_bikes` → redirect to
  `/workshop-intake/?bike={internal_id}` (default) or the admin edit screen.
- Bike card always offers "Edit in admin", so nothing is lost.
- Public/unauthenticated scan behaviour is **unchanged** (minimal no-PII page).

---

## 10. Internationalization

- All UI strings via `__()`/`esc_html__()` (text domain `tossa-workshop`).
- Full ES translation added to the existing catalog (`tools/i18n-extract.php`
  + `tools/build-translations.php`), as with every milestone.
- Staff see the site/admin locale (ES on this install).

---

## 11. Phasing & acceptance criteria

### Phase 1 — Core intake (highest daily ROI)
1. `Bike_Factory` (+ unit test for ID/QR/title generation).
2. `/workshop-intake/` route, auth + capability gating, standalone shell.
3. Mode 1 search (reuse `Bike_Search`) + Mode 2 bike card with **open-job
   warning** + last 3 jobs.
4. Create job from existing bike → `Job_Factory::create_for_bike()` + extra
   fields + optional received email → confirmation screen.

*Accept:* a logged-in Front Desk user can open `/workshop-intake/`, search a
bike by serial/owner phone, see its card + any open job, create a job that gets
`TCW-…`, inherits client contact, lands in `received`, optionally emails the
client, and reaches the confirmation screen. Logged-out → login; no-cap →
refusal. Plugin still works with the module disabled.

### Phase 2 — New bike, scan redirect, polish
5. Mode 3 create new bike + job (`Bike_Factory` + `Job_Factory`), gated by
   `tcw_edit_bikes`.
6. `scan_destination` setting; repoint logged-in scans to intake.
7. Mobile UX polish: sticky submit, big touch targets, "Copy WhatsApp message".

### Phase 3 — Optional
8. In-browser camera scanning (`html5-qrcode`, lazy-loaded).
9. Front-end photo upload (only if a role is granted `upload_files`).

---

## 12. Open questions for review (ChatGPT)

- **Q1.** Scan redirect default — switch logged-in scans to intake by default,
  or keep admin-edit as default and make intake opt-in? (Plan assumes intake
  default + "Edit in admin" button.)
- **Q2.** Should Mechanics be able to create **new bikes** from intake, or is
  that strictly Front Desk / Manager? (Plan: Front Desk / Manager only.)
- **Q3.** Photo upload at intake — defer to admin (plan's choice), grant
  `upload_files` to workshop roles, or use a lightweight direct uploader?
- **Q4.** "Send received email?" — should the toggle suppress just that one
  notification while still firing the status-changed audit action? (Needs a
  small per-request suppression hook in `Notifier`; design detail to confirm.)
- **Q5.** How many recent jobs on the card — 3 (plan) or more?
- **Q6.** Standalone page vs theme-wrapped — plan picks standalone for speed/
  isolation; confirm that's acceptable (no theme header/footer).

---

## 13. Verification approach

Consistent with the rest of the build (no WP runtime in the dev container):
- `php -l` + `node --check`.
- Standalone unit tests for pure logic: `Bike_Factory` ID/QR/title, open-job
  query filter, capability matrix helper.
- WP-stub activation smoke test extended to load the new classes/route.
- Per-phase high-effort code review before commit.
- Real activation/scan/create-flow tested on staging by Tossa.
```
