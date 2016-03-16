<?php

/**
 * DictionaryAbstract.php
 *
 * Marcin Wachulec <wachulec.marcin@gmail.com>
 * 2016-03-10
 */

namespace App\Integration\Dictionaries;

use SplFileInfo;
use Exception;
use App\Models\Enunciations;
use App\Integration\Interfaces\DictionaryInterface;
use App\Integration\Interfaces\StorageInterface;
use Illuminate\Database\Eloquent\Model;

abstract class DictionaryAbstract implements DictionaryInterface
{
    // vars
    //
    
    /**
     * Parrsed file holded in array. 
     * @var array
     */
    private $parsedXml           = [];

    /**
     * Translation. Keeps translated information from $parsedXml
     * @var array
     */
    private $translation         = [];

    /**
     * Config element. Providers location in storage/interface/ dir
     * @var string
     */
    private $location            = '';

    /**
     * Full providers Name
     * @var string
     */
    private $providerName        = '';

    /**
     * Uniqe providers code (usually 2 signs)
     * @var string
     */
    private $providerCode        = '';

    /**
     * Config element. Set of uniq rules. Every provider has different names and xml file format
     * @var array
     */
    private $rules               = [];

    /**
     * Regexp. Providers file name format
     * @var string
     */
    private $filesNameExpression = '';    

    /**
     * Set of providers file names, which shouldnt be taken by Crawler class.
     * @var array
     */
    private $disabledFileNames   = [];

    /**
     * Name of class responsibe for db table connection.
     * Use to car models translations.
     * @var string
     */
    private $integrationModel         = '';

    /**
     * Name of class responsibe for db table connection.
     * Use to get accessories translations.
     * @var string
     */
    protected $intAccessoriesModel   = '';

    // methods
    //
    
    // methods / public zone
    //
    
    /**
     * Construct dictionary object.
     * Always run protected init function.
     * @param string $model initialize $integrationModel
     * @param string $accessoriesModel initialize accessoriesModel
     */
    public function __construct( $model = "\App\Models\Integration", $accessoriesModel = "\App\Models\IntAccessories" )
    {
        $this->integrationModel = $model;
        $this->intAccessoriesModel = $accessoriesModel;
        $this->init();
    }
    
    /**
     * Getting setted parsed file
     * @return array parsedd file
     */
    public function getParsedXml()
    {
        return $this->parsedXml;
    }

    /**
     * Setting parsed file
     * @param array $parsedXml 
     * @return Self
     */
    public function setParsedXml( array $parsedXml )
    {
        $this->parsedXml = $parsedXml;
        return $this;
    }

    /**
     * Getting translation array
     * @return array
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * Setting new translation array. Should include enuns, credentials end optionally user keys.
     * @param array
     */
    public function setTranslation( array $translation )
    {
        $this->translation = $translation;
        return $this;
    }

    /**
     * Getting providers dir location (in storage/integration)
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Setting new locatin dir name
     * @param string
     * @return Self
     */
    public function setLocation( $location )
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Two letter providers code
     * @return string
     */
    public function getProviderCode()
    {
        return $this->providerCode;
    }

    /**
     * Setting two letter providers code
     * @param string
     */
    public function setProviderCode( $providerCode )
    {
        $this->providerCode = $providerCode;
        return $this;
    }

    /**
     * Getting full rules array for provider
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Setting new array with providers rules
     * @param array new set of rules
     * @return Self
     */
    public function setRules( array $rules )
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Getting regexp expression for providers file format.
     * @return string
     */
    public function getFilesNameExpression()
    {
        return $this->filesNameExpression;
    }

    /**
     * Setting regexp expression fo providers file format.
     * @param string
     */
    public function setFilesNameExpression( $expression )
    {
        $this->filesNameExpression = $expression;
        return $this;
    }

    /**
     * Getting set of providers file names which should be excluded.
     * @return array
     */
    public function getDisabledFileNames()
    {
        return $this->disabledFileNames;
    }

    /**
     * Setting array of providers file names which should be excluded
     * @param array
     */
    public function setDisabledFileNames( array $disabledFileNames )
    {
        $this->disabledFileNames = $disabledFileNames;
        return $this;
    }

    /**
     * Getting path depends on relativly ( ex.: $base = '../../storage/integration' could
     * return 'http://example.com/storage/integration/providers_dir/' )
     * @return String path to providers directory
     */
    public function getPath( $base )
    {
        return realpath( rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR . trim( $this->location, '/\\' ) );
    }

    /**
     * Getting credentials, elelemnt of translation array
     * @return array
     */
    public function getCredentials()
    {
        return $this->getTranslation()['credentials'];
    }

    /**
     * Setting credentials, elelemnt of translation array
     * @param array
     * @return Self
     */
    public function setCredentials( array $credentials )
    {
        $this->translation['credentials']  = $credentials;
        return $this;
    }

    /**
     * Getting user, elelemnt of translation array
     * @return array
     */
    public function getUser()
    {
       return $this->getTranslation()['user'];
    }

