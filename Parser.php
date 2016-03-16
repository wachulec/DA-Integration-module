<?php 

namespace App\Integration;

use ZipArchive;
use SimpleXMLElement;
use Symfony\Component\Finder\SplFileInfo;
use App\Integration\Interfaces\ParserInterface;

class Parser implements ParserInterface
{
	/**
	 * Parsing file holder
	 * @var SplFileInfo
	 */
	private $file;

	/**
	 * Content of parsing file
	 * @var string
	 */
	private $content;

	/**
	 * Parsed array
	 * @var array
	 */
	private $parsed;

	/**
	 * Parsed object
	 * @var SimpleXMLElement
	 */
	private $raw;

	/**
	 * In progress file sufix
	 * @var string
	 */
	private $sufix = '.ip';

	/**
	 * Set file to parse
	 * @param SplFileInfo $file
	 */
	public function setFile( SplFileInfo $file )
	{
		$this->file = $file;
		return $this;
	}

	/**
	 * Get current parsing file
	 * @return SplFileInfo
	 */
	public function getFile()
	{
		return $this->file;
	}

	/**
	 * Load content and parse file
	 * @return Self
	 */
	public function parse()
	{
		$this->loadFile();
		$this->parseXml();
		return $this;
	}

	/**
	 * Set sufix used for mark currently processing file
	 * @param string $sufix
	 */
	public function setSufix( $sufix )
	{
		$this->sufix = $sufix;
		return $this;
	}

	/**
	 * Get parsed array and optionally flush Parser
	 * @param  boolean $flush Flush after this operation
	 * @return array          Parsed array
	 */
	public function get( $flush = true )
	{
		if( !( $this->raw instanceof SimpleXMLElement) ) {
			$this->parse();
		}
		$parsed = $this->xmlToArray( $this->raw );

		if( $flush ) {
			$this->flush();
		}

		return $parsed;
	}

	/**
	 * Get raw parsed object
	 * @return SimpleXMLElement
	 */
	public function raw()
	{
		return $this->raw;
	}

	/**
	 * Flush current objects variables
	 * @return Self
	 */
	public function flush()
	{
		$this->content = '';
		$this->file = '';
		$this->raw = '';
		return $this;
	}

	/**
	 * Parse xml file content to SimpleXMLElement
	 * @return Self
	 */
	private function parseXml()
	{
		$this->raw = new SimpleXMLElement( $this->content, LIBXML_NOCDATA );
		return $this;
	}

	/**
	 * Convert parsed object to multidimensional array
	 * @param  mixed  $xml Input to parse
	 * @param  array  $out Output holder
	 * @return array       Parsed array
	 */
	private function xmlToArray( $xml, $out = [] )
	{
		foreach ( (array) $xml as $key => $node ) {

			if( $node instanceof SimpleXMLElement ) {
				$out[ $key ] = $this->xmlToArray( $node );
			} else if( is_array($node) ) { 
				$out[ $key ] = $this->xmlToArray( $node ); // not sure why I split it ;x
			} else {
				$out[ $key ] = $node;
			}
			
			if( isset( $out[ $key ]['@attributes'] ) ) {
				foreach( $out[ $key ]['@attributes'] as $attrKey => $attrValue ) {
					$out[ $key ][ $attrKey ] = $attrValue;
				}
				unset( $out[ $key ]['@attributes'] );
			}

		}

		return ( count( $out ) == 0 ? '' : $out );
	}

	/**
	 * Get content of file inside zip archive
	 * @param  SplFileInfo $file Zip archive
	 * @return string            File content
	 */
	private function handleZipFile( SplFileInfo $file )
	{
		$zip = new ZipArchive;
		$zip->open( $file->getPathname() . $this->sufix );
		return $zip->getFromName( $zip->getNameIndex( 0 ) );
	}

	/**
	 * Load file content or get it from zip
	 * @return void
	 */
	private function loadFile()
	{
		if( empty($this->content) ) {
			if( empty($this->file) ) {
				throw new Exception('Which file should I parse, you ...!');
			} else {
				if( strtolower( $this->file->getExtension() ) == 'zip' ) {
					$this->content = $this->handleZipFile( $this->file );
				} else {
					$this->content = $this->file->getContents();
				}
			}
		}
	}

}