# Tossa Workshop — Front-end Employee Intake Module (FINAL Build Plan)

**Status:** approved for build (decisions locked)
**Author:** Tossa Cycling / Claude Code + ChatGPT review
**Applies to plugin version:** 1.0.x (adds a new isolated module)

A **native front-end intake module** inside the existing `tossa-workshop`
plugin. Reuses the existing architecture (CPTs, factories, search, status
engine, notifier, capabilities). **No Gravity Forms** in the core intake
(Gravity Forms remains a candidate only for a future *public* repair-request
form, out of scope here).

---

## 0. Goal & principles

A fast, mobile-first, app-like screen for shop-floor staff to: identify a bike
(scan or search) → see who/what/history → warn about any open job → create a
repair job (or a new bike + job) in under ~2 minutes.

- **Reuse, don't duplicate.** All writes go through existing helpers
  (`Job_Factory`, `Bike_Factory`, `ID_Generator`, `QR_Generator`, `Job_Status`,
  `Field_Kit`, `Notifier`). One source of truth for IDs, sanitization, side
  effects, and **status slugs**.
- **Minimum data now, details later.** Mechanics complete the record in admin
  during inspection.
- **Authenticated staff only.** No public exposure; PII never leaks.
- **Capability-based, never role-name-based.** Gate every action on an existing
  capability.
- **Isolated.** Additive; the plugin functions fully with the module removed.

---

## 1. Scope

### In scope
- Protected `/workshop-intake/` front-end route (standalone, app-like).
- Find a bike: manual search (reusing `Bike_Search` AJAX) + arrival pre-loaded
  via `?bike=TCB-…` from a QR scan.
- Bike card: core details, **open-job warning**, last 3 jobs + full-history link.
- Create a repair job from an existing bike (short form) → `received`, with an
  optional "send received email" toggle.
- Create a new bike + job when search finds nothing — requires new `Bike_Factory`.
- Confirmation screen with next actions.
- Setting to repoint logged-in QR scans to intake (default on).

### Out of scope (v1; later phases)
- In-browser camera QR scanning (`html5-qrcode`) — Phase 3.
- Photo upload — Phase 2 (controlled uploader, see §5.3).
- Editing the full bike profile from intake (use the admin screen).
- Public/customer repair-request form (possible Gravity Forms, separate project).

---

## 2. Architecture decisions (fixed)

- Native, in-plugin module. No Gravity Forms in the core intake.
- **Standalone rendering** (like the M4 client page) — no theme header/footer,
  minimal own CSS/JS, app-like, no theme CSS conflicts.
- **No new data model.** Existing CPTs + meta.
- **Writes via `admin-post.php`** (logged-in), so handlers run before output and
  PRG redirects work — same pattern as `Fleet_Check`. Page render is a
  front-end rewrite route.
- **i18n via gettext** (site/admin locale; staff-facing). ES catalog updated.
- **No new capabilities.** Reuse existing caps (see §3).

---

## 3. Routing & access control

### Routes
- `GET /workshop-intake/` → query var `tcw_intake=1` (rewrite rule; reserved slug,
  alongside `/workshop-scan/` and `/workshop-status/`).
- `GET /workshop-intake/?bike=TCB-000123` → pre-load that bike's card.
- Search: reuse `wp_ajax_tcw_bike_search` (`Bike_Search`) — already cap-checked
  (`tcw_view_bikes`) and nonce-protected.
- Writes (admin-post, logged-in):
  - `action=tcw_intake_create_job`
  - `action=tcw_intake_create_bike` (bike + job)

Rewrite flush: rule registers on `init`; the existing version-based `maybe_flush`
(bump `TCW_VERSION`) persists it; the activator registers it before its flush for
fresh installs.

### Access control (capability-based, **no new caps**)
- Not logged in → `auth_redirect()` (login, returns to intake).
- Logged in, lacks `tcw_view_bikes` → polite refusal page.
- **View / search / bike card / create job:** `tcw_edit_jobs`
  (Manager, Front Desk, **and Mechanic** — per owner decision, mechanics may
  create jobs; open-job dedup guards duplicates).
- **Create new bike (Mode 3):** `tcw_edit_bikes` (Manager + Front Desk only —
  Mechanic lacks this cap, so the "create new bike" path is hidden for them).
- Every POST: action-scoped `wp_verify_nonce` **and** the matching capability,
  re-checked server-side.

| Role | Scan/search/view | Create job | Create new bike |
| --- | --- | --- | --- |
| Workshop Manager | ✓ | ✓ | ✓ |
| Front Desk | ✓ | ✓ | ✓ |
| Mechanic | ✓ | ✓ | ✗ |

> No `tcw_create_jobs` / `tcw_create_bikes` capabilities are introduced. The
> existing `tcw_edit_jobs` / `tcw_edit_bikes` already encode the desired
> separation. If policy later changes to block mechanics from creating jobs, add
> a single `tcw_create_jobs` cap then — not now.

