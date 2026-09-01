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
use Joomla\CMS\Installer\InstallerAdapter;
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

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();
        $this->revertModuleManifestPatch($app);

        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_UNINSTALL_NOTICE'),
            'notice'
        );

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
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

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();

        $this->patchModuleManifest($app);
        $this->checkBadge($app);

        return true;
    }

    /**
     * Inject the layout field into the upstream module's manifest using DOMDocument.
     * A backup of the original manifest is created before writing.
     */
    private function patchModuleManifest($app): void
    {
        if (!file_exists(self::MODULE_XML_PATH)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_NOT_FOUND'),
                'warning'
            );
            return;
        }

        $content = file_get_contents(self::MODULE_XML_PATH);

        if (strpos($content, 'type="modulelayout"') !== false) {
            return;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;

        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($content);
        libxml_clear_errors();

        if (!$loaded) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return;
        }

        $xpath = new DOMXPath($dom);

        /** @var DOMElement $fieldset */
        $fieldset = $xpath->query('//fieldset[@name="advanced"]')->item(0);

        if (!$fieldset) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return;
        }

        $field = $dom->createElement('field');
        $field->setAttribute('name',        'layout');
        $field->setAttribute('type',        'modulelayout');
        $field->setAttribute('label',       'Module Layout');
        $field->setAttribute('description', 'Select how this module is rendered.');
        $field->setAttribute('default',     '_:default');

        $fieldset->appendChild($field);

        $newContent = $dom->saveXML($dom->documentElement);

        if (!is_string($newContent) || strlen($newContent) === 0) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return;
        }

        if (!is_writable(self::MODULE_XML_PATH)) {
            $app->enqueueMessage(
                sprintf(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_XML_NOT_WRITABLE'),
                    htmlspecialchars(self::MODULE_XML_PATH, ENT_QUOTES, 'UTF-8')
                ),
                'warning'
            );
            return;
        }

        if (!copy(self::MODULE_XML_PATH, self::MODULE_XML_PATH . self::BACKUP_SUFFIX)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_BACKUP_FAILED'),
                'warning'
            );
            return;
        }

        $bytes = @file_put_contents(self::MODULE_XML_PATH, $newContent, LOCK_EX);

        if ($bytes === false) {
            @copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH);
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_WRITE_FAILED'),
                'warning'
            );
            return;
        }

        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_SUCCESS'),
            'success'
        );
    }

    /**
     * Revert the module manifest to the backup taken at install time.
     * Silently removes the backup after a successful restore.
     */
    private function revertModuleManifestPatch($app): void
    {
        $backupPath = self::MODULE_XML_PATH . self::BACKUP_SUFFIX;

        if (!file_exists($backupPath)) {
            return;
        }

        if (is_writable(self::MODULE_XML_PATH) && is_readable($backupPath)) {
            $restored = @copy($backupPath, self::MODULE_XML_PATH);

            if ($restored) {
                @unlink($backupPath);
                $app->enqueueMessage(
                    Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_MANIFEST'),
                    'notice'
                );
                return;
            }
        }

        $app->enqueueMessage(
            sprintf(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_FAILED'),
                htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8')
            ),
            'warning'
        );
    }

    /**
     * Check that the SCC badge image exists in /images/ and notify if missing.
     */
    private function checkBadge($app): void
    {
        $badgePath = JPATH_BASE . '/images/scc-calendar-badge.png';

        if (!file_exists($badgePath)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_BADGE_MISSING'),
                'notice'
            );
        }
    }
}
