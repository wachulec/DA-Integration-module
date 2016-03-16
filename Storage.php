<?php 

namespace App\Integration;

use Exception;
use Validator;
use App\Models\Users;
use App\Models\Images;
use App\Models\Tokens;
use App\Models\Counter;
use App\Models\UserToken;
use App\Models\Accessories;
use App\Models\Enunciations;
use App\Integration\Interfaces\StorageInterface;

class Storage implements StorageInterface
{
    /**
     * Published status number
     */
    const STATUS_OK = 1;

    /**
     * Enun with error status number
     */
    const STATUS_ERROR = 7;

    /**
     * Maximum amount of photos allowed for enunciation
     */
    const MAX_PHOTOS = 24;

    /**
     * Holder for raw params passed from dictionary
     * @var array
     */
    private $raw;

    /**
     * Final, converted and stripped params
     * @var array
     */
    private $params;

    /**
     * Class list for creating new instaces
     * @var array
     */
    private $models = [
        'enun'             	=> Enunciations::class,
        'user'             	=> Users::class,
        'usertoken'   		=> UserToken::class,
        'image'            	=> Images::class,
        'photoHandler'     	=> PhotoHandler::class,
        'accessories'    	=> Accessories::class,
        'tokens'			=> Tokens::class,
        'counter'           => Counter::class
    ];

    /**
     * Enunciation model holder
     * @var Enunciation
     */
    private $enun;

    /**
     * User model holder
     * @var Users
     */
    private $user;

    /**
     * Raw photos container
     * @var array
     */
    private $photos;

    /**
     * Accessories container
     * @var array
     */
    private $accessories;

    /**
     * Validation rules for given params
     * @var array
     */
    private $rules = [
        'header'        => 'required|max:255',
        'content'       => 'required',
        'price'         => 'required|numeric|min:100',
        'province'      => 'required|max:255',
        'distance'      => 'required|numeric',
        'year'			=> 'required|numeric|min:1900',
        'provider_id'	=> 'required',
        'provider_code' => 'required',
        'submodel_id'	=> 'required'
    ];

    /**
     * Filds which are allowed to store in database
     * @var array
     */
    private $fillable = [
        'header',
        'content',
        'province',
        'price',
        'year',
        'distance',
        'status',
        'user_id',
        'submodel_id',
        'route',
        'provider_id',
        'provider_code'
    ];

    /**
     * Credentials container
     * @var array
     */
    private $credentials = [];

    /**
     * Construct object, optionally pass raw parameters
     * @param array $raw Raw parameters
     */
    public function __construct( array $raw = [] )
    {
        $this->fill( $raw );
    }

    /**
     * Pas raw parameters from dictionary
     * @param  array $raw Raw parameters
     * @return self 
     */
    public function fill( array $raw )
    {
        $this->raw = $raw;
        return $this;
    }

    /**
     * Check and store given paramters in database
     * @return Enunciation
     */
    public function store()
    {
        if( empty($this->raw) ) {
            throw new Exception('I`m not able to process data without data. lol');    
        }

        $this->params = $this->raw;

        $this->cutParams( ['credentials', 'photos', 'accessories'] );
        $this->handleUser();

        $this->bindProvince( $this->user );

        $this->dropAdditionalParams();

		$enough = $this->enoughParams();
        
        $model = $this->getEnunModel();
        $model->fill( $this->params );
        $model->save();
        
        $this->bindAccessories( $model, $this->accessories );

        $this->bindPhotos( $model, $this->photos );

        if( $enough ) {
            $model->makePublished();
        } else {
            $this->bindCounter( $model );
        }

        return $model;

    }

    /**
     * Get enun model, if alredy exists restore it from database
     * @return Enunciation
     */
    public function getEnunModel()
    {   
        try {
            return $this->getModel('enun')->where( 'provider_id', '=', $this->params['provider_id'] )->where( 'provider_code','=',$this->params['provider_code'] )->firstOrFail();
        } catch (Exception $e) {
            return $this->getModel('enun');
        }
    }

    /**
     * Run dictionary callback
     * @param  callable $callback    Callback function
     * @param  array    $credentials Credentials to identify user
     * @return mixed                 Callback result
     */
    public function executeCallback( callable $callback, array $credentials )
    {
        $this->findUserByToken( $credentials['token'], $credentials['provider_code'] );

        return $callback( $this->getModel('enun'), $this->user );
    }

    /**
     * Update user with new data
     * @param  array  $data Users information
     * @return mixed 
     */
    public function updateUser( $data ) 
    {
        $this->findUserByToken( $data['token'], $data['provider_code'] );

        unset( $data['token'], $data['provider_code'] ); 

        return $this->user->update( $data );
    }

    public function deleteEnuns( array $enuns, $provider )
    {
        $success = 0;
        foreach( $enuns as $provider_id ) {

            $e = $this->getModel('enun')
                ->where( 'provider_id', '=', $provider_id )
                ->where( 'provider_code', '=', $provider )
                ->first();

            if( $e != null and $e->delete() ) {
                $success++;
            }
        }

        return $success;
    }

    /**
     * Drop unnecessary things from params container
     * @return self
     */
    private function dropAdditionalParams()
    {
        foreach( $this->params as $key => $value ) {
            if( !in_array($key, $this->fillable) ) {
                unset( $this->params[$key] );
            }
        }
        return $this;
    }

