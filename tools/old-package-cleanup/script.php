<?php
/**
 * Facility Calendar — remove orphaned OLD package(s) (pkg_facilitycalendar-modernsoftblue)
 * ------------------------------------------------------------------------------------
 * One-time cleanup helper. On INSTALL it deletes the stale, orphaned OLD
 * package row(s) from the Joomla `#__extensions` table (and any leftover
 * `#__schemas` rows), so the Extension Manager stops showing the old,
 * un-removable package.
 *
 * It uses the site's existing database connection (configuration.php), so NO
 * database login is required. It does NOT touch the newer package
 * (`pkg_facilitycalendar-upcomingeventlist-modernsoftblue`) or any files.
 *
 * The stored `element` is derived by Joomla from the OLD `<packagename>`
 * (`facilitycalendar-modernsoftblue`), so it is matched here by a LIKE pattern
 * rather than an exact string — this is safe because the NEW package's element
 * is explicitly excluded.
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

		// Find every Package row belonging to the OLD package.
		// The new package's element is 'pkg_facilitycalendar-upcomingeventlist-modernsoftblue'
		// and is excluded so it is never touched.
		$query = $db->getQuery(true)
			->select($db->quoteName(array('extension_id', 'element', 'name')))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('package'))
			->where($db->quoteName('element') . ' LIKE ' . $db->quote('%facilitycalendar-modernsoftblue%'))
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
				'No old package row was found (it may already be removed, or its '
				. 'element differs from the expected pattern). Refresh the Extension '
				. 'Manager — if it is still listed, reply with the exact row text.',
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
