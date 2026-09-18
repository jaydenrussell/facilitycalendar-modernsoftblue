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
 * The module's output is buffered so times can be normalized and event
 * links can be made SEF:
 *   - "12:00 AM" (an all-day placeholder) is shown as "All Day"
 *   - leading zeroes are trimmed ("08:30 AM" -> "8:30 AM")
 *   - every "index.php?..." href is routed through JRoute::_() so it renders
 *     root-relative and SEF (e.g. /club-events/event-registrations/bonspiel/230-...)
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
    <p><strong>[msb]</strong> Upstream layout not found: <code>mod_facilitycalendar_event_list/tmpl/default.php</code></p>
  <?php endif; ?>
  <?php return; ?>
<?php endif; ?>

<?php
/** Validate the upstream template path. Both sides are canonicalized with
realpath() before comparing: matching a resolved path against a raw base
breaks on symlinked, junction, or short-name docroots (e.g. atomic-deploy
"current" symlinks), which would silently disable the module exactly on
well-run infrastructure. Refuse only when something is genuinely unresolvable. */
$modTmplReal = realpath($modTmpl);
$modTmplDirReal = realpath(JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl');

if ($modTmplReal === false || $modTmplDirReal === false || strpos(str_replace('\\', '/', $modTmplReal), rtrim(str_replace('\\', '/', $modTmplDirReal), '/') . '/') !== 0) : ?>
  <?php if (Factory::getApplication()->get('debug')) : ?>
    <p><strong>[msb]</strong> Upstream layout path invalid or outside module directory: <code>mod_facilitycalendar_event_list/tmpl/default.php</code></p>
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

      // Without the DOM extension there is nothing safe to transform: output
      // upstream HTML untouched rather than fatal the whole module position.
      // (Logged at most once per request to avoid log spam on every page view.)
      static $msbLogged = false;
      $msbLogOnce = function ($message) use (&$msbLogged) {
          if ($msbLogged || !class_exists('JLog')) {
              return;
          }
          $msbLogged = true;
          \JLog::add('mod_facilitycalendar_event_list modernsoftblue layout: ' . $message, \JLog::WARNING, 'mod_facilitycalendar_event_list');
      };

      if (!class_exists('DOMDocument')) {
          $msbLogOnce('PHP DOM extension missing; serving unprocessed upstream output.');
          echo $html;
      } elseif (strlen($html) > $maxBufferSize) {
          $msbLogOnce('Upstream output exceeds ' . round($maxBufferSize / 1024) . 'KB limit; serving unprocessed output.');
          if (Factory::getApplication()->get('debug')) {
              echo '<p><strong>[msb]</strong> Upstream output exceeds ' . round($maxBufferSize / 1024) . 'KB limit; skipping post-processing to preserve memory.</p>';
          }
          echo $html;
      } else {
          // DOMDocument::loadHTML defaults to Latin-1 without charset info,
          // which corrupts non-ASCII event titles. Normalize to HTML entities
          // first so UTF-8 survives the round-trip; without mbstring, declare
          // the encoding instead (same guarantee, no extension required).
          if (function_exists('mb_convert_encoding')) {
              $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
          } else {
              $html = '<?xml encoding="UTF-8">' . $html;
          }

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

          // Route facility-calendar links through Joomla's router so event
          // hrefs render root-relative and SEF instead of raw query URLs.
          // Exact component match: a substring test would also accept
          // lookalike options such as com_facilitycalendar_evil.
          $linkNodes = $xpath->query('//a[@href]');
          foreach ($linkNodes as $link) {
              $href = trim($link->getAttribute('href'));
              if (stripos($href, 'index.php') !== 0 || !class_exists('JRoute')) {
                  continue;
              }
              $msbQuery = array();
              parse_str((string) parse_url($href, PHP_URL_QUERY), $msbQuery);
              if (!isset($msbQuery['option']) || $msbQuery['option'] !== 'com_facilitycalendar') {
                  continue;
              }
              $link->setAttribute('href', JRoute::_($href));
          }

          $body = $dom->getElementsByTagName('body')->item(0);
          $out = '';
          if ($body) {
              foreach ($body->childNodes as $child) {
                  $out .= $dom->saveHTML($child);
              }
          }

          // Structural check, not a length heuristic: routing hrefs through SEF
          // legitimately shifts total byte length, so byte-counting the output
          // would false-negative on link-heavy lists and silently disable the
          // feature. What must hold is structure: same links in, same links out.
          $msbInLinks = preg_match_all('~<a\s[^>]*href=~i', $html);
          $msbOutLinks = preg_match_all('~<a\s[^>]*href=~i', $out);
          if ($out !== '' && $msbInLinks !== false && $msbOutLinks === $msbInLinks) {
              echo $out;
          } else {
              // Fallback serves upstream output untouched rather than a mangled
              // card. Logged so the skip is visible instead of silent.
              $msbLogOnce('DOM transform diverged from upstream output; serving unprocessed output.');
              echo $html;
          }
      }
      ?>
    </div>
  </section>
</div>
