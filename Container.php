<?php 

namespace App\Integration;

use Closure;
use Exception;
use SplFileInfo;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use App\Integration\Interfaces\DictionaryInterface;
use App\Integration\Interfaces\ParserInterface;
use App\Integration\Interfaces\StorageInterface;
use App\Integration\Interfaces\CrawlerInterface;

class Container 
{
    /**
     * In progress file sufix
     */
    const IN_PROGRESS = '.ip';

    /**
     * Done file sufix
     */
    const DONE_FILE = '.done';

    /**
     * Disabled file sufix
     */
    const DISABLED_FILE = '.tk';

    /**
     * Shortcut for directory separator const
     */
    const DS = DIRECTORY_SEPARATOR;

    /**
     * Should run pipeline in sandbox
     * @var boolean
     */
    private $sandbox = true;

    /**
     * Integration Crawler holder
     * @var App\Integrations\Crawler
     */
    private $crawler;

    /**
     * Integration Parser holder
     * @var App\Integrations\Parser
     */
    private $parser;

    /**
     * Integration Storage holder
     * @var App\Integrations\Storage
     */
    private $storage;

    /**
     * Container for completed files
     * @var array
     */
    private $done = [];

    /**
     * Container for corrupted files
     * @var array
     */
    private $corrupted = [];

    /**
     * Integration Dictionaries holder
     * @var Array
     */
    private $dictionaries;

    /**
     * Amount of processing files at the time
     * @var integer
     */
    public $amount = 2;

    /**
     * Path where xml`s are stored
     * @var string
     */
    public $payloadPath = __DIR__ . self::DS . '..' . self::DS . '..' . self::DS . 'storage' . self::DS . 'integration';

    /**
     * Logs path
     * @var string
     */
    public $logPath = __DIR__ . self::DS . '..' . self::DS . '..' . self::DS . 'storage' . self::DS . 'logs';

    /**
     * Monolog logs name
     * @var string
     */
    public $logName = 'integration.log';

    /**
     * Monolog (logging) object
     * @var Logger
     */
    private $monolog;

    /**
     * Build Integration container and bind Objects with DI
     * @param CrawlerInterface $crawler
     * @param ParserInterface  $parser 
     * @param StorageInterface $storage
     */
    public function __construct(
        CrawlerInterface $crawler,
        ParserInterface $parser,
        StorageInterface $storage,
        array $dictionaries
    ) {
        $this->crawler          = $crawler;
        $this->parser           = $parser;
        $this->storage          = $storage;

        $this->setDictionaries( $dictionaries );

        $this->monolog          = $this->buildMonolog( $this->logName );
    }

    /**
     * Get injected crawler object
     * @return App\Integrations\Crawler
     */
    public function getCrawler()
    {
        return clone $this->crawler;
    }

    /**
     * Inject new crawler object
     * @param CrawlerInterface $crawler
     */
    public function setCrawler(CrawlerInterface $crawler)
    {
        $this->crawler = $crawler;
        return $this;
    }

    /**
     * Get injected parser object
     * @return App\Integrations\Parser
     */
    public function getParser()
    {
        return clone $this->parser;
    }

    /**
     * Inject new parser object
     * @param ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
        return $this;
    }

    /**
     * Get injected storage object
     * @return App\Integrations\Storage
     */
    public function getStorage()
    {
        return clone $this->storage;
    }

    /**
     * Inject new storage object
     * @param StorageInterface $storage [description]
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Get array of dictionaries Objects
     * @return Array
     */
    public function getDictionaries()
    {
        return clone $this->dictionaries;
    }

    /**
     * Get dictionary object
     * @param  string $name Dictionary name
     * @return DictionaryInterface
     */
    public function getDictionary( $name )
    {
        return clone $this->dictionaries[ $name ];
    }

    /**
     * Insert new set of dictionaries
     * @param Array $dictionaries
     */
    public function setDictionaries(array $dictionaries)
    {
        $this->dictionaries = [];
        foreach( $dictionaries as $name => $dictionary ) {
            $this->pushDictionary( $name, $dictionary );
        }
        return $this;
    }

    /**
     * Push single dictionary to existing Array
     * @param  DictionaryInterface $dictionary
     */
    public function pushDictionary( $name, DictionaryInterface $dictionary)
    {
        $this->dictionaries[ $name ] = $dictionary;
    }