    /**
     * Check if param container contains all required parameters
     * @return boolean checking result
     */
    private function enoughParams()
    {
        if( $this->params['submodel_id'] == null or $this->params['submodel_id'] == false or $this->params['submodel_id'] == 0 ) {
            $this->params['status'] = static::STATUS_ERROR;
            return false;
        }
        
        if( $this->validate( $this->params, $this->rules ) ) {
            $this->params['status'] = static::STATUS_OK;
            return true;
        } else {
            $this->params['status'] = static::STATUS_ERROR;
            return false;
        }
    }

    /**
     * Download / move photos and bind it to enunciation model
     * @param  Enunciations $enun   Enunciation model
     * @param  array        $photos Photos container
     * @return self
     */
    private function bindPhotos( Enunciations $enun, array $photos )
    {
        foreach( $enun->images as $image ) {
            $image->delete();
        }

        $photos = array_slice($photos, 0, self::MAX_PHOTOS);

        foreach( $photos as $photo ) {
            $upload = true;
            // $upload = $this->getModel('photoHandler')->path( $photo )->save();

            if( $upload ) {
                $image = $this->getModel('image');   

                $image->url = $photo;
                $image->attachEnun( $enun );
                $image->save();
            }
        }

        return $this;
    }

    /**
     * Bind all accessories to enunciation
     * @param  Enunciations $enun        Enunciation model
     * @param  array        $accessories Accessories
     * @return self
     */
    public function bindAccessories( Enunciations $enun, array $accessories )
    {
        $container = [];

        foreach( $accessories as $name => $accessory ) {
            $a = $this->getModel('accessories')->where( 'route', '=', $name )->first();
            if( $a !== null ) {
                if( $accessory === true and $a->main == 0 ) {
                    $container[] = $a->id;
                } else if( $accessory !== false ) {
                    $container[ $a->id ] = [ 'text_value' => $accessory ];
                }
            }            
        }
        $enun->accessories()->sync( $container );

        return $this;
    }

    public function bindCounter( Enunciations $enun )
    {
        $counter = $this->getModel('counter')->addNewCounter($enun->id);
    }

    public function noticeTokenWithoutUser( $token, $provider, $info )
    {
        return $this->getModel('tokens')->add( $token, $provider, $info );
    }

    /**
     * Get user province and bind it to enun params
     * @param  Users  $user User model
     * @return self
     */
    private function bindProvince( Users $user )
    {
    	$this->params['province'] = $user->province;
    	return $this;
    }

    /**
     * Find and check if user is valid
     * @return self
     */
    private function handleUser()
    {
        $this->findUserByToken( $this->credentials['token'], $this->credentials['provider_code'] );

        if( !$this->checkUser( $this->user ) ) {
            $exception = sprintf("User ( #%d ) %s %s is not a company.", [ $this->user->id, $this->name, $this->surname ]);
            throw new Exception( $exception );
        }

        $this->params['user_id'] = $this->user->id;

        return $this;
    }

    /**
     * Cut and set exact parameters into self attribute
     * @param  array  $names Names of params
     * @return self 
     */
    private function cutParams( array $names )
    {
        foreach( $names as $name ) {
            $this->cutParam( $name );
        }
        return $this;
    }

    /**
     * Cut and set exact parameter into self attribute
     * @param  string  $names Name of param
     * @return self 
     */
    private function cutParam( $name )
    {
        if( !isset( $this->params[ $name ] ) ) {
            return false;
        }

        $this->{$name} = $this->params[ $name ];
        unset( $this->params[ $name ] );
        return $this;
    }

    /**
     * Check if user is company
     * @param  Users  $user User model
     * @return boolean
     */
    private function checkUser( Users $user )
    {
        return $user->isCompany();
    }

    public function userExists( $token, $providerCode )
    {
    	try {
    		$this->findUserByToken( $token, $providerCode );
    	} catch (Exception $e) {
    		return false;
    	}
    	return true;
    }

    /**
     * Find user by token and provider code
     * @param  string $token        Auth token
     * @param  string $providerCode Provider code
     * @return self
     */
    private function findUserByToken( $token, $providerCode )
    {
        $model = $this->getModel('usertoken')->where( 'token', '=' , $token )
            ->where( 'provider_code', '=', $providerCode )
            ->firstOrFail();

        if( $model == null ) {
            throw new Exception( 'Not found token ' . $token . ' or provider ' . $providerCode );    
        }

        if( $model->user == null ) {
            throw new Exception( 'Not found user for token ' . $token );    
        }

        $this->user = $model->user;
        return $this;
    }

    /**
     * Validate data / array
     * @param  array  $data  Data to validate
     * @param  array  $rules Rules
     * @return boolean       Validation result
     */
    private function validate( array $data, array $rules )
    {
        $validator = Validator::make( $data, $rules );
        return $validator->passes();
    }

    /**
     * Create new object from list
     * @param  string $name object "key" name
     * @return mixed        Created object
     */
    private function getModel( $name )
    {
        if( !isset( $this->models[$name] ) ) {
            throw new Exception('Model '.$name.' not found!');
        }
        return ( new $this->models[$name] ); 
    }

}