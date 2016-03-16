<?php 

/**
 * DictionaryInterface.php
 * 
 * Classes implemented DictionaryInterface are
 * part of Integration Module.
 *
 * Marcin Wachulec <wachulec.marcin@gmail.com>
 * 2016-03-10
 */

namespace App\Integration\Interfaces;

interface DictionaryInterface 
{
	/**
	 * Getting dir location for providers files
	 * @return String name of dir
	 */
	public function getLocation();

	/**
	 * Getting providers name
	 * @return String providers name
	 */
	public function getProviderCode();
	
	/**
	 * Getting translation. Usually includes arrays: enuns, user, credentials. 
	 * Other elements are depended at providers requirements.
	 * @return Array full file translation. 
	 */
	public function getTranslation();

	/**
	 * Getting Regex expression for Provider's files
	 * @return String format for providers files
	 */
	public function getFilesNameExpression();

	/**
	 * Getting disabled file names expression
	 * @return String set of disabled files
	 */
	public function getDisabledFileNames();

	/**
	 * Creating translation array
	 * @param  Array $parsedXml parsed xml file
	 * @return Self Dictionary Object
	 */
	public function buildTranslation( $parsedXml );

	/**
	 * Getting path depends on relativly ( ex.: $base = '../../storage/integration' could
	 * return 'http://example.com/storage/integration/providers_dir/' )
	 * @return String path to providers directory
	 */
	public function getPath( $base );
}