<?php
/**
 * Facility Calendar Upcoming Event List — Modern Soft Blue — installer script
 *
 * Package installer (Joomla 3.8+). Has a child file extension.
 *
 * Responsibilities:
 *   preflight  — abort install if Joomla or PHP version is too low
 *   postflight — register language strings, patch module manifest, remove orphan legacy child
 *   uninstall  — remove deployed files, remove stray package manifests, restore module manifest backup
 *
 * @copyright   Copyright (C) 2026 Simcoe Curling Club
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class pkg_facilitycalendar_upcomingeventlist_modernsoftblueInstallerScript
{
    // Untyped deliberately: typed properties are a PHP 7.4 compile-time feature,
    // and this file must load on older PHP precisely so preflight() can abort
    // cleanly there instead of fataling the whole install.
    private $minimumJoomla = '3.8.0';
    private $minimumPhp    = '7.4.0';

    private const MODULE_NAME     = 'mod_facilitycalendar_event_list';
    // JPATH_ROOT (site root) deliberately: installer scripts execute in the
    // admin application where JPATH_BASE points at /administrator, but this is
    // a site module. JPATH_BASE here would silently target a path that never
    // exists and the manifest patch would fail on every install.
    private const MODULE_XML_PATH = JPATH_ROOT . '/modules/' . self::MODULE_NAME . '/' . self::MODULE_NAME . '.xml';
    private const BACKUP_SUFFIX   = '.msb-backup';
    private const META_SUFFIX     = '.msb-backup.json';
    // Maximum age of an installer lock file before it is treated as stale
    // (e.g. PHP killed by a host timeout mid-install). 15 minutes.
    private const LOCK_TTL        = 900;

    public function install($adapter): bool { return true; }
    public function update($adapter): bool   { return true; }

    /**
     * Log to Joomla's log system. Installer failures must leave a trace;
     * silent catches inherit silent breakage to the next maintainer.
     */
    private function log(string $message)
    {
        if (!class_exists('JLog')) {
            return;
        }
        // Logging must never crash an install: if the log subsystem itself is
        // broken, drop the message silently (last resort).
        try {
            \JLog::add(
                'pkg_facilitycalendar_upcomingeventlist_modernsoftblue: ' . $message,
                \JLog::WARNING,
                'jerror'
            );
        } catch (\Throwable $e) {
        }
    }

    public function uninstall($adapter): bool
    {
        $app = Factory::getApplication();

        $tplDir  = JPATH_ROOT . '/templates/tpl_jdseattle/html/mod_facilitycalendar_event_list/';
        $tplFiles = ['modernsoftblue.php', 'index.html'];
        foreach ($tplFiles as $f) {
            $p = $tplDir . $f;
            if (file_exists($p) && !@unlink($p)) {
                $this->log('Unable to delete template override file during uninstall: ' . $p);
            }
        }

        $mediaDir  = JPATH_ROOT . '/media/mod_facilitycalendar_upcomingeventlist_modernsoftblue/';
        $mediaFiles = ['modernsoftblue.css', 'clock.svg', 'index.html'];
        foreach ($mediaFiles as $f) {
            $p = $mediaDir . $f;
            if (file_exists($p) && !@unlink($p)) {
                $this->log('Unable to delete media file during uninstall: ' . $p);
            }
        }

        $langDir  = JPATH_ROOT . '/language/en-GB/';
        $langFiles = [
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.ini',
            'en-GB.pkg_facilitycalendar_upcomingeventlist_modernsoftblue.sys.ini',
        ];
        foreach ($langFiles as $f) {
            $p = $langDir . $f;
            if (file_exists($p) && !@unlink($p)) {
                $this->log('Unable to delete language file during uninstall: ' . $p);
            }
        }

        // This staging dirname only ever existed inside the install package;
        // it is never deployed to the site (the child extension deploys files
        // to their final locations, which Joomla's FileAdapter removes). The
        // call is intentionally gone: dead recursive deletes rot into hazards.

        $manifestDir = JPATH_ADMINISTRATOR . '/manifests/packages/';
        foreach (['facilitycalendar-modernsoftblue.xml', 'pkg_facilitycalendar-upcomingeventlist-modernsoftblue.xml'] as $stray) {
            $p = $manifestDir . $stray;
            if (file_exists($p)) { @unlink($p); }
        }

        $backupPath = self::MODULE_XML_PATH . self::BACKUP_SUFFIX;
        $metaPath   = self::MODULE_XML_PATH . self::META_SUFFIX;
        if (file_exists($backupPath)) {
            $this->restoreModuleManifest($app, $backupPath, $metaPath);
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

        // Load package language first so version-abort messages translate
        // instead of showing raw keys (harmless no-op on fresh installs where
        // the child has not deployed the files yet).
        $this->loadLanguageFiles();

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

        $this->loadLanguageFiles();
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

    /**
     * Restore the module manifest from our backup, but only when it is provably
     * safe: the backup must be intact AND the live file must still be exactly
     * what our patch wrote. If the module was updated after our install, the
     * backup is stale and restoring it would silently downgrade a third-party
     * extension — so refuse, keep the backup, and say so loudly.
     */
    private function restoreModuleManifest($app, string $backupPath, string $metaPath)
    {
        if (!class_exists('DOMDocument')) {
            $this->log('Cannot verify module manifest during uninstall: DOM extension missing. Manifest left untouched.');
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the module manifest could not be verified (PHP XML extension missing) and was left untouched.',
                'warning'
            );
            return;
        }

        if (!file_exists($metaPath) || !is_readable($metaPath) || !is_readable($backupPath)) {
            $this->log('No integrity record for module manifest backup; refusing blind restore. Backup kept at: ' . $backupPath);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: no integrity record for the module manifest backup, so it was NOT restored (backup kept). Check the module manifest manually.',
                'warning'
            );
            return;
        }

        $meta = json_decode((string) @file_get_contents($metaPath), true);
        if (!is_array($meta) || empty($meta['backup_sha1']) || empty($meta['patched_sha1'])) {
            $this->log('Corrupt integrity record for module manifest backup; refusing restore. Backup kept at: ' . $backupPath);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the module manifest backup failed its integrity check and was NOT restored (backup kept).',
                'warning'
            );
            return;
        }

        if (sha1_file($backupPath) !== $meta['backup_sha1']) {
            $this->log('Module manifest backup is corrupt (checksum mismatch); refusing restore. Backup kept at: ' . $backupPath);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the module manifest backup is corrupt and was NOT restored (backup kept).',
                'warning'
            );
            return;
        }

        if (!file_exists(self::MODULE_XML_PATH) || sha1_file(self::MODULE_XML_PATH) !== $meta['patched_sha1']) {
            $this->log('Live module manifest changed since install (module updated?); refusing restore to avoid downgrading it. Backup kept at: ' . $backupPath);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the module manifest changed after install, so the backup was NOT restored (backup kept) to avoid downgrading the module.',
                'warning'
            );
            return;
        }

        if (!is_writable(self::MODULE_XML_PATH)) {
            $this->log('Module manifest not writable during uninstall; restore skipped: ' . self::MODULE_XML_PATH);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the module manifest is not writable, so it was left as-is.',
                'warning'
            );
            return;
        }

        if (!@copy($backupPath, self::MODULE_XML_PATH)) {
            $this->log('Failed to copy module manifest backup during uninstall: ' . $backupPath);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: restoring the module manifest failed (backup kept).',
                'warning'
            );
            return;
        }

        $check = new DOMDocument();
        $check->preserveWhiteSpace = true;
        $check->formatOutput       = false;
        $ok = $check->loadXML((string) @file_get_contents(self::MODULE_XML_PATH), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            $this->log('Restored module manifest does not parse; leaving restored copy in place for manual inspection: ' . self::MODULE_XML_PATH);
            $app->enqueueMessage(
                'Modern Soft Blue uninstall: the restored module manifest does not parse — inspect it manually.',
                'warning'
            );
            return;
        }

        @unlink($backupPath);
        @unlink($metaPath);
        $app->enqueueMessage(
            Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_RESTORE_MANIFEST'),
            'notice'
        );
    }

    /**
     * Remove the legacy child file-extension record that older releases
     * registered with the ambiguous element "modernsoftblue".
     *
     * Conservative by design: a row is deleted only if its display name is
     * exactly the legacy name this package used. Any other row sharing the
     * element belongs to someone else and is refused with a loud warning —
     * deleting foreign extension rows would break other extensions with no
     * undo. Failures are logged, never swallowed.
     */
    private function removeLegacyChild()
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(array($db->quoteName('extension_id'), $db->quoteName('name')))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('file'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('modernsoftblue'));

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Exception $e) {
            $this->log('Legacy child lookup failed, extensions table left untouched: ' . $e->getMessage());
            return;
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int) $row->extension_id;
            if (!isset($row->name) || $row->name !== 'Modern Soft Blue') {
                $seen = isset($row->name) ? $row->name : '(unnamed)';
                $this->log('Refusing to delete file extension id ' . $id . ' with element modernsoftblue: unexpected name "' . $seen . '". Not ours — manual cleanup required.');
                Factory::getApplication()->enqueueMessage(
                    'Modern Soft Blue install: found an unrelated file extension using the element "modernsoftblue" (id ' . $id . '). It was left untouched — remove it manually if it is stale.',
                    'warning'
                );
                continue;
            }

            $legacyManifest = JPATH_ADMINISTRATOR . '/manifests/files/modernsoftblue.xml';
            if (file_exists($legacyManifest) && !@unlink($legacyManifest)) {
                $this->log('Unable to delete legacy child manifest: ' . $legacyManifest);
            }

            try {
                $db->setQuery('DELETE FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('extension_id') . ' = ' . $id);
                $db->execute();
            } catch (\Exception $e) {
                $this->log('Failed to delete legacy child extension row id ' . $id . ': ' . $e->getMessage());
            }
        }
    }

    private function loadLanguageFiles()
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
     * Acquire the installer lock. Returns the lock handle, or null when another
     * install is running. A lock left behind by a killed PHP process (host
     * timeout) must not brick every future install, so locks older than
     * LOCK_TTL are treated as stale: cleared with a visible notice, never
     * silently. (The check-and-clear races safely: concurrent acquirers fall
     * through to exclusive-create, exactly one wins, the loser aborts.)
     *
     * No return type declared: "resource" is not a valid standalone type and
     * trips a warning on PHP 8.2+.
     */
    private function acquireLock()
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
            $age = time() - (int) @filemtime($lockPath);
            if ($age > self::LOCK_TTL) {
                @unlink($lockPath);
                $this->log('Cleared stale installer lock (age ' . $age . 's, left by an interrupted install): ' . $lockPath);
                Factory::getApplication()->enqueueMessage(
                    'Modern Soft Blue install: cleared a stale installer lock left by an interrupted install and continued.',
                    'notice'
                );
            } else {
                return null;
            }
        }

        $handle = @fopen($lockPath, 'x');
        if (!is_resource($handle)) {
            return null;
        }

        $pid = function_exists('getmypid') ? getmypid() : 0;
        @fwrite($handle, time() . ':' . $pid);
        flock($handle, LOCK_EX);
        return $handle;
    }

    private function releaseLock($handle)
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
        if (!class_exists('DOMDocument')) {
            $this->log('Cannot patch module manifest: PHP DOM/XML extension missing.');
            $app->enqueueMessage(
                'Modern Soft Blue install: the module manifest could not be patched because the PHP XML (DOM) extension is missing. Install it and reinstall the package.',
                'warning'
            );
            return false;
        }

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
                if (!@copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH)) {
                    $this->log('Rollback copy failed after temp-write failure; module manifest may be inconsistent: ' . self::MODULE_XML_PATH);
                }
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
                if (!@copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH)) {
                    $this->log('Rollback copy failed after rename failure; module manifest may be inconsistent: ' . self::MODULE_XML_PATH);
                }
            }
            $this->releaseLock($lockHandle);
            $app->enqueueMessage(
                Text::_('PKG_FACILITYCALENDAR_UPCOMINGEVENTLIST_MODERNSOFTBLUE_WRITE_FAILED'),
                'warning'
            );
            return false;
        }

        // Verify what is actually on disk — not just the string we wrote.
        $written = (string) @file_get_contents(self::MODULE_XML_PATH);
        $checkDom = new DOMDocument();
        $checkDom->preserveWhiteSpace = true;
        $checkDom->formatOutput       = false;
        $checkOk = $checkDom->loadXML($written, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($checkOk) {
            $checkXpath = new DOMXPath($checkDom);
            $checkOk = $checkXpath->query('//fieldset[@name="advanced"]/field[@name="layout"]')->length > 0;
        }
        if (!$checkOk) {
            $this->log('Patched module manifest failed on-disk verification; rolling back: ' . self::MODULE_XML_PATH);
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

        // Integrity record for a future uninstall: restore is only safe while
        // the live file is still exactly what we wrote (see restoreModuleManifest).
        $meta = json_encode(array('backup_sha1' => sha1($content), 'patched_sha1' => sha1($written)));
        if ($meta === false || @file_put_contents(self::MODULE_XML_PATH . self::META_SUFFIX, $meta, LOCK_EX) === false) {
            $this->log('Could not write module manifest integrity record; rolling back patch: ' . self::MODULE_XML_PATH);
            if (file_exists(self::MODULE_XML_PATH . self::BACKUP_SUFFIX)) {
                if (!@copy(self::MODULE_XML_PATH . self::BACKUP_SUFFIX, self::MODULE_XML_PATH)) {
                    $this->log('Rollback copy failed after integrity-record failure; module manifest may be inconsistent: ' . self::MODULE_XML_PATH);
                }
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