<?php
/**
 * Facility Calendar Upcoming Event List — Modern Soft Blue — installer script
 *
 * Package installer (Joomla 3.8+) with a child file extension.
 *
 * Responsibilities:
 *   preflight  — abort install if Joomla or PHP version is too low
 *   postflight — register language strings, patch the module manifest to add
 *                layout override support, remove the orphaned legacy child
 *   uninstall  — remove deployed files, remove stray package manifests,
 *                restore the module manifest backup
 *
 * @copyright   Copyright (C) 2026 Simcoe Curling Club
 * @license     GNU General Public License version 2 or later
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class pkg_facilitycalendar_upcomingeventlist_modernsoftblueInstallerScript
{
    private string $minimumJoomla = '3.8.0';
    private string $minimumPhp    = '7.4.0';

    private const MODULE_NAME     = 'mod_facilitycalendar_event_list';
    private const MODULE_XML_PATH = JPATH_BASE . '/modules/' . self::MODULE_NAME . '/' . self::MODULE_NAME . '.xml';
    private const BACKUP_SUFFIX   = '.msb-backup';

    public function install($adapter): bool { return true; }
    public function update($adapter): bool   { return true; }

    public function uninstall($adapter): bool
    {
        $app = Factory::getApplication();

        $tplDir  = JPATH_ROOT . '/templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/';
        $tplFiles = ['modernsoftblue.php', 'index.html'];
        foreach ($tplFiles as $f) {
            $p = $tplDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        $mediaDir  = JPATH_ROOT . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/';
        $mediaFiles = ['modernsoftblue.css', 'clock.svg', 'index.html'];
        foreach ($mediaFiles as $f) {
            $p = $mediaDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        $langDir  = JPATH_ROOT . '/language/en-GB/';
        $langFiles = [
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.ini',
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.sys.ini',
        ];
        foreach ($langFiles as $f) {
            $p = $langDir . $f;
            if (file_exists($p)) { @unlink($p); }
        }

        $this->removeRecursive(JPATH_ROOT . '/modernsoftblue/');

        // Remove any stray package manifests left in the manifests/packages folder
        // from older releases that used a non-canonical manifest filename.
        $manifestDir = JPATH_ADMINISTRATOR . '/manifests/packages/';
        foreach (['facilitycalendar-modernsoftblue.xml', 'pkg_facilitycalendar-upcomingeventlist-modernsoftblue.xml'] as $stray) {
            $p = $manifestDir . $stray;
            if (file_exists($p)) { @unlink($p); }
        }

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

        // Files (template override, media, language) are deployed natively by the
        // package's child file extension (Joomla 3 <fileset> handling). This script
        // only patches the module manifest and registers the language strings.

        $this->loadLanguageFiles();

        // Remove the orphaned legacy child file extension from older releases
        // whose element was simply "modernsoftblue".
        $this->removeLegacyChild();

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

    private function removeRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;
            if (is_dir($path)) {
                $this->removeRecursive($path . '/');
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Remove the legacy child file-extension record and manifest that older
     * releases registered with the ambiguous element "modernsoftblue".
     */
    private function removeLegacyChild(): void
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(array($db->quoteName('extension_id'), $db->quoteName('name'), $db->quoteName('folder')))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('file'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('modernsoftblue'));

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Exception $e) {
            return;
        }

        foreach ($rows as $row) {
            if (isset($row->folder) && $row->folder !== '') {
                $manifest = JPATH_ADMINISTRATOR . '/manifests/files/' . basename($row->folder);
                if (file_exists($manifest)) {
                    @unlink($manifest);
                }
            }

            $legacyManifest = JPATH_ADMINISTRATOR . '/manifests/files/modernsoftblue.xml';
            if (file_exists($legacyManifest)) {
                @unlink($legacyManifest);
            }

            try {
                $db->setQuery('DELETE FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);
                $db->execute();
            } catch (\Exception $e) {
                // ignore, proceeds even if the row cannot be removed
            }
        }
    }

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
