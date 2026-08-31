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

## Installation

### 1. Build or download the zip

```bash
cd facilitycalendar-modernsoftblue
zip -r ../facilitycalendar-modernsoftblue.zip .
```

Or download `facilitycalendar-modernsoftblue.zip` from the [Releases](https://github.com/jaydenrussell/scccard-layout/releases) page.

### 2. Install in Joomla

**Extensions → Manage → Install → Upload Package File** → select `facilitycalendar-modernsoftblue.zip`.

The package automatically:
- Installs the `modernsoftblue.php` theme to `templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/`
- Patches the module's XML manifest to add the **Module Layout** dropdown (idempotent — safe to re-install)
- Copies `scc-calendar-badge.png` to `/images/` (if not already present)

Post-install messages will confirm what was done.

### 3. Select the theme

**Extensions → Modules → Facility Calendar Event List → Advanced tab → Module Layout = modernsoftblue**

Set **Show Title** to **Hide** (the card renders its own title — "Show" creates duplicates). Save & close.

### 4. Clear cache

**System → Clear Cache** → purge Astroid compiled CSS. Hard-refresh (`Ctrl+F5`) the front-end.

## Reverting

In the module's **Advanced** tab, set **Module Layout** back to **Default**. Original appearance is restored immediately.

## What the installer does

The post-install script (`script.php`) runs automatically:

1. Locates `modules/mod_facilitycalendar_event_list/mod_facilitycalendar_event_list.xml`
2. Checks if the `layout` field (type `modulelayout`) already exists — if so, skips
3. If not, injects the field into the `<fieldset name="advanced">` block
4. Reports success or warns if the module wasn't found

This is the same mechanism used by Joomla's core `mod_menu` module. If the module is updated and its manifest is overwritten, simply re-install this package — the script will re-apply the patch.

## File Structure

```
facilitycalendar-modernsoftblue/
├── facilitycalendar-modernsoftblue.xml   # Package manifest
├── script.php                            # Post-install script (auto-patches module manifest)
├── update.xml                            # Joomla update server XML
└── modernsoftblue/
    ├── modernsoftblue.xml                # File extension manifest
    └── files/
        ├── images/
        │   └── scc-calendar-badge.png
        └── templates/
            └── tpl_jdseattle/
                └── html/
                    └── mod_facilitycalendar_event_list/
                        └── modernsoftblue.php
```

## License

GNU General Public License version 2 or later.
