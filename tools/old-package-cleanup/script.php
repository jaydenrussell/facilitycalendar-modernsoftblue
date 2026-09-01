<?php
/**
 * Facility Calendar — remove orphaned OLD package(s)
 * -------------------------------------------------
 * One-time cleanup helper. On INSTALL it deletes the stale, orphaned OLD
 * package row(s) from the Joomla `#__extensions` table (and any leftover
 * `#__schemas` rows), so the Extension Manager stops showing the old,
 * un-removable package.
 *
 * It uses the site's existing database connection (configuration.php), so NO
 * database login is required. It does NOT touch the newer package
 * (`pkg_facilitycalendar_upcomingeventlist_modernsoftblue`) or any files.
 *
 * IMPORTANT: The old package's stored `element` is `pkg_facilitycalendar_modernsoftblue`
 * (underlines), as shown in the Extension Manager. Joomla stores the element
 * derived from the old manifest's <name> tag. We therefore match BOTH the
 * underlined element (the real one) and the hyphenated form (in case some
 * versions derived it from <packagename>). The new package is excluded by name.
 *
 * IMPORTANT: delete this helper's zip + any copied files once you're done.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class pkg_facilitycalendar_msb_old_cleanupInstallerScript
{
	/**
	 * Runs once on install (and on re-install since method="upgrade").
	 */
	public function install($parent)
	{
		$db = Factory::getDbo();
		$removed = array();

		// Match ONLY package rows that belong to the OLD "Modern Soft Blue"
		// facility-calendar package — by element (underlines OR hyphens), while
		// explicitly excluding the newer package so it is never touched.
		$query = $db->getQuery(true)
			->select($db->quoteName(array('extension_id', 'element', 'name')))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('package'))
			->where(
				'('
				. $db->quoteName('element') . ' = ' . $db->quote('pkg_facilitycalendar_modernsoftblue')
				. ' OR '
				. $db->quoteName('element') . ' = ' . $db->quote('pkg_facilitycalendar-modernsoftblue')
				. ' OR '
				. $db->quoteName('element') . ' LIKE ' . $db->quote('%facilitycalendar_modernsoftblue%')
				. ')'
			)
			->where($db->quoteName('element') . ' NOT LIKE ' . $db->quote('%upcomingeventlist%'));
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		foreach ($rows as $row)
		{
			// Remove the matching schema row first, then the extension row.
			$queryS = $db->getQuery(true)
				->delete($db->quoteName('#__schemas'))
				->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);
			$db->setQuery($queryS);
			$db->execute();

			$queryD = $db->getQuery(true)
				->delete($db->quoteName('#__extensions'))
				->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);
			$db->setQuery($queryD);
			$db->execute();

			if ($db->getAffectedRows() > 0)
			{
				$removed[] = $row->element . ' (id ' . (int) $row->extension_id . ')';
			}
		}

		$app = Factory::getApplication();

		if (!empty($removed))
		{
			$app->enqueueMessage(
				'Removed old package row(s): ' . implode(', ', $removed) . '.',
				'message'
			);
		}
		else
		{
			$app->enqueueMessage(
				'No old package row was removed. It may already be gone, or its '
				. 'element differs from the expected values. Refresh the Extension '
				. 'Manager — if the old package is still listed, reply with the EXACT '
				. 'Name and Element text so it can be matched precisely.',
				'warning'
			);
		}

		$app->enqueueMessage(
			'Cleanup complete. Refresh the Extension Manager — the old package should '
			. 'be gone. You may now uninstall this helper (Manage → select it → Uninstall) '
			. 'or install/update the new "Facility Calendar Upcoming Event List — Modern '
			. 'Soft Blue" package. Please delete the helper zip from your computer after use.',
			'notice'
		);

		return true;
	}
}
