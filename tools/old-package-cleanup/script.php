<?php
/**
 * Facility Calendar — remove orphaned OLD package(s)
 * -------------------------------------------------
 * One-time cleanup helper. On INSTALL or UPDATE it deletes the stale, orphaned
 * OLD package row(s) from the Joomla `#__extensions` table (and any leftover
 * `#__schemas` rows), so the Extension Manager stops showing the old,
 * un-removable package.
 *
 * It uses the site's existing database connection (configuration.php), so NO
 * database login is required. It does NOT touch the newer package
 * (`pkg_facilitycalendar_upcomingeventlist_modernsoftblue`) or any files.
 *
 * IMPORTANT: The old package's stored `element` is `pkg_facilitycalendar_modernsoftblue`
 * (underlines), as shown in the Extension Manager. We match BOTH the
 * underlined element (the real one) and the hyphenated form, and exclude the
 * newer package by name.
 *
 * NOTE: cleanup runs in postflight(), which Joomla calls after BOTH a fresh
 * install and an upgrade (re-install). Just having install() is not enough —
 * a re-install over an existing copy triggers update(), not install().
 *
 * IMPORTANT: delete this helper's zip + any copied files once you're done.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class pkg_msb_old_cleanupInstallerScript
{
	public function install($parent)
	{
		return true;
	}

	public function update($parent)
	{
		return true;
	}

	/**
	 * Runs after BOTH install and update, so the cleanup always happens even
	 * when the helper is re-installed over an existing copy.
	 */
	public function postflight($type, $parent)
	{
		$this->cleanup();
		return true;
	}

	private function cleanup()
	{
		$db = Factory::getDbo();
		$removed = array();

		// Match ONLY package rows belonging to the OLD "Modern Soft Blue"
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

		try
		{
			$rows = $db->loadObjectList();
		}
		catch (Exception $e)
		{
			$this->warn('Database error during lookup: ' . $e->getMessage());
			return;
		}

		foreach ($rows as $row)
		{
			try
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
			}
			catch (Exception $e)
			{
				$this->warn('Could not delete row ' . (int) $row->extension_id . ': ' . $e->getMessage());
				continue;
			}

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
				. 'element differs from the expected values. If pkg_facilitycalendar_modernsoftblue '
				. 'is still listed, tell me the EXACT contents of the Name and Element '
				. 'columns so it can be matched precisely.',
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
	}

	private function warn($message)
	{
		try
		{
			Factory::getApplication()->enqueueMessage($message, 'warning');
		}
		catch (Exception $e)
		{
			// Never let messaging break the install.
		}
	}
}
