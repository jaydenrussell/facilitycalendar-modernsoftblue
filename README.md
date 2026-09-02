# Facility Calendar Upcoming Event List — Modern Soft Blue

A modern, card-style theme for the **mod_facilitycalendar_event_list** Joomla module's upcoming event list output. One install — everything is handled automatically.

**Tested on:** Joomla 3.10.12 · tpl\_jdseattle (JD Seattle / Astroid)

---

## Features

- Light rounded date tiles (month in club blue, bold day)
- Hairline row dividers between events
- Semibold dark titles with blue accent bar
- Secondary muted times with clock icon
- Time normalization: `12:00 AM` → "All Day", leading zeroes trimmed
- Badge PNG on hover lift effect
- Scoped CSS — won't leak to other modules

## Requirements

- **mod\_facilitycalendar\_event\_list** must already be installed on the site.
- Joomla 3.8.0 or later
- PHP 7.4.0 or later

## Installation

### 1. Build or download the zip

```bash
cd facilitycalendar-modernsoftblue
zip -r ../facilitycalendar-upcomingeventlist-modernsoftblue-v1.5.4.zip .
```

Or download `facilitycalendar-upcomingeventlist-modernsoftblue-v1.5.4.zip` from the [Releases](https://github.com/jaydenrussell/facilitycalendar-modernsoftblue/releases) page.

### 2. Install in Joomla

**Extensions → Manage → Install → Upload Package File** → select `facilitycalendar-upcomingeventlist-modernsoftblue-v1.5.4.zip`.

The package automatically:
- Installs the `modernsoftblue.php` layout to `templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/`
- Installs the `modernsoftblue.css` stylesheet and badge to the Joomla media folder
- Patches the module's XML manifest to add the **Module Layout** dropdown (idempotent — safe to re-install)
- Checks for `scc-calendar-badge.png` in the media folder and warns if missing

Post-install messages will confirm what was done.

### 3. Select the theme

**Extensions → Modules → Facility Calendar Event List → Advanced tab → Module Layout = modernsoftblue**

Set **Show Title** to **Hide** (the card renders its own title — "Show" creates duplicates). Save & close.

### 4. Clear cache

**System → Clear Cache** → purge Astroid compiled CSS. Hard-refresh (`Ctrl+F5`) the front-end.

## Reverting

In the module's **Advanced** tab, set **Module Layout** back to **Default**. Original appearance is restored immediately.

## Uninstalling

Uninstalling this package will automatically revert the module manifest patch, restoring the original manifest without the layout field. You can then safely uninstall `mod_facilitycalendar_event_list` if desired.

## What the installer does

The post-flight script (`script.php`) runs after install and update:

1. Validates minimum Joomla (3.8.0) and PHP (7.4.0) versions
2. Locates `modules/mod_facilitycalendar_event_list/mod_facilitycalendar_event_list.xml`
3. Checks if the `layout` field (type `modulelayout`) already exists — if so, skips
4. If not, injects the field into the `<fieldset name="advanced">` block using DOMDocument
5. Backs up the original manifest before writing
6. Reports success or warns if the module wasn't found

If the upstream module is updated and its manifest is overwritten, simply re-install this package — the script will re-apply the patch.

## File Structure

```
facilitycalendar-modernsoftblue/
├── facilitycalendar-modernsoftblue.xml   # Package manifest
├── script.php                            # Installer script (preflight, postflight, uninstall)
├── update.xml                            # Joomla update server XML
├── CHANGELOG.xml                         # Joomla update changelog
└── modernsoftblue/
    ├── modernsoftblue.xml                # File extension manifest
    └── files/
        ├── language/
        │   └── en-GB/
        │       ├── en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.ini
        │       └── en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.sys.ini
        ├── media/
        │   └── mod_facilitycalendar_upcomingeventlist_modernsoftblue/
        │       ├── modernsoftblue.css
        │       ├── scc-calendar-badge.png
        │       └── clock.svg
        └── templates/
            └── tpl_jdseattle/
                └── html/
                    └── mod_facilitycalendar_event_list/
                        ├── modernsoftblue.php
                        └── index.html
```

## License

GNU General Public License version 2 or later.