---

## 4. New classes / files

```
includes/class-intake-page.php      Intake_Page    — route, render (modes), assets
includes/class-intake-actions.php   Intake_Actions — admin-post handlers (POST)
includes/class-bike-factory.php     Bike_Factory   — programmatic bike + ID/QR (NEW, §5)
assets/js/intake.js                 search, UI, sticky submit
assets/css/intake.css               mobile-first app-like styling
```

Registered in `Plugin::run()`:
```php
Intake_Page::instance()->register_hooks();     // front-end route + assets
Intake_Actions::instance()->register_hooks();  // admin_post_* (logged-in only)
```

Reused as-is: `Bike_Search`, `Job_Factory`, `Job_Status`,
`Job_Status_Taxonomy`, `ID_Generator`, `QR_Generator`, `Field_Kit`,
`Bike_Fields`, `Repair_Job_Fields`, `Notifier`, `Client_Status_Page::url()`,
`Settings_Page`, `Roles`.

---

## 5. `Bike_Factory` (build & test FIRST)

Bikes currently get `internal_id` + QR only on the admin `save_post` path
(nonce-gated). A programmatic `wp_insert_post` will **not** mint them. Mode 3
needs a factory mirroring `Job_Factory`. **No intake UI work begins until
`Bike_Factory` is implemented and unit-tested.**

### 5.1 API
```php
Bike_Factory::create( array $args ): int   // bike post ID, or 0 on failure
```
`$args` minimal: `bike_type` (customer|fleet), `brand`, `model`; optional
`owner_name/owner_email/owner_phone/owner_language`, `category`, `frame_size`,
`color`, `serial_number`, `rental_category` (fleet).

### 5.2 Behaviour (mirrors `Job_Factory`)
1. `wp_insert_post` (publish), temp title.
2. Persist provided fields via `Field_Kit::sanitize()` keyed by `Bike_Fields`
   definitions (one source of truth — same sanitization as the admin form).
3. `internal_id = ID_Generator::generate_bike_id( $type, $rental_category )`.
4. `qr_attachment_id = QR_Generator::generate_for_bike( $id, $internal_id )`.
5. Title `"{brand} {model} ({internal_id})"`.
6. Return ID.

