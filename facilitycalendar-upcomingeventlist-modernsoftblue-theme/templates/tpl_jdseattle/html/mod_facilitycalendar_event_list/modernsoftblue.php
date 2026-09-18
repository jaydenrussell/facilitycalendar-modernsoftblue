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
 * Conventions (do not regress):
 *   - All layout locals use the msb prefix. This file is included into
 *     Joomla's scope, and generic local names collide with includer
 *     variables (proven once already by a test harness).
 *   - The card wrapper id is unique per module instance on the page.
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
    // Unique per module instance on the page: duplicate ids are invalid HTML
    // and break fragment/JS targeting. The first instance keeps the bare id.
    function msb_facilitycalendar_wrapper_id(): string
    {
        static $msbInstance = 0;
        $msbInstance++;
        return $msbInstance > 1 ? 'msb-facilitycalendar-' . $msbInstance : 'msb-facilitycalendar';
    }
}

/** Absolute path to the upstream module's default layout */
$msbModTmpl = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/default.php';

/** Cache-busting query string based on the CSS file's mtime so browsers/CDNs fetch a fresh copy after a version update. */
$msbCssPath = JPATH_BASE . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/modernsoftblue.css';
if (file_exists($msbCssPath)) {
    clearstatcache(true, $msbCssPath);
    $msbCssMtime = filemtime($msbCssPath);
} else {
    $msbCssMtime = '1.5.5';
}

HTMLHelper::stylesheet(
    'mod_facilitycalendar_upcomingeventlist_modernsoftblue/modernsoftblue.css?' . $msbCssMtime,
    ['relative' => true, 'detectDebug' => false]
);

/** Guard: fatal error is worse than a graceful skip */
if (!file_exists($msbModTmpl)) : ?>
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
$msbModTmplReal = realpath($msbModTmpl);
$msbModTmplDirReal = realpath(JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl');

if ($msbModTmplReal === false || $msbModTmplDirReal === false || strpos(str_replace('\\', '/', $msbModTmplReal), rtrim(str_replace('\\', '/', $msbModTmplDirReal), '/') . '/') !== 0) : ?>
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
      $msbMemLimit = ini_get('memory_limit');
      $msbMemBytes = ($msbMemLimit === '' || $msbMemLimit === '-1') ? 256 * 1024 * 1024 : (int)$msbMemLimit * 1024 * 1024;
      $msbMaxBufferSize = (int)min(max($msbMemBytes * 0.4, 256 * 1024), 2 * 1024 * 1024);

      ob_start();
      require $msbModTmplReal;
      $msbHtml = ob_get_clean();

      // Without the DOM extension there is nothing safe to transform: output
      // upstream HTML untouched rather than fatal the whole module position.
      // (Logged at most once per request to avoid log spam on every page view.)
      static $msbLogged = false;
      $msbLogOnce = function ($message) use (&$msbLogged) {
          if ($msbLogged || !class_exists('JLog')) {
              return;
          }
          $msbLogged = true;
          // Logging must never crash the render path: if the log subsystem
          // itself is broken, drop the message silently (last resort).
          try {
              \JLog::add('mod_facilitycalendar_event_list modernsoftblue layout: ' . $message, \JLog::WARNING, 'mod_facilitycalendar_event_list');
          } catch (\Throwable $msbLogError) {
          }
      };

      if (!class_exists('DOMDocument')) {
          $msbLogOnce('PHP DOM extension missing; serving unprocessed upstream output.');
          echo $msbHtml;
      } elseif (strlen($msbHtml) > $msbMaxBufferSize) {
          $msbLogOnce('Upstream output exceeds ' . round($msbMaxBufferSize / 1024) . 'KB limit; serving unprocessed output.');
          if (Factory::getApplication()->get('debug')) {
              echo '<p><strong>[msb]</strong> Upstream output exceeds ' . round($msbMaxBufferSize / 1024) . 'KB limit; skipping post-processing to preserve memory.</p>';
          }
          echo $msbHtml;
      } else {
          // DOMDocument::loadHTML defaults to Latin-1 without charset info,
          // which corrupts non-ASCII event titles. Normalize to HTML entities
          // first so UTF-8 survives the round-trip; without mbstring, declare
          // the encoding instead (same guarantee, no extension required).
          if (function_exists('mb_convert_encoding')) {
              $msbHtml = mb_convert_encoding($msbHtml, 'HTML-ENTITIES', 'UTF-8');
          } else {
              $msbHtml = '<?xml encoding="UTF-8">' . $msbHtml;
          }

          $msbDom = new DOMDocument();
          $msbDom->preserveWhiteSpace = true;
          $msbDom->formatOutput = false;
          $msbDom->loadHTML($msbHtml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

          $msbXpath = new DOMXPath($msbDom);
          $msbTimeNodes = $msbXpath->query('//*[contains(@class, "facility-event-time")]');

          foreach ($msbTimeNodes as $msbNode) {
              $msbT = trim($msbNode->textContent);

              if (preg_match('~^\d{1,2}:\d{2}\s*(AM|PM)$~i', $msbT)) {
                  if (preg_match('~^12:00\s*AM$~i', $msbT)) {
                      $msbT = 'All Day';
                  } else {
                      $msbT = preg_replace('~^0(?=\d)~', '', $msbT);
                  }
                  $msbNode->textContent = $msbT;
              }
          }

          // Route facility-calendar links through Joomla's router so event
          // hrefs render root-relative and SEF instead of raw query URLs.
          // Exact component match: a substring test would also accept
          // lookalike options such as com_facilitycalendar_evil.
          $msbLinkNodes = $msbXpath->query('//a[@href]');
          foreach ($msbLinkNodes as $msbLink) {
              $msbHref = trim($msbLink->getAttribute('href'));
              if (stripos($msbHref, 'index.php') !== 0 || !class_exists('JRoute')) {
                  continue;
              }
              $msbQuery = array();
              parse_str((string) parse_url($msbHref, PHP_URL_QUERY), $msbQuery);
              if (!isset($msbQuery['option']) || $msbQuery['option'] !== 'com_facilitycalendar') {
                  continue;
              }
              try {
                  $msbLink->setAttribute('href', JRoute::_($msbHref));
              } catch (\Exception $msbRouteError) {
                  // A router failure must never white-screen the module:
                  // keep the original href and report once per request.
                  $msbLogOnce('Router rejected an event link; original href kept.');
              }
          }

          $msbBody = $msbDom->getElementsByTagName('body')->item(0);
          $msbOut = '';
          if ($msbBody) {
              foreach ($msbBody->childNodes as $msbChild) {
                  $msbOut .= $msbDom->saveHTML($msbChild);
              }
          }

          // Structural check, not a length heuristic: routing hrefs through SEF
          // legitimately shifts total byte length, so byte-counting the output
          // would false-negative on link-heavy lists and silently disable the
          // feature. What must hold is structure: same links in, same links out.
          $msbInLinks = preg_match_all('~<a\s[^>]*href=~i', $msbHtml);
          $msbOutLinks = preg_match_all('~<a\s[^>]*href=~i', $msbOut);
          if ($msbOut !== '' && $msbInLinks !== false && $msbOutLinks === $msbInLinks) {
              echo $msbOut;
          } else {
              // Fallback serves upstream output untouched rather than a mangled
              // card. Logged so the skip is visible instead of silent.
              $msbLogOnce('DOM transform diverged from upstream output; serving unprocessed output.');
              echo $msbHtml;
          }
      }
      ?>
    </div>
  </section>
</div>
