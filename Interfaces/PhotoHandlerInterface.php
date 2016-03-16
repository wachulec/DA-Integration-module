<?php 

namespace App\Integration\Interfaces;

interface PhotoHandlerInterface 
{
	public function path( $path );

	public function save();
}