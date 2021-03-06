<?php
/*
*                 eyeos - The Open Source Cloud's Web Desktop
*                               Version 2.0
*                   Copyright (C) 2007 - 2010 eyeos Team 
* 
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU Affero General Public License version 3 as published by the
* Free Software Foundation.
* 
* This program is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
* details.
* 
* You should have received a copy of the GNU Affero General Public License
* version 3 along with this program in the file "LICENSE".  If not, see 
* <http://www.gnu.org/licenses/agpl-3.0.txt>.
* 
* See www.eyeos.org for more details. All requests should be sent to licensing@eyeos.org
* 
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU Affero General Public License version 3.
* 
* In accordance with Section 7(b) of the GNU Affero General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "Powered by
* eyeos" logo and retain the original copyright notice. If the display of the 
* logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
* must display the words "Powered by eyeos" and retain the original copyright notice. 
*/

define('WORKGROUPS_METAFILES_EXTENSION', '.xml');

/**
 * 
 * @package kernel-services
 * @subpackage Meta
 */
class DefaultWorkgroupFileXMLMetaHandler implements IMetaDataHandler {
	protected static $Instance;
	
	
	protected function __construct() {}
	
	public static function getInstance() {
		if (self::$Instance === null) {
			$class = __CLASS__;
			self::$Instance = new $class();
		}
		return self::$Instance;
	}
	
	/**
	 * @param mixed $object
	 * @param String $params
	 * @return boolean
	 * @throws EyeException
	 * @throws EyeErrorException
	 */
	public function deleteMeta($object, $params) {
		if (!$object instanceof EyeWorkgroupFile) {
			throw new EyeInvalidArgumentException('$object must be an EyeWorkgroupFile.');
		}
		
		$meta = $this->retrieveMeta($object, $params);
		if ($meta !== null) {
			SecurityManager::getInstance()->checkPermission(
				$meta,
				new MetaDataPermission('delete', null, $object)
			);
		}
		
		$urlParts = $object->getURLComponents();
		if ($urlParts['path'] == '/') {
			$realpath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR;
		} else {
			$realpath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . $urlParts['path'];
		}
		if (is_dir($realpath)) {
			AdvancedPathLib::rmdirs($realpath);
		}
		return @unlink($realpath . WORKGROUPS_METAFILES_EXTENSION);
	}

	/**
	 * Return the relative path giving a $urlParts
	 * EXAMPLE:
	 * $urlParts['path'] => workgroup://~wgroupName/folder1/folder2/folder3
	 * return => /folder1/folder2/folder3
	 * 
	 * @param <mixed> $urlParts array containing various components of a workgroup URL
	 * @return <String>	relative path as described above
	 */
	private function getPathFromUrlParts ($urlParts) {
		if ($urlParts === null || !isset($urlParts['path']) || !is_string($urlParts['path'])) {
			throw new EyeInvalidArgumentException('$urlParts is null or $urlParts[\'path\'] is null or not a string');
		}
		$urlPartsPath = $urlParts['path'];

		$expr = '`/' . EyeosAbstractVirtualFile::URL_LOCATOR_CHAR . EyeosSQLPrincipalsManager::PRINCIPALNAME_VALIDATION_REGEXP . '(/.*)`';
		if (preg_match($expr, $urlPartsPath, $matches)) {
			return $matches[1];
		}

		return $urlPartsPath;
	}
	
	protected function getWorkgroupMetaFilesPath($wgname) {
		$path = UMManager::getEyeosWorkgroupDirectory($wgname) . '/' . WORKGROUPS_METAFILES_DIR;
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		return $path;
	}
	
