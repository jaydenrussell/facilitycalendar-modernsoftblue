<?php
/**
 * Facility Calendar Upcoming Event List — Modern Soft Blue layout override (Joomla 3)
 * --------------------------------------------------------------------------------------
 * Wraps the module's original tmpl/default.php in a card shell with the
 * Modern Soft Blue skin: light rounded date tiles, hairline row dividers,
 * semibold titles, and muted times with a clock icon.
 *
 * Select this layout from the admin (does NOT apply automatically):
 *   Extensions → Modules → mod_facilitycalendar_event_list
 *     Advanced tab → Module Layout = "modernsoftblue"
 *
 * Reverting: set Module Layout back to "Default" in the same dropdown.
 *
 * The module's output is buffered so times can be normalized:
 *   - "12:00 AM" (an all-day placeholder) is shown as "All Day"
 *   - leading zeroes are trimmed ("08:30 AM" -> "8:30 AM")
 *
 * Module settings:
 *   Basic tab → Show Title = "Hide"   (the card renders its own title; "Show"
 *   duplicates it with the template's own module header)
 *
 * Styles are loaded from the Joomla media system via the document API,
 * not embedded inline, so they can be cached by the browser/CDN.
 * `detectDebug => false` suppresses the built-in minified-file lookup (we
 * ship only modernsoftblue.css; debug mode does not require a .min variant).
 * A cache-busting query string based on the CSS file's mtime is appended
 * so browsers/CDNs fetch a fresh copy after a version update.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/** Scoped wrapper ID — deterministic, no randomness needed */
$msb_id = 'msb-facilitycalendar';

/** Absolute path to the upstream module's default layout */
$modTmpl = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/default.php';

/** Cache-busting query string based on the CSS file's mtime so browsers/CDNs fetch a fresh copy after a version update. */
$cssPath = JPATH_BASE . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/modernsoftblue.css';
if (file_exists($cssPath)) {
    clearstatcache(true, $cssPath);
    $cssMtime = filemtime($cssPath);
} else {
    $cssMtime = '1.5.4';
}

HTMLHelper::stylesheet(
    'mod_facilitycalendar_upcomingeventlist_modernsoftblue/modernsoftblue.css?' . $cssMtime,
    ['relative' => true, 'detectDebug' => false]
);

/** Guard: fatal error is worse than a graceful skip */
if (!file_exists($modTmpl)) : ?>
  <?php if (Factory::getApplication()->get('debug')) : ?>
    <p><strong>[msb]</strong> Upstream layout not found: <code><?php echo htmlspecialchars($modTmpl, ENT_QUOTES, 'UTF-8'); ?></code></p>
  <?php endif; ?>
  <?php return; ?>
<?php endif; ?>

/** Validate the upstream template path: must be a regular file within the upstream module's tmpl directory, not a symlink or device. */
$modTmplReal = realpath($modTmpl);
if ($modTmplReal === false || strpos($modTmplReal, JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/') !== 0) : ?>
  <?php if (Factory::getApplication()->get('debug')) : ?>
    <p><strong>[msb]</strong> Upstream layout path invalid or outside module directory: <code><?php echo htmlspecialchars($modTmpl, ENT_QUOTES, 'UTF-8'); ?></code></p>
  <?php endif; ?>
  <?php return; ?>
<?php endif; ?>

<div id="<?php echo $msb_id; ?>">
  <section class="msb-card msb-card--events">
    <?php if (trim($module->title) !== '') : ?>
      <h3 class="msb-card-title"><?php echo htmlspecialchars($module->title, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>
    <div class="msb-card-body">
      <?php
      ob_start();
      require $modTmplReal;
      $html = ob_get_clean();

      // Limit buffer size to prevent memory exhaustion on cheap shared hosting.
      // Skip normalization if upstream output exceeds 512KB; output raw HTML instead.
      $maxBufferSize = 512 * 1024;
      if (strlen($html) <= $maxBufferSize) {
          $dom = new DOMDocument();
          $dom->preserveWhiteSpace = true;
          $dom->formatOutput = false;
          $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

          $xpath = new DOMXPath($dom);
          $timeNodes = $xpath->query('//*[contains(@class, "facility-event-time")]');

          foreach ($timeNodes as $node) {
              $t = trim($node->textContent);

              if (preg_match('~^12:00\s*AM$~i', $t)) {
                  $t = 'All Day';
              } else {
                  $t = preg_replace('~^0(?=\d)~', '', $t);
              }

              $node->textContent = $t;
          }

          // Serialize body children only to avoid doctype/html/body wrappers
          $body = $dom->getElementsByTagName('body')->item(0);
          $out = '';
          if ($body) {
              foreach ($body->childNodes as $child) {
                  $out .= $dom->saveHTML($child);
              }
          }

          if ($out !== '') {
              echo $out;
          } else {
              echo $html;
          }
      } else {
          if (Factory::getApplication()->get('debug')) {
              echo '<p><strong>[msb]</strong> Upstream output exceeds 512KB limit; skipping time normalization to preserve memory.</p>';
          }
          echo $html;
      }
      ?>
    </div>
  </section>
</div>
