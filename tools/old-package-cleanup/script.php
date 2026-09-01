<?php
/**
 * Facility Calendar — remove orphaned OLD package (pkg_facilitycalendar_modernsoftblue)
 * ------------------------------------------------------------------------------------
 * One-time cleanup helper. On INSTALL it deletes the stale, orphaned
 * `pkg_facilitycalendar_modernsoftblue` row from the Joomla `#__extensions`
 * table (and any leftover `#__schemas` row), so the Extension Manager stops
 * showing the old, un-removable package.
 *
 * It uses the site's existing database connection (configuration.php), so NO
 * database login is required. It does NOT touch the newer package
 * (`pkg_facilitycalendar_upcomingeventlist_modernsoftblue`) or any files.
 *
 * IMPORTANT: delete this helper's zip + any copied file once you're done.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * The element of the OLD package to remove.
 * (The new, renamed package has a different element and is NOT touched.)
 */
define('MSB_CLEANUP_OLD_ELEMENT', 'pkg_facilitycalendar_modernsoftblue');

class pkg_facilitycalendar_msb_old_cleanupInstallerScript
{
	/**
	 * Runs once on install (and on re-install since method="upgrade").
	 */
	public function install($parent)
	{
		$db = Factory::getDbo();

		// 1) Remove the orphaned package from #__extensions
		$removed = 0;

		$query = $db->getQuery(true)
			->delete($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote(MSB_CLEANUP_OLD_ELEMENT))
			->where($db->quoteName('type') . ' = ' . $db->quote('package'));
		$db->setQuery($query);
		$db->execute();
		$removed += $db->getAffectedRows();

		// 2) Remove any matching schema row (package installs leave one)
		$query2 = $db->getQuery(true)
			->delete($db->quoteName('#__schemas'))
			->where($db->quoteName('extension_id') . ' = ' . (int) $this->getPackageExtensionId($db));
		$db->setQuery($query2);
		$db->execute();

		// 3) Report to the user
		$msg = ($removed > 0)
			? ($removed . ' row(s) removed for the old package "' . MSB_CLEANUP_OLD_ELEMENT . '".')
			: 'The old package "' . MSB_CLEANUP_OLD_ELEMENT . '" was not found (already removed).';

		$app = Factory::getApplication();
		$app->enqueueMessage($msg, 'message');

		$this->showCleanupNotice($app);

		return true;
	}

	/**
	 * Look up the old package's extension_id so we can clean #__schemas.
	 */
	private function getPackageExtensionId($db)
	{
		$query = $db->getQuery(true)
			->select($db->quoteName('extension_id'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote(MSB_CLEANUP_OLD_ELEMENT))
			->where($db->quoteName('type') . ' = ' . $db->quote('package'));
		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	/**
	 * Remind the user what to do next and to remove this helper afterwards.
	 */
	private function showCleanupNotice($app)
	{
		$app->enqueueMessage(
			'Cleanup complete. Refresh the Extension Manager — the old '
			. '"Facility Calendar — Modern Soft Blue" package should be gone. '
			. 'You may now uninstall this helper (select it in Manage and press Uninstall), '
			. 'or install/update the new "Facility Calendar Upcoming Event List — Modern Soft Blue" package. '
			. 'Please delete the helper zip from your computer as it is a one-time tool.'
			,
			'notice'
		);
	}
}
