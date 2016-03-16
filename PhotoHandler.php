<?php 

namespace App\Integration;

use App\Uploader;
use Intervention\Image\ImageManager;
use App\Integration\Interfaces\PhotoHandlerInterface;

class PhotoHandler extends Uploader implements PhotoHandlerInterface
{
	public function __construct()
	{
		parent::__construct();

		$this->setType('image');
	}

	public function path( $path )
	{
		$this->setFile( $path );
		return $this;
	}

	public function save( $name = 'random' )
	{
		return parent::save( $name );
	}
}