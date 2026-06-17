# Tossa Workshop

Workshop management for Tossa Cycling — a single self-contained WordPress plugin.

- **Slug / text domain:** `tossa-workshop`
- **PHP namespace:** `TossaWorkshop\`
- **Prefix:** `tcw_` (functions, options, meta, capabilities)
- **Requires:** WordPress 6.4+, PHP 8.1+
- **Optional:** WooCommerce (detected with `class_exists('WooCommerce')`; the plugin never fatals if it is absent)

## Architecture (fixed)

- Data store: Custom Post Types + post meta. No custom DB tables.
- `tcw_bike` — bikes (customer **and** fleet, distinguished by `bike_type`).
- `tcw_repair_job` — repair jobs, linked to a bike.
- `tcw_job_status` — non-hierarchical taxonomy on jobs; current status is the single assigned term, full history in the `status_history` meta.

## Roles & capabilities

Three roles are created on activation and granted semantic + CPT capabilities;
the Administrator role receives every capability.

| Role | Slug | Summary |
| --- | --- | --- |
| Workshop Manager | `tcw_workshop_manager` | Full access incl. prices, closing jobs, settings |
| Workshop Mechanic | `tcw_mechanic` | Work on jobs, change status (gated to `quality_check`), no prices/closing/settings |
| Workshop Front Desk | `tcw_front_desk` | Create bikes & jobs, intake, notifications, mark collected; no full financials |

Semantic caps: `tcw_view_bikes`, `tcw_edit_bikes`, `tcw_edit_jobs`,
`tcw_change_status`, `tcw_edit_prices`, `tcw_close_jobs`, `tcw_manage_settings`.

## Reserved front-end paths

These slugs are reserved by the plugin and must not collide with WP Pages:

- `/workshop-scan/?id={internal_id}` — staff scan router (added in M1)
- `/workshop-status/?job={job_id}&token={client_token}` — client status page (added in M4)

Public links are built from `home_url()` by default, overridable via the
`public_base_url` setting (Workshop → Settings).

## Repository layout

The plugin lives at the **repository root** (`tossa-workshop.php` is the main
file). This is deliberate so the repo works with
[git-plugin-loader](https://github.com/LukaszKomarTC/git-plugin-loader), which
clones the whole repo into `wp-content/plugins/<repo>/` and relies on
WordPress's `get_plugins()` — which only scans one directory deep. A nested
`tossa-workshop/` subfolder would hide the plugin header and make the plugin
impossible to activate.

For a conventional manual upload (Plugins → Add New → Upload), zip the repo
contents into a `tossa-workshop/` wrapper folder first.

## Development / verification

There is no WordPress runtime in the build container, so verification is:

- `php -l` syntax lint across all PHP files.
- Standalone tests (no WordPress required):

  ```sh
  php tests/test-id-generator.php   # ID format / sequencing / uniqueness
  php tests/activation-smoke.php    # boots plugin + activator under WP stubs
  ```

Activation, CPT/taxonomy registration, QR generation and the scan/status
routers must still be smoke-tested on a real WordPress staging site.

## Milestones

M0 Scaffold · M1 Bikes · M2 Repair jobs · M3 Notifications · M4 Client status
page · M5 Dashboards · M6 Optional extras. Built in order; each milestone is
self-tested against its acceptance criteria before the next begins.
