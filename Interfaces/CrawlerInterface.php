<?php 

namespace App\Integration\Interfaces;

interface CrawlerInterface 
{
	/**
	 * Set dictionary containing needed files
	 * @param string $dir
	 */
	public function setDirectory( $dir );

	/**
	 * Set list of excluded directories
	 * @param  array  $array excluded directories
	 */
	public function excludeDirectories(array $array );

	/**
	 * Set regular expression for matching files by name
	 * @param string $expression
	 * @param string $modifiers
	 */
	public function setNameExpression( $expression, $regex = true);

	/**
	 * Set list of disabled names
	 * @param arra $names
	 */
	public function setDsiabledNames( array $names, $merge = false );

	/**
	 * Take exact amount of files at the time ( by default 10 )
	 * @param  integer $amount
	 */
	public function take( $amount );

	/**
	 * Get files object
	 * @return array founded files
	 */
	public function get();
}