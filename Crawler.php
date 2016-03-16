<?php 

namespace App\Integration;

use Exception;
use SplFileInfo;
use LogicException;
use IteratorAggregate;
use Symfony\Component\Finder\Finder;
use App\Integration\Interfaces\CrawlerInterface;

class Crawler implements CrawlerInterface
{
	/**
	 * Expression matching valid files
	 * @var string
	 */
	private $name;

	/**
	 * Expressions matching invalid files
	 * @var array
	 */
	private $disabled = [];

	/**
	 * Amount of files taking at the time
	 * @var integer
	 */
	private $amount = 10;

	/**
	 * Directory to search in
	 * @var string
	 */
	private $directory;

	/**
	 * Symfony component to crawling in files
	 * @var Finder
	 */
	private $finder;

	/**
	 * List of excluded directories
	 * @var array
	 */
	private $excluded = [];

	/**
	 * Construct Crawler object
	 */
	public function __construct()
	{
		$this->finder = new Finder();
	}

	/**
	 * Set directory to search in
	 * @param string $dir
	 */
	public function setDirectory( $dir )
	{
		$this->directory = $dir;
		return $this;
	}

	/**
	 * Set excluded directories
	 * @param  array  $array List of excluded directories
	 * @return Self 
	 */
	public function excludeDirectories(array $array )
	{
		$this->excluded = $array;
		return $this;
	}

	/**
	 * Set name expression / opt. regex to mach results
	 * @param string  $expression expression
	 * @param boolean $regex      is regex
	 */
	public function setNameExpression( $expression, $regex = true)
	{
		if( $regex === true ) {
			$this->name = $this->buildExpression( $expression );
		} else {
			$this->name = $expression;
		}
		return $this;
	}

	/**
	 * Set disabled names expressions
	 * @param array $names
	 */
	public function setDsiabledNames( array $names, $merge = false )
	{
		if( $merge ) {
			$this->disabled = array_merge( $this->disabled, $names );
		} else {
			$this->disabled = $names;
		}
		return $this;
	}

	/**
	 * Take exactly amount of found files
	 * @param  integer $amount amount to take
	 * @return Self 
	 */
	public function take( $amount )
	{
		$this->amount = $amount;
		return $this;
	}

	/**
	 * Get list of found files
	 * @return array 
	 */
	public function get()
	{
		$this->shouldWeThrowSth();
		$this->setFinderParams();

		return $this->limit( $this->finder, $this->amount );
	}

	/**
	 * Limit iterable object
	 * @param  IteratorAggregate  $iterable Object to limit
	 * @param  integer            $limit    Limit
	 * @return arra 
	 */
	private function limit( IteratorAggregate $iterable, $limit )
	{
		$current = 0;
		$container = [];

		foreach( $iterable as $part ) {

			$container[] = $part;
			$current++;

			if( $current == $limit ) {
				break;
			}
		}
		return $container;
	}

	/**
	 * Check if everything is in its place, otherwise throw sth for admin
	 * @return void
	 */
	private function shouldWeThrowSth()
	{
		if( empty($this->name) ) {
			throw new Exception('I can`t get files without name expression!');
		} 

		if( empty($this->directory) ) {
			throw new Exception('I can`t get files without directory path!');
		} 

		if( empty( $this->amount) or $this->amount < 1 or !is_int( $this->amount ) ) {
			throw new LogicException("Invalid amount supplied: " . $this->amount);
		}

	}

	/**
	 * Set Symfony\Finder params
	 * @return Finder
	 */
	private function setFinderParams()
	{
		$this->finder->in( $this->directory );

		foreach( $this->excluded as $dir ) {
			$this->finder->exclude( $dir );
		}

		$this->finder->name( $this->name );

		foreach( $this->disabled as $disabled ) {
			$this->finder->notName( $disabled );
		}

		$this->finder->sort( function (SplFileInfo $a, SplFileInfo $b) {
			return strcmp($a->getRealpath(), $b->getRealpath());
		});

		return $this->finder;
	}

	/**
	 * Build rgexp from string
	 * @param  string $exp     basic expression
	 * @param  string $options regex options
	 * @return string          regular expression
	 */
	private function buildExpression( $exp, $options = '' ) 
	{
		return '/^' . trim( $exp, '/^$' ) . '$/' . $options;
	}
}