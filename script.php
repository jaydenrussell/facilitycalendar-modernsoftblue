<?php
/**
 * Facility Calendar Upcoming Event List — Modern Soft Blue — installer script
 *
 * Provides install, update, and uninstall lifecycle hooks (Joomla 3.8+).
 *
 * This is a flat package: there is no separate file extension child.
 * All file deployment happens in postflight; cleanup happens in uninstall.
 *
 * Responsibilities:
 *   preflight  — abort install if Joomla or PHP version is too low
 *   postflight — deploy files from package, patch module manifest
 *   uninstall  — remove deployed files, restore module manifest
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

    public function install($adapter): bool { return true; }
    public function update($adapter): bool   { return true; }

    public function uninstall($adapter): bool
    {
        $app = Factory::getApplication();

        // Remove deployed template overrides
        $tplDir  = JPATH_ROOT . '/templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/';
        $tplFiles = ['modernsoftblue.php', 'index.html'];
        foreach ($tplFiles as $f) {
            $p = $tplDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        // Remove deployed media files
        $mediaDir  = JPATH_ROOT . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/';
        $mediaFiles = ['modernsoftblue.css', 'clock.svg', 'index.html'];
        foreach ($mediaFiles as $f) {
            $p = $mediaDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        // Remove deployed language files
        $langDir  = JPATH_ROOT . '/language/en-GB/';
        $langFiles = [
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.ini',
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.sys.ini',
        ];
        foreach ($langFiles as $f) {
            $p = $langDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        // Restore module manifest from backup
        $backupPath = self::MODULE_XML_PATH . self::BACKUP_SUFFIX;
        if (file_exists($backupPath)) {
            if (is_writable(self::MODULE_XML_PATH) && is_readable($backupPath)) {
                $restored = copy($backupPath, self::MODULE_XML_PATH);
                if ($restored && filesize(self::MODULE_XML_PATH) === filesize($backupPath)) {
                    $verifyDom = new DOMDocument();
                    $verifyDom->preserveWhiteSpace = true;
                    $verifyDom->formatOutput       = false;
                    $restored = $verifyDom->loadXML(file_get_contents(self::MODULE_XML_PATH), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                }
                if ($restored) {
                    @unlink($backupPath);
                    $app->enqueueMessage(
                        Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_MANIFEST'),
                        'notice'
                    );
                }
            }
        }

        // Clear manifest cache so Joomla re-reads the restored module manifest
        if (class_exists('JCache')) {
            $cache = JFactory::getCache('_system', '');
            if (method_exists($cache, 'clean')) {
                $cache->clean('mod_facilitycalendar_event_list');
            }
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

        // Locate the package source directory (where Joomla extracted the zip)
        $srcDir = dirname($parent->getPath('manifest'));

        // Deploy template overrides
        $srcTpl  = $srcDir . '/files/templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/';
        $destTpl = JPATH_ROOT . '/templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/';
        $this->deployFolder($srcTpl, $destTpl);

        // Deploy media files
        $srcMedia  = $srcDir . '/files/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/';
        $destMedia = JPATH_ROOT . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/';
        $this->deployFolder($srcMedia, $destMedia);

        // Deploy language files
        $srcLang  = $srcDir . '/files/language/en-GB/';
        $destLang = JPATH_ROOT . '/language/en-GB/';
        $this->deployFolder($srcLang, $destLang);

        // Load language files from deployed location
        $this->loadLanguageFiles();

        // Patch module manifest to add layout dropdown
        if (!$this->patchModuleManifest($app)) {
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_FAILED'),
                'warning'
            );
            return false;
        }

        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_PATCH_SUCCESS'),
            'success'
        );

        return true;
    }

    /**
     * Recursively copy a folder from source to destination.
     */
    private function deployFolder(string $src, string $dest): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath  = $src . $item;
            $destPath = $dest . $item;

            if (is_dir($srcPath)) {
                $this->deployFolder($srcPath . '/', $destPath . '/');
            } else {
                @copy($srcPath, $destPath);
            }
        }
    }

    /**
     * Load the package's language files from their deployed location.
     */
    private function loadLanguageFiles(): void
    {
        $langDir = JPATH_ROOT . '/language/en-GB/';

        $files = [
            'pkg_facilitycalendar_upcomingeventlist_modernsoftblue',
            'pkg_facilitycalendar_upcomingeventlist_modernsoftblue.sys',
        ];

        $lang = Factory::getLanguage();
        foreach ($files as $file) {
            $path = $langDir . $file . '.ini';
            if (file_exists($path)) {
                $lang->load($file, JPATH_ROOT);
            }
        }
    }

    /**
     * Acquire an exclusive lock in Joomla's temp directory.
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

        // Strip DTDs to prevent XXE attacks (multi-line aware)
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

        // Idempotency check — skip if layout field already exists
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

        // Verify XML is well-formed before writing
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

        // Backup original manifest
        $backupBytes = file_put_contents(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, $content, LOCK_EX);
        $contentLen  = strlen($content);

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
        $bytes   = file_put_contents($tmpPath, $newContent, LOCK_EX);
        $newLen  = strlen($newContent);

        if ($bytes === false || $bytes !== $newLen) {
            if (file_exists($tmpPath)) { @unlink($tmpPath); }
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
            if (file_exists($tmpPath)) { @unlink($tmpPath); }
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
        return true;
    }
}
