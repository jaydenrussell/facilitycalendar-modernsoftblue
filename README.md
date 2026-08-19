# SCC Card Layout for Facility Calendar Event List

A modern, card-style layout override for the **mod_facilitycalendar_event_list** Joomla module. Selectable from the admin Module Layout dropdown — no plugins required.

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

1. The **mod\_facilitycalendar\_event_list** module must already be installed.
2. The module's XML manifest must include the **Module Layout** dropdown (see [Module Manifest Patch](#module-manifest-patch) below).
3. Upload `scc-calendar-badge.png` to `/images/` on your Joomla site (optional — the card renders without it).

## Installation

### Build the zip

From the repo root:

```bash
cd pkg_scccard
zip -r ../pkg_scccard.zip .
```

Or download the pre-built `pkg_scccard.zip` from the Releases page.

### Install in Joomla

1. Go to **Extensions → Manage → Install**.
2. Click **Upload Package File** and select `pkg_scccard.zip`.
3. The package installs the override file to:
   ```
   templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/scccard.php
   ```

### Select the layout

1. Go to **Extensions → Modules → Facility Calendar Event List**.
2. Open the **Advanced** tab.
3. Set **Module Layout** to **scccard**.
4. Set **Show Title** to **Hide** (the card renders its own title — keeping "Show" creates duplicates).
5. Save & close.

### Clear cache

After installing, go to **System → Clear Cache** and purge the Astroid compiled CSS. Hard-refresh (`Ctrl+F5`) the front-end.

## Module Manifest Patch

The module's default XML does **not** include a layout dropdown. The package installs the override file but does **not** modify the module's manifest (to avoid conflicts during module updates).

You must add the layout field manually (one-time edit):

**File:** `modules/mod_facilitycalendar_event_list/mod_facilitycalendar_event_list.xml`

Find:

```xml
<fieldset name="advanced">
```

Add immediately after:

```xml
<field
    name="layout"
    type="modulelayout"
    label="JLayout Render Layout"
    description="J_LAYOUT_RENDER_LAYOUT_DESC"
    default="_:default"
/>
```

This is the same mechanism used by the core `mod_menu` module. It adds a **Module Layout** dropdown under the Advanced tab. If the module is updated and the manifest is overwritten, re-apply this patch.

## Reverting

In the module's **Advanced** tab, set **Module Layout** back to **Default**. The module returns to its original appearance immediately.

## File Structure

```
pkg_scccard/
├── pkg_scccard.xml                          # Package manifest
└── scccard/
    ├── scccard.xml                          # File extension manifest
    └── files/
        └── templates/
            └── tpl_jdseattle/
                └── html/
                    └── mod_facilitycalendar_event_list/
                        └── scccard.php      # The override
```

## License

GNU General Public License version 2 or later.
