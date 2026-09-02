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

if (!function_exists('msb_facilitycalendar_wrapper_id')) {
    function msb_facilitycalendar_wrapper_id(): string
    {
        return 'msb-facilitycalendar';
    }
}

/** Absolute path to the upstream module's default layout */
$modTmpl = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/default.php';

/** Cache-busting query string based on the CSS file's mtime so browsers/CDNs fetch a fresh copy after a version update. */
$cssPath = JPATH_BASE . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/modernsoftblue.css';
if (file_exists($cssPath)) {
    clearstatcache(true, $cssPath);
    $cssMtime = filemtime($cssPath);
} else {
    $cssMtime = '1.5.5';
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
$modTmplDir = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/';

if ($modTmplReal === false || strpos(str_replace('\\', '/', $modTmplReal), str_replace('\\', '/', $modTmplDir)) !== 0) : ?>
  <?php if (Factory::getApplication()->get('debug')) : ?>
    <p><strong>[msb]</strong> Upstream layout path invalid or outside module directory: <code><?php echo htmlspecialchars($modTmpl, ENT_QUOTES, 'UTF-8'); ?></code></p>
  <?php endif; ?>
  <?php return; ?>
<?php endif; ?>

<div id="<?php echo msb_facilitycalendar_wrapper_id(); ?>">
  <section class="msb-card msb-card--events">
    <?php if (trim($module->title) !== '') : ?>
      <h3 class="msb-card-title"><?php echo htmlspecialchars($module->title, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>
    <div class="msb-card-body">
      <?php
      $memLimit = ini_get('memory_limit');
      $memBytes = ($memLimit === '' || $memLimit === '-1') ? 256 * 1024 * 1024 : (int)$memLimit * 1024 * 1024;
      $maxBufferSize = (int)min(max($memBytes * 0.4, 256 * 1024), 2 * 1024 * 1024);

      ob_start();
      require $modTmplReal;
      $html = ob_get_clean();

      if (strlen($html) > $maxBufferSize) {
          if (Factory::getApplication()->get('debug')) {
              echo '<p><strong>[msb]</strong> Upstream output exceeds ' . round($maxBufferSize / 1024) . 'KB limit; skipping time normalization to preserve memory.</p>';
          }
          echo $html;
      } else {
          $dom = new DOMDocument();
          $dom->preserveWhiteSpace = true;
          $dom->formatOutput = false;
          $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

          $xpath = new DOMXPath($dom);
          $timeNodes = $xpath->query('//*[contains(@class, "facility-event-time")]');

          foreach ($timeNodes as $node) {
              $t = trim($node->textContent);

              if (preg_match('~^\d{1,2}:\d{2}\s*(AM|PM)$~i', $t)) {
                  if (preg_match('~^12:00\s*AM$~i', $t)) {
                      $t = 'All Day';
                  } else {
                      $t = preg_replace('~^0(?=\d)~', '', $t);
                  }
                  $node->textContent = $t;
              }
          }

          $body = $dom->getElementsByTagName('body')->item(0);
          $out = '';
          if ($body) {
              foreach ($body->childNodes as $child) {
                  $out .= $dom->saveHTML($child);
              }
          }

          $originalLength = strlen($html);
          $transformedLength = strlen($out);
          if ($out !== '' && $originalLength > 0 && abs($originalLength - $transformedLength) <= $originalLength * 0.2) {
              echo $out;
          } else {
              echo $html;
          }
      }
      ?>
    </div>
  </section>
</div>