    /**
     * Add many dictionaries to local array
     * @param Array $newDictionaries
     * @return Array $this->dictionaries
     */
    public function addDictionaries(array $newDictionaries)
    {
        foreach($newDictionaries as $d) {
            pushDictionary($d);
        }
        return $this->dictionaries;
    }

    /**
     * Run The Machine, crawl in files, parse xml`s to
     * php arrays, translate it with dictionaries and
     * save into database
     * 
     * @param  string $dictionary Dictionary name
     * @return integer            Number of done files
     */
    public function run( $dictionaryCode )
    {
        $this->giveUsSomeTime();

        if( !isset( $this->dictionaries[$dictionaryCode] ) ) {
            throw new Exception('Unable to find ' . $dictionaryCode . ' dictionary ');
        }

        $files = $this->getFilesForDictionary( $dictionaryCode );

        if( !is_array( $files ) ) {
            if( !( $files instanceof Exception ) ) {
                $this->monolog->addCritical('Finder or Dictionary failed: ' . var_export( $files, true ));
            }

            throw new Exception('Stopping further execution without files! More info in integration logs!');            
        }

        foreach ( $files as $file ) {

            $result = $this->sandbox( function () use ( $file, $dictionaryCode ) {
                return $this->pipeline( $file, $dictionaryCode );
            });

            if( $result instanceof Exception ) {
                $this->corrupted[] = ['file' => $file, 'exception' => $result];
            } else {
                $this->done[] = $file;
            }
        }

        return count( $this->done );
    }

    /**
     * Check if user exists in database by his credentials
     * @param  SplFileInfo $file File which we use to get credentials
     * @return boolean           User exists
     */
    private function checkIfUserExists( SplFileInfo $file )
    {
        $credentials = $dictionary->getCredentials( $file );

        return $this->getStorage()->userExists( $credentials['token'], $credentials['provider_code'] );
    }
 
    /**
     * Crawl throught files to get wanted one
     * @param  string $dictionaryCode Dictionay name
     * @return mixed                  Array or exception
     */
    private function getFilesForDictionary( $dictionaryCode )
    {
        $crawler = $this->getCrawler();
        $dictionary = $this->getDictionary( $dictionaryCode );

        $files = $this->sandbox( function () use ( $crawler, $dictionary ) {

            $files = $crawler->setDsiabledNames( $dictionary->getDisabledFileNames() )
                ->setDirectory( $dictionary->getPath( $this->payloadPath ) )
                ->setNameExpression( $dictionary->getFilesNameExpression() )
                ->take( $this->amount )->get();

            return $files;

        });

        return $files;
    }

    /**
     * Set new maximum time execution
     * @param  integer $time Time in seconds
     * @return self
     */
    private function giveUsSomeTime( $time = 599 )
    {
        set_time_limit( $time );
        ini_set( 'max_execution_time', $time );
        return $this;
    }

    /**
     * Get array of corrupted files which processing failed
     * @return array
     */
    public function getCorruptedFiles()
    {
        return $this->corrupted;
    }

    /**
     * Get list of already processed files
     * @return array
     */
    public function getDoneFiles()
    {
        return $this->done;
    }

    /**
     * Send file through pipeline and process data
     * @param  SplFileInfo         $file       File to process
     * @param  DictionaryInterface $dictionary Dictionary for translating data
     * @return boolean                         Mission result
     */
    public function pipeline( SplFileInfo $file, $dictionaryCode )
    {
        $dictionary = $this->getDictionary( $dictionaryCode );

        $this->lockFile( $file );
        
        $parsed = $this->getParser()->setFile( $file )->setSufix( self::IN_PROGRESS )->get();
        
        $data =  $dictionary->buildTranslation( $parsed )->getTranslation();

        if( $this->getStorage()->userExists( $data['credentials']['token'], $data['credentials']['provider_code'] ) ) {
            $this->processTranslation( $data ); 

            $this->unlockFile( $file );

        } else {
            $this->getStorage()->noticeTokenWithoutUser( 
                $data['credentials']['token'],
                $data['credentials']['provider_code'],
                $data['credentials']['name']
            );

            $this->unlockFile( $file, false );
            $this->disableFiles( $dictionary, $data['credentials']['token'] );
        }             

    }