    /**
     * Setting user, elelemnt of translation array
     * @param array
     * @return Self
     */
    public function setUser( array $user )
    {
        $this->translation['user'] = $user;
        return $this;
    }

    /**
     * Getting enuns, main elelemnt of translation array
     * @return array
     */
    public function getEnuns()
    {
       return $this->getTranslation()['enuns'];
    }

    /**
     * Setting enuns, main elelemnt of translation array
     * @param array
     * @return Self
     */
    public function setEnuns( array $enuns )
    {
        $this->translation['enuns'] = $enuns;
        return $this;
    }

    /**
     * Getting full providers name.
     * @return string
     */
    public function getProviderName()
    {
        return $this->providerName;
    }

    /**
     * Setting new full providers name
     * @param string
     * @return Self
     */
    public function setProviderName( $providerName )
    {
        $this->providerName = $providerName;
        return $this;
    }

    /**
     * Creating translation array
     * @param  Array $parsedXml parsed xml file
     * @return Self
     */
    public function buildTranslation( $parsedXml )
    {
        $this->setParsedXml( $parsedXml );

        $this->translation = [
            'user'          => $this->buildUser(),
            'credentials'   => $this->buildCredentials(),
            'enuns'         => $this->buildEnuns()
        ];

        return $this;
    }

    /**
     * Method using to adding new sets of rules for provider.
     * @param  string $index key for pushing rules array
     * @param  array $array set of rules
     * @return Self
     */
    public function pushRules( $index, array $array )
    {
        $this->rules[ $index ] = $array;

        return $this;
    }

    // methods / private
    //

    /**
     * Helper method. Used in translate process. Used in method parseArrayValues().
     * Method returns key of given rules sub-array by regexp pattern in this keys item.
     * Used for multi-nested rules arrays which gives exact value instead simple translation.
     * @param  array $rules
     * @param  string $value
     * @return mixed
     */
    private function searchSubruleForGivenValue( array $rules, $value )
    {
        $result = false;
        $fakeArr = [];
        foreach( $rules as $key => $rule ) {
            if( preg_match('/'.$rule.'/', $value, $fakeArr) === 1 ) {
                $result = $key;
            }
        }

        return $result;
    }

    /**
     * Creating translation sub-array with cars accessories.
     * @param  array $enun one enun from enuns array
     * @return array merged technical and additional accessories array
     */
    private function buildAccessories( $enun )
    {
        $technical = $this->translate( 'technical', $enun );
        $additional = $this->getAdditionalAccessories( $enun );

        return array_merge( $technical, $additional );
    }

    // methods / protected zone
    //
    
    /**
     * Initializing rules method. Could be override by extending class to
     * add more uniqe rules config.
     * @return Self
     */
    protected function init()
    {
        $this->initCategoriesRules();

        return $this;
    }

    /**
     * Method give value from multi-nested array by given chain of keys. 
     * @param  array $path Array of following keys
     * @param  array $source Array with data
     * @return mixed value
     */
    protected function selectParsedData( $path, $source = null )
    {
        if( !is_array( $path ) ) {
            throw new Exception( "Input \$path argument have to be an array!" );
        }

        if( !$source ) {
            $source = $this->parsedXml;
        }

        foreach( $path as $step ) {
            if( is_array($source) && array_key_exists($step, $source) ) {
                $source = $source[$step];
            }
            else {
                $source = [];
                break;
            }
        }

        return $source;
    }

    /**
     * Method gives array of transaltions depends on $rules.
     * @param  array $rules instruction how $source should be tranlated
     * @param  array $source fresh data to translate
     * @return array $result translated data
     */
    protected function translate( $rules, $source )
    {
        $result = [];
        foreach( $this->rules[$rules] as $to => $from ) {
            $result[$to] = $this->parseArrayValues( $source, $from );
        }

        return $result;
    }

    /**
     * Method recursivly looking for value. If given $key is array, method
     * trying to get first key of $key array and use it as key in $array.
     * If given $key is string returns value from $array by this key.
     * Method allow to handle with multi-nested array rules.
     * @param  array $array array with data
     * @param  mixed $key string or array to gain key
     * @return mixed if search will succeed return found value (string, integer). Otherwise returns false.
     */
    protected function parseArrayValues( $array, $key )
    {
        if( !is_array($key) ) {
            return ( array_key_exists($key, $array) && $array[$key] != "" ) ? trim($array[$key]) : false;
        }

        if( array_key_exists(key($key), $array) ) {
            $arrayValue = $array[key($key)];
            $key = current($key);
        } else {
            return false;
        }

        if(is_array($key) && !is_array($arrayValue) && count($key) > 1) {
            return $this->searchSubruleForGivenValue($key, $arrayValue);
        }

        return $this->parseArrayValues( $arrayValue, $key );
    }

    /**
     * Allow to add new uniqe translation into translation array
     * @param  string $index key for new translation
     * @param  array $item set of new translations
     * @return Self
     */
    protected function pushTranslation( $index, $item )
    {
        $this->translation[$index] = $item;
        return $this;
    }

