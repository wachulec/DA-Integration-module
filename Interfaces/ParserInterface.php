<?php 

namespace App\Integration\Interfaces;

use Symfony\Component\Finder\SplFileInfo;

interface ParserInterface 
{
	public function parse();

	public function setFile( SplFileInfo $file );

	public function getFile();

	public function get( $flush = true );

	public function flush();
}