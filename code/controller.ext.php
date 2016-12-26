<?php

/**
 * @copyright 2016 Rodrigo Sepúlveda Heerwagen (http://burbuja.cl/)
 * @copyright 2014-2015 Sentora Project (http://www.sentora.org/) 
 * Sentora is a GPL fork of the ZPanel Project whose original header follows:
 *
 * ZPanel - A Cross-Platform Open-Source Web Hosting Control panel.
 *
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@bobbyallen.me
 * @copyright (c) 2008-2014 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (ZPanel) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class module_controller extends ctrl_module
{
	static $ok;
	static $blank;

	static function getDefaultTranslate()
	{
		global $controller;
		$urlvars = $controller->GetAllControllerRequests('URL');
		if (!isset($urlvars['show']))
			return true;
		return false;
		//self::ExecuteAddTranslations('es');
		//return ui_sysmessage::shout(ui_language::translate('Prueba'), 'zannounceerror');
	}

	static function getisAddLanguage()
	{
		global $controller;
		$urlvars = $controller->GetAllControllerRequests('URL');
		//print_r($urlvars);
		if (!isset($urlvars['show']))
			return true;
		return false;
	}

	static function getLocalLangList() {
		$language_names = array('es' => 'Español', 'de' => 'Deutsch');
		$res = array();
		$column_names = ui_language::GetColumnNames('x_translations');
		foreach ($column_names as $column_name) {
			if ($column_name != 'tr_id_pk') {
				$column_name = explode('_', $column_name);
				$local_languages[] = $column_name[1];
			}
		}
		foreach ($local_languages as $local_language) {
			if ($local_language != 'en')
				array_push($res, array('code' => $local_language, 'name' => $language_names[$local_language]));
		}
		return $res;
	}

	static function getRemoteLangList()
	{
		$res = array();
		array_push($res, array('language' => ui_language::translate('Languages'), 'selected' => 'SELECTED'));
		$remote_languages = array('es');
		$column_names = ui_language::GetColumnNames('x_translations');
		foreach ($column_names as $column_name) {
			if ($column_name != 'tr_id_pk') {
				$column_name = explode('_', $column_name);
				$local_languages[] = $column_name[1];
			}
		}
		foreach ($remote_languages as $remote_language) {
			if (!in_array($remote_language, $local_languages))
				array_push($res, array('language' => $remote_language, 'selected' => ''));
		}
		return $res;
	}

	static function getCurrentLanguage() {
		global $controller;
		$language = $controller->GetControllerRequest('URL', 'code');
		return ($language) ? $language : '';
	}

	static function getCurrentID() {
		return true;
	}

	static function getisDeleteLanguage() {
		global $controller;
		$urlvars = $controller->GetAllControllerRequests('URL');
		return (isset($urlvars['show'])) && ($urlvars['show'] == 'Delete');
		// ALTER TABLE `x_translations` DROP `tr_es_tx`;
	}

	static function doInstallLanguage()
	{
		global $zdbh;
		global $controller;
		runtime_csfr::Protect();
		$language = $controller->GetControllerRequest('FORM', 'inLanguage');

		if (!fs_director::CheckForEmptyValue(self::ExecuteInstallLanguage($language))) {
			runtime_hook::Execute('OnAfterInstallLanguage');
			self::$ok = true;
		}
	}

	static function ExecuteInstallLanguage($language)
	{
		global $zdbh;
		if (fs_director::CheckForEmptyValue(self::CheckUpdateForErrors($language)))
			return 0;
		switch($language)
		{
			case 'es':
				$file = fopen('https://translate.burbuja.cl/projects/sentora/es-cl/default/export-translations?format=po', 'r');
				break;
			default:
				return 0;
		}
		$contents = stream_get_contents($file);
		fclose($file);
		$pattern = '/(?:msgid ")(.+)(?:"\nmsgstr ")(.+)(?:"\n)/';
		preg_match_all( $pattern, $contents, $matches);
		$pattern = '/(?:(?:^|"\n)(?:msg(?:id|str)\s")|"\n)/';
		foreach ($matches[0] as $match) {
			$translations[] = preg_split($pattern, $match, 0, PREG_SPLIT_NO_EMPTY);
		}
		$query = 'ALTER TABLE `x_translations` ADD `tr_'.$language.'_tx` TEXT NULL;';
		$zdbh->query($query);
		$query = 'UPDATE `x_translations` SET `tr_'.$language.'_tx` = CASE `tr_en_tx` ';
		foreach ($translations as $translation) {
			$query.= 'WHEN \''.addslashes(addslashes($translation[0])).'\' THEN \''.addslashes(stripslashes($translation[1])).'\' ';
		}
		$query.= 'END WHERE `tr_en_tx` IN (';
		foreach ($translations as $translation) {
			$query.= '\''.addslashes(addslashes($translation[0])).'\', ';
		}
		$query = rtrim($query, ', ');
		$query.= ');';
		$zdbh->query($query);
		return 1;
	}

	static function CheckUpdateForErrors($language)
	{
		global $zdbh;
		if (fs_director::CheckForEmptyValue($language)) {
			self::$blank = true;
			return 0;
		}
		return 1;
	}
}