    /**
     * Deleting translation array by given index from translation array and returning it
     * @param  string $index key for poping array
     * @return array $pop deleted array
     */
    protected function popTranslationByIndex( $index )
    {
        $pop = array_key_exists($index, $this->translation) ? $this->translation[$index] : null;
        if( !array_key_exists($index, $this->translation) ) {
            throw new Exception("Poping an non exist element from [\$translation] array at index: $index.");
        } else {
            unset( $this->translation[$index] );
        }

        return $pop;
    }

    /**
     * In case we have return array of arrays, but parsing will give one array,
     * we have to correct by this function
     * @param  reference &$enun
     * @param  string $index
     * @return Self
     */
    protected function singleEnunCorrection( &$enun, $index = null )
    {
        if( ( is_array($enun) && array_key_exists($index, $enun) ) || is_string($enun) ) {
            $enun = [$enun];
        }

        return $this;
    }

    /**
     * Looking in database for cars match.
     * @param  array $enun
     * @return integer $subID false if search faild. id of car submodel if search succeed
     */
    protected function adjustCarNames( $enun )
    {
        $slug = $this->slugedFullCarName( $enun );

        $subID = (new $this->integrationModel())->match( $slug, $this->getProviderCode() );
        $subID = ($subID != 0) ? $subID : false;

        return $subID;
    }

    /**
     * Sluggin full name of car
     * @param  array $enun parsed data
     * @return string $name sluged name
     */
    protected function slugedFullCarName( $enun )
    {
        $name = $this->getCarCategory( $enun )." ".$this->getFullCarName( $enun );

        $name = str_replace([',','.',';',':','+'], '-', $name);

        return str_slug( $name );
    }

    /**
     * Adding to given array another array with specified keys and null values
     * @param  reference &$enun 
     * @return Self
     */
    protected function fillWithOtherRules( &$enun )
    {
        $enun = array_merge(array_fill_keys( $this->getRules()['other'], null ), $enun);

        return $this;
    }

    /**
     * Retrieving photos from enuns
     * @param  array $enun
     * @return array $result paths of photos
     */
    protected function getPhotos( $enun )
    {
        $result = $this->selectParsedData( ['zdjecia', 'zdjecie'], $enun );
        $this->singleEnunCorrection( $result );

        return $result;
    }

    /**
     * Method creats array of enuns by whole rules
     * @return array enuns translation
     */
    protected function buildEnuns()
    {
        $enuns = $this->selectEnuns();

        $translation = [];
        foreach( $enuns as $enun ) {
            $translatedEnun = $this->translate( 'secondary', $enun );
            $translatedEnun['submodel_id'] = $this->adjustCarNames( $enun );
            $translatedEnun['photos'] = $this->getPhotos( $enun );
            $translatedEnun['header'] = $this->getCustomizeTitle( $enun );
            $translatedEnun['credentials'] = $this->buildCredentials();
            $translatedEnun['accessories'] = $this->buildAccessories( $enun );
            $translatedEnun['provider_code'] = $this->getProviderCode();
            $translatedEnun['route'] = str_slug( $translatedEnun['header'] );
            $this->fillWithOtherRules( $translatedEnun );
            //
            $translation[] = $translatedEnun;
        }

        return $translation;
    }

    /**
     * Creating customize title for provider
     * @param  array $enun
     * @return string enun header
     */
    protected function getCustomizeTitle( $enun )
    {
        return trim( $this->getFullCarName( $enun ) );
    }

    /**
     * Init method to push categories rules into $rules array
     * @return Self
     */
    protected function initCategoriesRules()
    {
        $this->pushRules('categories',[
            'o' => 'osobowe',
            'd' => 'dostawcze',
            'c' => 'ciezarowe',
            'm' => 'motocykle',
            'a' => 'autobusy'
        ]);

        return $this;
    }

    // methods / abstract zone
    // 
    
    /**
     * Creating user translation set. Required by buildTranslation() method
     * @return array $user
     */
    abstract protected function buildUser();

    /**
     * Creating credentials translation set. Required by buildTranslation() method
     * @return array $credentials
     */
    abstract protected function buildCredentials();

    /**
     * Return full name of car depends on given parsed file. Required to looking matches in database.
     * @param  array $enun
     * @return string
     */
    abstract protected function getFullCarName( $enun );

    /**
     * Required in buildTranslation() method to select all enuns in setted file.
     * @return array set of enuns
     */
    abstract protected function selectEnuns();

    /**
     * Getting additional accessories from setted file.
     * @param  array $enun
     * @return array 
     */
    abstract protected function getAdditionalAccessories( $enun );

    /**
     * Getting category of car from enun. 
     * @param  array $enun
     * @return string 
     */
    abstract protected function getCarCategory( $enun );

    /**
     * Getting file name format for given user token.
     * @param  string $token
     * @param  string $sufix
     * @return mixed false if wrong format of token, string if succeed
     */
    abstract public function getNamesForToken( $token, $sufix = '' );
}