    /**
     * Process translated data
     * @param  array  $data Data
     * @return void
     */
    private function processTranslation( array $data )
    {
        if( isset( $data['user'] ) ) {
            $this->sandbox( function () use ( $data ) {
                return $this->getStorage()->updateUser( $data['user'] );
            });
        }
        
        if( isset( $data['delete'] ) and !empty( $data['delete'] ) ) {
            $this->sandbox( function () use ( $data ) {
                return $this->getStorage()->deleteEnuns( $data['delete'], $data['credentials']['provider_code'] );
            } );
        }

        if( isset( $data['callback'] ) and is_callable( $data['callback'] ) ) {
            $this->sandbox( function () use ( $data ) {
                return $this->getStorage()->executeCallback( $data['callback'], $data['credentials'] );
            });
        }

        
        if( isset( $data['enuns'] ) and !empty( $data['enuns'] ) ) {
            foreach( $data['enuns'] as $translation ) {
                
                $this->sandbox( function () use ( $translation ) {
                    return $this->getStorage()->fill( $translation )->store();
                });

            }
        }

    }

    /**
     * Disable file without related user
     * @param  SplFileInfo $file 
     * @return [type]            [description]
     */
    public function disableFiles( DictionaryInterface $dictionary, $token )
    {

        $crawler = $this->getCrawler();

        $files = $this->sandbox( function () use ( $crawler, $dictionary, $token ) {

            $files = $crawler->setDsiabledNames( $dictionary->getDisabledFileNames() )
                ->setDirectory( $dictionary->getPath( $this->payloadPath ) )
                ->setNameExpression( $dictionary->getNamesForToken( $token ), false )
                ->get();

            return $files;

        });

        $counter = 0;

        foreach( $files as $f ) {
            $pathname = $f->getPathname();

            if( file_exists($pathname) ) {

                rename( $pathname, $pathname . self::DISABLED_FILE );
                $counter++;
            }
        }

        return $counter;

    }

    public function enableFiles($token, $providerCode)
    {
        $crawler = $this->getCrawler();
        $dictionary = $this->getDictionary( $providerCode );

        $files = $this->sandbox( function () use ( $crawler, $dictionary, $token ) {

            $files = $crawler->setDsiabledNames( $dictionary->getDisabledFileNames() )
                ->setDirectory( $dictionary->getPath( $this->payloadPath ) )
                ->setNameExpression( '*'.$token.'*.zip.tk', false )
                ->get();

            return $files;

        });

        $counter = 0;

        foreach( $files as $f ) {
            $pathname = $f->getPathname();

            if( file_exists($pathname) ) {
                $newname = preg_replace( '/' . preg_quote( self::DISABLED_FILE ) . '$/', "", $pathname);
                
                rename( $pathname, $newname );
                $counter++;
            }
        }

        return $counter;
    }

    /**
     * Lock file by adding special suffix to name
     * @param  SplFileInfo $file File to lock
     * @return boolean           Locked successfully
     */
    public function lockFile( SplFileInfo $file )
    {
        return rename( $file->getPathname(), $file->getPathname() . self::IN_PROGRESS );
    }

    /**
     * Unlock file by removing special suffix from name
     * @param  SplFileInfo $file File to unlock
     * @return boolean           <u>u</u>nlocked successfully
     */
    public function unlockFile( SplFileInfo $file, $done = true )
    {
        if( $done ) {
            return rename( $file->getPathname() . self::IN_PROGRESS, $file->getPathname() . self::DONE_FILE );
        } else {
            return rename( $file->getPathname() . self::IN_PROGRESS, $file->getPathname() );
        }
    }

    /**
     * Run given closure in sandbox environment
     * @param  Closure $callable
     * @return boolean            execution result
     */
    private function sandbox( Closure $callable, $logException = true )
    {
        ob_start();
        try {
            $run = $callable();
        } catch ( Exception $e ) {
            $run = $e;
        }
        ob_end_clean();

        if( $logException === true and $run instanceof Exception ) {
            $this->logException( $run );
        }

        return $run;
    }

    /**
     * Log exception into log files
     * @param  Exception $e Exception to log
     * @return boolean
     */
    private function logException( Exception $e )
    {
        $message = sprintf('%s: "%s" in %s at line %d', get_class( $e ), $e->getMessage(), $e->getFile(), $e->getLine() );

        return $this->monolog->addCritical( $message );
    }

    /**
     * Build monolog object
     * @return Logger   Monolog\Logger object
     */
    private function buildMonolog( $name )
    {
        $logger = new Logger('name');
        $logger->pushHandler( new RotatingFileHandler( realpath( $this->logPath ) . self::DS . $this->logName, Logger::CRITICAL) );

        return $logger;
    }

}