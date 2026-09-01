# Facility Calendar — Remove Old Package (cleanup helper)

A small, one-time installable package that removes the **orphaned old** package
(`pkg_facilitycalendar_modernsoftblue`) from your Joomla `#__extensions` table.

## Why you need it

After the package was renamed to `facilitycalendar-upcomingeventlist-modernsoftblue`,
Joomla treats the new install as a *different* extension. Any site that had the old
package installed is left with a **stranded row** that:

- won't uninstall (its recorded file manifest no longer matches disk), and
- won't update (its update server no longer matches the new element).

The cleanest fix is to delete that one stale database row. This helper does exactly
that **using the site's own database connection** (via `configuration.php`) — so you
ONLY need Joomla admin access. No phpMyAdmin, no host cPanel, no FTP.

## Build the zip

From this folder:

```bash
cd tools/old-package-cleanup
zip -r ../pkg_facilitycalendar-msb-old-cleanup.zip .
```

## Install (Joomla 3)

1. **Extensions → Manage → Install → Upload Package File** → select
   `pkg_facilitycalendar-msb-old-cleanup.zip`, then press **Upload & Install**.
2. The installer automatically deletes the old package row and shows a confirmation
   message.
3. Open **Extensions → Manage → Manage** — the old
   "Facility Calendar — Modern Soft Blue" entry is gone.
4. Install (or update) the new package:
   **Facility Calendar Upcoming Event List — Modern Soft Blue**.
5. Optionally uninstall this helper, then **delete the zip from your computer**.

## Safety

- Only touches the row with `element = 'pkg_facilitycalendar_modernsoftblue'` and
  `type = 'package'` (plus its matching `#__schemas` row).
- Does **NOT** touch the newer package or any files on disk.
- Idempotent: safe to install again if the row is already gone.
