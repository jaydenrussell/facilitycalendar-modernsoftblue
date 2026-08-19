<?php
/**
 * SCC Card Layout — post-install script
 *
 * Runs automatically after the package is installed.
 * Patches mod_facilitycalendar_event_list's manifest to add the
 * Module Layout dropdown (the layout field needed to select "scccard").
 */
defined('_JEXEC') or die;

class pkg_scccardInstallerScript
{
    public function postinstall($parent)
    {
        $app = JFactory::getApplication();

        $this->patchModuleManifest($app);
        $this->checkBadge($app);
    }

    private function patchModuleManifest($app)
    {
        $modulePath = JPATH_BASE . '/modules/mod_facilitycalendar_event_list/mod_facilitycalendar_event_list.xml';

        if (!file_exists($modulePath)) {
            $app->enqueueMessage(
                '<strong>mod_facilitycalendar_event_list</strong> was not found. '
                . 'Install it first, then re-install this package to enable the layout dropdown.',
                'warning'
            );
            return;
        }

        $content = file_get_contents($modulePath);

        if (strpos($content, 'type="modulelayout"') !== false) {
            return;
        }

        $field = <<<'XML'
				<field
					name="layout"
					type="modulelayout"
					label="Module Layout"
					description="Select how this module is rendered."
					default="_:default"
				/>XML;

        $pattern  = '/(<fieldset\s+name="advanced">)/';
        $replace  = '$1' . "\n" . $field;
        $newContent = preg_replace($pattern, $replace, $content, 1, $count);

        if ($count > 0 && @file_put_contents($modulePath, $newContent) !== false) {
            $app->enqueueMessage(
                'Layout dropdown enabled — set <strong>Module Layout</strong> to <em>scccard</em> '
                . 'in the module\'s Advanced tab.',
                'success'
            );
        } else {
            $app->enqueueMessage(
                'Could not auto-patch the module manifest. '
                . 'Add the layout field manually — see the README for instructions.',
                'warning'
            );
        }
    }

    private function checkBadge($app)
    {
        $badgePath = JPATH_BASE . '/images/scc-calendar-badge.png';

        if (!file_exists($badgePath)) {
            $app->enqueueMessage(
                'Upload <strong>scc-calendar-badge.png</strong> to <code>/images/</code> '
                . 'for the floating badge on the card.',
                'notice'
            );
        }
    }
}
