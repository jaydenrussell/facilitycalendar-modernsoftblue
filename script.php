<?php
/**
 * Facility Calendar Upcoming Event List — Modern Soft Blue — installer script
 *
 * Provides install, update, and uninstall lifecycle hooks (Joomla 3.8+).
 *
 * Note: this is written for Joomla 3.x, where the installer script is duck-typed
 * (a plain class whose method names match the installer's expectations) rather
 * than implementing Joomla 4's InstallerScriptInterface. The class name is
 * derived from the package element name by the installer.
 *
 * Responsibilities:
 *   preflight  — abort install if Joomla or PHP version is too low
 *   postflight — patch mod_facilitycalendar_event_list manifest with layout field
 *   uninstall  — revert the manifest patch cleanly
 *
 * @copyright   Copyright (C) 2026 Simcoe Curling Club
 * @license     GNU General Public License version 2 or later
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class pkg_facilitycalendar_upcomingeventlist_modernsoftblueInstallerScript
{
    /** Minimum Joomla version required */
    private string $minimumJoomla = '3.8.0';

    /** Minimum PHP version required */
    private string $minimumPhp = '7.4.0';

    /** Name of the upstream module this package patches */
    private const MODULE_NAME     = 'mod_facilitycalendar_event_list';
    private const MODULE_XML_PATH = JPATH_BASE . '/modules/' . self::MODULE_NAME . '/' . self::MODULE_NAME . '.xml';

    /** Backup file placed alongside the module manifest during patch */
    private const BACKUP_SUFFIX = '.msb-backup';

    /** Lock file suffix to prevent concurrent backup/restore operations */
    private const LOCK_SUFFIX = '.msb-lock';

    public function install($adapter): bool
    {
        return true;
    }

    public function update($adapter): bool
    {
        return true;
    }

    public function uninstall($adapter): bool
    {
        $app = Factory::getApplication();

        if (!$this->revertModuleManifestPatch($app)) {
            return false;
        }

        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_UNINSTALL_NOTICE'),
            'notice'
        );

        return true;
    }

    public function preflight(string $type, $adapter): bool
    {
        $app = Factory::getApplication();

        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_MIN_PHP'),
                    $this->minimumPhp,
                    PHP_VERSION
                ),
                'error'
            );
            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_MIN_JOOMLA'),
                    $this->minimumJoomla,
                    JVERSION
                ),
                'error'
            );
            return false;
        }

        return true;
    }

    public function postflight(string $type, $adapter): bool
    {
        $app = Factory::getApplication();

        if (!$this->patchModuleManifest($app)) {
            return false;
        }

        $this->checkBadge($app);

        return true;
    }

    /**
     * Acquire an exclusive lock in Joomla's temp directory to prevent
     * concurrent backup/restore race conditions.
     */
    private function acquireLock(): ?resource
    {
        $tmpPath = Factory::getApplication()->get('tmp_path');
        if (empty($tmpPath) || !is_writable($tmpPath)) {
            $tmpPath = JPATH_ROOT . '/tmp';
            if (!is_writable($tmpPath)) {
                return null;
            }
        }

        $lockPath = $tmpPath . '/mod_facilitycalendar_event_list.msb-lock';

        if (file_exists($lockPath)) {
            return null;
        }

        $handle = @fopen($lockPath, 'x');

        if (!is_resource($handle)) {
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    /**
     * Release the lock and remove the lock file.
     */
    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        $tmpPath = Factory::getApplication()->get('tmp_path');
        if (empty($tmpPath) || !is_writable($tmpPath)) {
            $tmpPath = JPATH_ROOT . '/tmp';
        }

        $lockPath = $tmpPath . '/mod_facilitycalendar_event_list.msb-lock';
        if (file_exists($lockPath)) {
            @unlink($lockPath);
        }
    }

    /**
     * Inject the layout field into the upstream module's manifest using DOMDocument.
     * A backup of the original manifest is created before writing.
     * Uses file locking to prevent concurrent backup/restore race conditions.
     */
    private function patchModuleManifest($app): bool
    {
        if (!file_exists(self::MODULE_XML_PATH)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_NOT_FOUND'),
                'warning'
            );
            return false;
        }

        $content = file_get_contents(self::MODULE_XML_PATH);

        // Strip DTDs to prevent XXE / billion-laughs attacks (multi-line aware)
        $content = preg_replace('#<!DOCTYPE[^>]*?(?:\[.*?\])?>#si', '', $content);
        $content = preg_replace('#<!ENTITY[^>]*>#i', '', $content);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;

        if (\PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader(true);
        }
        $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if (!$loaded) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return false;
        }

        $xpath = new DOMXPath($dom);

        // Idempotency check via DOM — skip if the layout field already exists
        if ($xpath->query('//fieldset[@name="advanced"]/field[@name="layout"]')->length > 0) {
            return true;
        }

        $fieldset = $xpath->query('//fieldset[@name="advanced"]')->item(0);

        if (!$fieldset) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return false;
        }

        $field = $dom->createElement('field');
        $field->setAttribute('name',        'layout');
        $field->setAttribute('type',        'modulelayout');
        $field->setAttribute('label',       'Module Layout');
        $field->setAttribute('description', 'Select how this module is rendered.');
        $field->setAttribute('default',     '_:default');

        $fieldset->appendChild($field);

        $newContent = $dom->saveXML($dom->documentElement);

        if (!is_string($newContent) || $newContent === '') {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return false;
        }

        // Verify the saved XML is well-formed before writing to disk
        $verifyDom = new DOMDocument();
        $verifyDom->preserveWhiteSpace = true;
        $verifyDom->formatOutput       = false;
        if (!$verifyDom->loadXML($newContent, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return false;
        }

        if (!is_writable(self::MODULE_XML_PATH)) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_XML_NOT_WRITABLE'),
                    htmlspecialchars(self::MODULE_XML_PATH, ENT_QUOTES, 'UTF-8')
                ),
                'warning'
            );
            return false;
        }

        $lockHandle = $this->acquireLock();

        if ($lockHandle === null) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_BACKUP_FAILED'),
                'warning'
            );
            return false;
        }

        // Backup with LOCK_EX to prevent partial reads during copy
        $backupBytes = file_put_contents(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, $content, LOCK_EX);
        $contentLen = strlen($content);

        if ($backupBytes === false || $backupBytes !== $contentLen) {
            if (file_exists(self::MODULE_XML_PATH . self::BACKUP_SUFFIX)) {
                @unlink(self::MODULE_XML_PATH . self::BACKUP_SUFFIX);
            }
            $this->releaseLock($lockHandle);
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_BACKUP_FAILED'),
                'warning'
            );
            return false;
        }

        // Atomic write: write to .tmp then rename
        $tmpPath = self::MODULE_XML_PATH . '.tmp';
        $bytes = file_put_contents($tmpPath, $newContent, LOCK_EX);
        $newContentLen = strlen($newContent);

        if ($bytes === false || $bytes !== $newContentLen) {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            $this->releaseLock($lockHandle);
            if (file_exists(self::MODULE_XML_PATH . self::BACKUP_SUFFIX)) {
                copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH);
            }
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_WRITE_FAILED'),
                'warning'
            );
            return false;
        }

        if (!rename($tmpPath, self::MODULE_XML_PATH)) {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
            if (file_exists(self::MODULE_XML_PATH . self::BACKUP_SUFFIX)) {
                copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH);
            }
            $this->releaseLock($lockHandle);
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_WRITE_FAILED'),
                'warning'
            );
            return false;
        }

        $this->releaseLock($lockHandle);

        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_SUCCESS'),
            'success'
        );

        return true;
    }

    /**
     * Revert the module manifest to the backup taken at install time.
     * Refuses to proceed if the backup is missing.
     * Uses file locking to prevent concurrent backup/restore race conditions.
     */
    private function revertModuleManifestPatch($app): bool
    {
        $backupPath = self::MODULE_XML_PATH . self::BACKUP_SUFFIX;

        if (!file_exists($backupPath)) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_FAILED'),
                    'No backup file found at <code>' . htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8') . '</code>; cannot revert a patch that was never applied.'
                ),
                'warning'
            );
            return false;
        }

        $lockHandle = $this->acquireLock();

        if ($lockHandle === null) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_FAILED'),
                    htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8')
                ),
                'warning'
            );
            return false;
        }

        if (!is_writable(self::MODULE_XML_PATH) || !is_readable($backupPath)) {
            $this->releaseLock($lockHandle);
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_FAILED'),
                    htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8')
                ),
                'warning'
            );
            return false;
        }

        $restored = copy($backupPath, self::MODULE_XML_PATH);

        // Verify the restored manifest is well-formed XML and destination size matches source
        if ($restored && file_exists(self::MODULE_XML_PATH) && filesize(self::MODULE_XML_PATH) === filesize($backupPath)) {
            $verifyDom = new DOMDocument();
            $verifyDom->preserveWhiteSpace = true;
            $verifyDom->formatOutput       = false;
            $restored = $verifyDom->loadXML(file_get_contents(self::MODULE_XML_PATH), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } else {
            $restored = false;
        }

        $this->releaseLock($lockHandle);

        if ($restored) {
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_MANIFEST'),
                'notice'
            );
            return true;
        }

        $app->enqueueMessage(
            sprintf(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_FAILED'),
                htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8')
            ),
            'warning'
        );

        return false;
    }

    /**
     * Check that the SCC badge image exists in the media folder and notify if missing.
     */
    private function checkBadge($app): void
    {
        $badgePath = JPATH_BASE . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/scc-calendar-badge.png';

        if (!file_exists($badgePath)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_BADGE_MISSING'),
                'notice'
            );
        }
    }
}