	/**
	 * @param mixed $object
	 * @param String $params
	 * @return IMetaData
	 * @throws EyeException
	 * @throws EyeErrorException
	 */
	public function retrieveMeta($object, $params) {
		if (!$object instanceof EyeWorkgroupFile) {
			throw new EyeInvalidArgumentException('$object must be an EyeWorkgroupFile.');
		}
		$urlParts = $object->getURLComponents();
		if ($urlParts['path'] == '/') {
			$filepath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . WORKGROUPS_METAFILES_EXTENSION;
		} else {
			$relativePath = self::getPathFromUrlParts($urlParts);
			$filepath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . $relativePath . WORKGROUPS_METAFILES_EXTENSION;
		}
		$provider = new SimpleXMLMetaProvider((string) $params, array(
			SimpleXMLMetaProvider::PARAM_FILEPATH => $filepath
		));
		$meta = null;
		try {
			$meta = $provider->retrieveMeta(null);
		} catch (EyeFileNotFoundException $e) {}
		
		if ($meta === null && $object->exists()) {
			$workgroup = UMManager::getInstance()->getWorkgroupByName($urlParts['principalname']);
			$workgroupOwner = UMManager::getInstance()->getUserById($workgroup->getOwnerId());
			$masterGroup = UMManager::getInstance()->getGroupById($workgroup->getMasterGroupId());
			
			$meta = MetaManager::getInstance()->getNewMetaDataInstance($object);
			$meta->setAll(array(
				EyeosAbstractVirtualFile::METADATA_KEY_OWNER => $workgroupOwner->getName(),
				EyeosAbstractVirtualFile::METADATA_KEY_GROUP => $masterGroup->getName(),
				EyeosAbstractVirtualFile::METADATA_KEY_PERMISSIONS => '-rw-------',
				EyeosAbstractVirtualFile::METADATA_KEY_CREATIONTIME => null,
				EyeosAbstractVirtualFile::METADATA_KEY_MODIFICATIONTIME => null
			));
			if ($object->isDirectory()) {
				$meta->set(EyeosAbstractVirtualFile::METADATA_KEY_PERMISSIONS, '-rwx------');
			}
		}
		
		SecurityManager::getInstance()->checkPermission(
			$meta,
			new MetaDataPermission('read', null, $object)
		);
		
		return $meta;
	}

	/**
	 * @param mixed $object
	 * @param IMetaData $metaData
	 * @param String $params
	 * @throws EyeException
	 * @throws EyeErrorException
	 */
	public function storeMeta($object, IMetaData $metaData = null, $params) {
		if (!$object instanceof EyeWorkgroupFile) {
			throw new EyeInvalidArgumentException('$object must be an EyeWorkgroupFile.');
		}
		
		$meta = $this->retrieveMeta($object, $params);
		SecurityManager::getInstance()->checkPermission(
			$metaData,
			new MetaDataPermission('write', $meta, $object)
		);
		
		$urlParts = $object->getURLComponents();
		if ($urlParts['path'] == '/') {
			$filepath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . WORKGROUPS_METAFILES_EXTENSION;
		} else {
			$filepath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . $urlParts['path'] . WORKGROUPS_METAFILES_EXTENSION;
		}
		
		$dir = dirname($filepath);
		if (!is_dir($dir)) {
			if (!mkdir($dir, 0777, true)) {
				throw new EyeIOException('Unable to create necessary directories for meta file ' . $filepath . '.');
			}
		}
		
		$provider = new SimpleXMLMetaProvider((string) $params, array(
			SimpleXMLMetaProvider::PARAM_FILEPATH => $filepath
		));
		$meta = $provider->storeMeta(null, $metaData);
	}
	
	/**
	 * @param mixed $object
	 * @param mixed $changes
	 * @param String $params
	 * @return boolean
	 * @throws EyeException
	 */
	public function updateMeta($object, $changes, $params) {
		if (!$object instanceof EyeWorkgroupFile) {
			throw new EyeInvalidArgumentException('$object must be an EyeWorkgroupFile.');
		}
		$urlParts = $object->getURLComponents();
		if ($urlParts['path'] == '/') {
			$realpath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR;
		} else {
			$realpath = $this->getWorkgroupMetaFilesPath($urlParts['principalname']) . '/' . WORKGROUPS_FILES_DIR . $urlParts['path'];
		}
		
		//file has been renamed
		if (isset($changes['oldName'])) {
			$oldRealpath = dirname($realpath) . '/' . $changes['oldName'];
			if (is_dir($oldRealpath)) {
				//rename metafiles directory
				if (!rename($oldRealpath, $realpath)) {
					throw new EyeMetaDataUpdateException('Unable to update metadata for directory ' . $object->getPath() . '.');
				}
			}
			//rename metafile
			if (rename($oldRealpath . SERVICE_META_CONFIGURATION_FILE_EXTENSION, $realpath . SERVICE_META_CONFIGURATION_FILE_EXTENSION)) {
				return true;
			}
			throw new EyeMetaDataUpdateException('Unable to update metadata for file ' . $object->getPath() . '.');
		}
	}
}

?>
