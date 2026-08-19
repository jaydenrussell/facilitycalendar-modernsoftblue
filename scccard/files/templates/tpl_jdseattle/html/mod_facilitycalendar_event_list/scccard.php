<?php
/**
 * Facility Calendar Event List — SCC card layout override (Joomla 3)
 * --------------------------------------------------------------------------------------
 * Wraps the module's original tmpl/default.php in the SCC card shell with the
 * modern events skin: light rounded date tiles, hairline row dividers, semibold
 * titles, and secondary muted times with a clock icon.
 *
 * Select this layout from the admin (does NOT apply automatically):
 *   Admin -> Extensions -> Modules -> "Facility Calendar Event List"
 *     Advanced tab -> Module Layout = "scccard"  (under the "tpl_jdseattle" group)
 *
 * The "Module Layout" dropdown is enabled by adding the layout field to the
 * module's own manifest, modules/mod_facilitycalendar_event_list/
 * mod_facilitycalendar_event_list.xml (same mechanism as the core Menu module).
 *
 * Reverting: set Module Layout back to "Default" in the same dropdown.
 *
 * The module's output is buffered so times can be normalized:
 *   - "12:00 AM" (an all-day placeholder) is shown as "All Day"
 *   - leading zeroes are trimmed ("08:30 AM" -> "8:30 AM")
 *
 * Module settings:
 *   Basic tab -> Show Title = "Hide"   (the card renders its own title; keeping
 *   "Show" duplicates it with the template's own module header)
 *
 * Also upload  scc-calendar-badge.png  to  /images/scc-calendar-badge.png
 */
defined('_JEXEC') or die;

$scc_id   = 'scc' . substr(md5(uniqid()), 0, 10);
$modTmpl  = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/tmpl/default.php';
?>
<style>
#<?php echo $scc_id; ?> .scc-card {
  background:#ffffff;
  border:1px solid #e6eef5;
  border-radius:16px;
  box-shadow:0 10px 24px rgba(17,24,39,0.08);
  padding:1.2rem 1.25rem;
  margin:0 0 1.5rem 0;
  overflow:visible;
  position:relative;
}
#<?php echo $scc_id; ?> .scc-card-title {
  position:relative;
  font-size:1.05rem;
  font-weight:700;
  letter-spacing:.2px;
  color:#15324a;
  text-transform:none;
  margin:0 0 .7rem 0;
  padding:0 0 .55rem .7rem;
  border-bottom:1px solid #e6ecf0 !important;
}
#<?php echo $scc_id; ?> .scc-card-title::before {
  content:"";
  position:absolute;
  left:0; top:.1em;
  height:1.15em; width:4px;
  border-radius:3px;
  background:#1890d7;
}
#<?php echo $scc_id; ?> .scc-card--events::after {
  content:"";
  position:absolute; top:-16px; right:14px;
  width:52px; height:52px;
  background-image:url("/images/scc-calendar-badge.png");
  background-size:contain; background-repeat:no-repeat;
  transform:rotate(9deg);
  filter:drop-shadow(0 6px 10px rgba(17,24,39,0.25));
  pointer-events:none;
}
#<?php echo $scc_id; ?> .facility-upcoming-events {
  list-style:none; margin:0 !important; padding:0 !important;
}
#<?php echo $scc_id; ?> .facility-upcoming-events li.row {
  display:flex !important; flex-wrap:nowrap !important;
  align-items:center; gap:.8rem;
  padding:.4rem .1rem !important; margin:0 !important;
  border-bottom:0 !important;
}
#<?php echo $scc_id; ?> .facility-upcoming-events + .facility-upcoming-events li.row {
  border-top:1px solid #eef3f8 !important;
}
#<?php echo $scc_id; ?> .facility-col-1 {
  float:none !important; width:auto !important; flex:0 0 auto;
}
#<?php echo $scc_id; ?> .facility-event-date {
  width:54px !important; height:58px !important; flex-shrink:0;
  background:#f2f7fc !important; border:1px solid #e1ecf5 !important; border-radius:12px !important;
  display:flex !important; flex-direction:column !important; align-items:center; justify-content:center;
  box-sizing:border-box;
  transition:transform .15s ease, box-shadow .15s ease;
}
#<?php echo $scc_id; ?> .facility-upcoming-events li.row:hover .facility-event-date {
  transform:translateY(-2px);
  box-shadow:0 4px 10px rgba(24,144,215,0.15);
}
#<?php echo $scc_id; ?> .facility-event-month {
  font-size:10px !important; font-weight:700 !important; letter-spacing:1.4px !important;
  text-transform:uppercase !important; color:#1890d7 !important; line-height:1 !important;
  background:transparent !important; border:0 !important;
  margin:0 !important; padding:0 !important;
}
#<?php echo $scc_id; ?> .facility-event-day {
  font-size:22px !important; font-weight:700 !important; color:#1c2b3a !important;
  line-height:1.05 !important; margin-top:3px !important;
  background:transparent !important; border:0 !important;
}
#<?php echo $scc_id; ?> .facility-col-2 { min-width:0; }
#<?php echo $scc_id; ?> .facility-col-2 > div:first-child {
  font-size:.95rem; font-weight:600; color:#1c2b3a; line-height:1.3;
}
#<?php echo $scc_id; ?> .facility-col-2 a { color:#1890d7; text-decoration:none; }
#<?php echo $scc_id; ?> .facility-col-2 a:hover { text-decoration:underline; }
#<?php echo $scc_id; ?> .facility-col-2 a::after {
  content:"\203A"; margin-left:4px; color:#1890d7; font-weight:700;
}
#<?php echo $scc_id; ?> .facility-event-time {
  margin-top:.18rem; font-size:.7rem; font-weight:600;
  letter-spacing:.5px; text-transform:uppercase; color:#7a8ba0;
  display:flex; align-items:center; gap:.32rem;
  line-height:1 !important;
}
#<?php echo $scc_id; ?> .facility-event-time::before {
  content:""; width:11px; height:11px; flex-shrink:0;
  background:url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"%3E%3Ccircle cx="8" cy="8" r="6.4" fill="none" stroke="%237a8ba0" stroke-width="1.4"/%3E%3Cpath d="M8 4.4V8l2.6 1.6" fill="none" stroke="%237a8ba0" stroke-width="1.4" stroke-linecap="round"/%3E%3C/svg%3E') center/contain no-repeat;
}
</style>

<div id="<?php echo $scc_id; ?>">
  <section class="scc-card scc-card--events">
    <?php if (trim($module->title) !== '') : ?>
      <h3 class="scc-card-title"><?php echo $module->title; ?></h3>
    <?php endif; ?>
    <div class="scc-card-body">
      <?php
      ob_start();
      require $modTmpl;
      $html = ob_get_clean();

      $html = preg_replace_callback(
        '~(<div class="facility-event-time">)\s*(.*?)\s*(</div>)~s',
        function ($m) {
          $t = trim($m[2]);

          if (preg_match('~^12:00\s*AM$~i', $t)) {
            $t = 'All Day';
          } else {
            $t = preg_replace('~^0(?=\d)~', '', $t);
          }

          return $m[1] . $t . $m[3];
        },
        $html
      );

      echo $html;
      ?>
    </div>
  </section>
</div>