### 5.3 Photo note
- **Phase 1: no photo upload** at intake.
- **Phase 2: a controlled uploader** — a plain `<input type="file">` →
  `wp_handle_upload` + `wp_insert_attachment`, gated by our own `tcw_edit_jobs`
  check, image-only, size-limited, attached to the job, IDs stored in
  `intake_photos`. **No `upload_files` grant needed** (only the JS `wp.media`
  frame requires that cap — we don't use it). Photos otherwise added on the
  admin screen at inspection.

---

## 6. Page modes & flows

Standalone HTML shell (header = shop name, minimal CSS). Three modes:

### Mode 1 — Find bike
Big "Scan QR" hint (native phone camera in v1) + a large search box.
`Bike_Search` AJAX: internal ID, serial, owner name/email/phone, brand/model.
Selecting a result loads Mode 2.

### Mode 2 — Bike found (card)
Staff-only details: internal ID, brand/model, category, size, type, owner
name/phone/email/language, serial.
- **Open-job warning** (§7), prominent, with "Open existing job" vs "Create
  another anyway".
- **Last 3 jobs** (job_id, status, date) + **"View full history"** link.
- Actions: **Create repair job** · **Edit in admin** · **Print label**.

### Mode 3 — Bike not found (only after a search returns nothing)
"Create new bike + repair job" → short combined form (`Bike_Factory` then
`Job_Factory`). Required-only fields per §5.1 + problem description, priority.
Mode 3 is **never shown before a search** — this is the primary duplicate guard.

### Create-job form (from existing bike)
Compact: `problem_description`, `client_notes`, `bike_condition_on_arrival`,
`priority`, `promised_completion`, `assigned_mechanic`, and a **"Send received
email?" toggle (default checked)**.

### Confirmation screen
```
Repair job created — TCW-2026-0052  (Bike TCB-000123)
[Open job (admin)] [Back to intake] [Print label] [Copy client WhatsApp message]
```
"Copy WhatsApp message" = prefilled text incl. the client status link
(`Client_Status_Page::url()`).

---

## 7. Open-job dedup (essential)

Active job = a job linked to the bike whose status is **NOT** in the closed set.

Real status slugs (underscores) from `Job_Status_Taxonomy::status_slugs()`:
```
received, inspected, waiting_approval, approved, waiting_parts, parts_arrived,
in_repair, quality_check, ready, delivered, closed, declined, cancelled
```
Closed/terminal set (exclude from "active"): `delivered, closed, cancelled,
declined`. Everything else counts as an open job.

On the bike card, query `tcw_repair_job` with `meta_query bike_id = X` and a
`tax_query` excluding the closed set. If any active job exists, show the most
recent with a warning + "Open existing job" and an explicit "Create another
anyway". Prevents the classic "same bike, two open jobs" mistake.

> Implementation must use the exact underscore slugs above — never hyphenated
> variants — to avoid creating phantom statuses.

---

## 8. "Send received email?" toggle — exact behaviour

The toggle suppresses **only the client email**. It must NOT suppress job
creation, the `received` status, or the status-history/audit entry.

Mechanism (request-scoped, not global):
- `Job_Factory::create_for_bike( $bike_id, $problem, $status = 'received', $notify = true )`.
- When `$notify === false`, wrap the internal `Job_Status::set()` call:
  ```php
  add_filter( 'tcw_send_client_notification', '__return_false' );
  Job_Status::set( $job_id, 'received', get_current_user_id() );
  remove_filter( 'tcw_send_client_notification', '__return_false' );
  ```
- `Notifier::on_status_changed()` gains one guard:
  ```php
  if ( false === apply_filters( 'tcw_send_client_notification', true, $job_id, $to ) ) {
      return;
  }
  ```
Result: status changes, history is written, the `tcw_job_status_changed` action
still fires (future listeners unaffected) — only the email is skipped.

---

## 9. Scan-redirect setting (Phase 2)

New setting `scan_destination ∈ { intake, admin }`, **default `intake`**.
- `Scan_Router`: logged-in + `tcw_view_bikes` →
  `/workshop-intake/?bike={internal_id}` (default) or the admin edit screen.
- Bike card always offers **"Edit in admin"**, so nothing is lost.
- **Public/unauthenticated scan behaviour is unchanged** (minimal no-PII page).

---

## 10. Internationalization
- All UI strings via `__()`/`esc_html__()` (text domain `tossa-workshop`).
- Full ES translation added via `tools/i18n-extract.php` +
  `tools/build-translations.php`, as every milestone.

---

## 11. Phasing & acceptance criteria

### Phase 1 — Core intake
1. `Bike_Factory` (+ unit test: ID/QR/title/sanitized meta == admin-created).
2. `/workshop-intake/` route, auth + `tcw_view_bikes`/`tcw_edit_jobs` gating,
   standalone shell.
3. Mode 1 search (reuse `Bike_Search`) + Mode 2 bike card with **open-job
   warning** + last 3 jobs + full-history link.
4. Create job from existing bike → `Job_Factory::create_for_bike()` + extra
   fields + the "send received email" toggle (§8) → confirmation screen.

*Accept:* a logged-in Front Desk **or Mechanic** user opens `/workshop-intake/`,
searches a bike by serial/owner phone, sees its card + any open job, creates a
job that gets `TCW-…`, inherits client contact, lands in `received`, optionally
emails the client (toggle), and reaches confirmation. Logged-out → login;
lacks `tcw_view_bikes` → refusal. Plugin still works with the module disabled.

### Phase 2 — New bike, scan redirect, polish, dedup-on-create
5. Mode 3 create new bike + job (`Bike_Factory` + `Job_Factory`), gated by
   `tcw_edit_bikes`; shown only after a search returns nothing.
6. **Duplicate-bike warning** when creating: if the entered phone/email/serial
   already matches an existing bike, warn (non-blocking) with the match.
7. `scan_destination` setting; repoint logged-in scans to intake.
8. Controlled photo uploader (§5.3). Mobile polish: sticky submit, big targets,
   "Copy WhatsApp message".

### Phase 3 — Optional
9. In-browser camera scanning (`html5-qrcode`, lazy-loaded).

---

## 12. Resolved decisions (was: open questions)

- **Q1 scan default:** `intake` (with "Edit in admin" on the card). Public scan
  unchanged.
- **Q2 mechanics create new bikes:** **No** — gated by `tcw_edit_bikes`
  (Manager/Front Desk only). No new cap.
- **Q2b mechanics create jobs:** **Yes** — gated by `tcw_edit_jobs` (owner
  decision). No new cap.
- **Q3 photos:** none in Phase 1; controlled uploader in Phase 2 (no
  `upload_files` grant).
- **Q4 send-email toggle:** suppresses only the email via the
  `tcw_send_client_notification` filter; status/history/action preserved.
  Default checked.
- **Q5 recent jobs:** last 3 + "View full history".
- **Q6 rendering:** standalone, no theme header/footer.
- **Extra:** force search before "create new bike"; duplicate-bike warning in
  Phase 2.

---

## 13. Verification approach
- `php -l` + `node --check`.
- Unit tests (no WP runtime): `Bike_Factory` (ID/QR/title/meta), open-job query
  filter (correct slug set), capability matrix helper, send-email suppression
  filter.
- WP-stub activation smoke extended to load the new classes/route.
- Per-phase high-effort code review before commit.
- Real activation/scan/create-flow tested on staging by Tossa.
```
