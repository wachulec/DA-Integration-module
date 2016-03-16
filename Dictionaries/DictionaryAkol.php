<?php

/*
 * DictionaryAkol.php
 * Marcin Wachulec <wachulec.marcin@gmail.com>
 * 2016-03-10
 */

namespace App\Integration\Dictionaries;

use SplFileInfo;
use Exception;
use App\Integration\Interfaces\DictionaryInterface;
use App\Integration\Interfaces\StorageInterface;

class DictionaryAkol extends DictionaryAbstract
{
	// vars
	// 

	// const zone
	// 
    
    const UPDATE_INCR = 'przyrostowy';

    const UPDATE_FULL = 'pelny';

    // methods
    // 
    
    // methods / public zone
    // 

    public function __construct()
    {
        parent::__construct();
    	$this->setProviderName( "AKoL" )
    		 ->setProviderCode( 'ak' )
    		 ->setLocation( '/akol' )
    		 ->setFilesNameExpression( 'Akol_[a-zA-Z0-9]{32}_(19[0-9]{2}|2[0-9]{3})(0[1-9]|1[012])([123]0|[012][1-9]|31)([01][0-9]|2[0-3])([0-5][0-9])([0-5][0-9]).zip' )
    		 ->setDisabledFileNames( ['ERROR_*'] );
    }

    public function buildTranslation( $parsedXml )
    {
        parent::buildTranslation( $parsedXml );

        switch( $this->getExportType() ) {
            case self::UPDATE_FULL:
                $this->pushTranslation( 'callback', $this->deleteMissingEnunsCallback() );
                break;
            case self::UPDATE_INCR:
                $this->pushTranslation( 'delete', $this->getDeletedEnuns() );
                break;
            default:
                $exception = sprintf("Akol: lack of 'rodzaj_eksportu'. Translation is not completed! [user name | token: %s | %s]",
                 $this->getCredentials()['name'], $this->getUser()['token']);
                throw new Exception($exception);
                break;
        }

        return $this;
    }

    public function getNamesForToken( $token, $sufix = null )
    {
        $fileNameFormat = '';
        $fakeArr = [];
         if( preg_match('/[a-zA-Z0-9]{32}/', $token, $fakeArr) === 1 ) {
            $fileNameFormat = 'Akol_'.$token.'_'.'*'.'.zip';
            if( isset($sufix) && $sufix !== '' ) {
                $fileNameFormat .= '.'. ltrim($sufix,'.');
            }
         } else {
            $fileNameFormat = false;
         }

         return $fileNameFormat;
    }

    // methods / protected zone
    // 

    protected function buildUser()
    {
    	$user                   = $this->selectParsedData(['komis']);
        $user['provider_code']  = $this->getProviderCode();
        $user['token']          = $this->selectUserToken();
    	return $user;
    }

    protected function buildCredentials()
    {
    	return [ 
    		'provider_code' => $this->getProviderCode(),
    		'token' 		=> $this->selectUserToken(),
    		'name'			=> $this->selectParsedData(['komis', 'firma'])." (".$this->selectParsedData(['komis', 'email']).", ".$this->selectParsedData(['komis', 'telefon']).")"
    	];
    }

    protected function getFullCarName( $enun )
    {
        $model = '';
        if( isset( $enun['wersja_modelu'] ) ) {
            $model = $enun['wersja_modelu'];
        }
        
        return trim($enun['marka'])." ".trim($enun['model'])." ".trim($model);
    }

    protected function selectEnuns()
    {
        $enuns = [];
        foreach( $this->getRules()['categories'] as $category ) {
            $enuns = array_merge( $enuns, $this->selectEnunsFromCategory( $category ) );
        }

        return $enuns;
    }

    protected function getCustomizeTitle( $enun )
    {
        $header = '';
        if( isset($enun['tytul']) && $enun['tytul']!='' ) {
            $header = $enun['tytul'];
        } else {
            $header = $this->getFullCarName( $enun );
        }
        
        return trim($header);
    }

    protected function getAdditionalAccessories( $enun )
    {
        $arr = [];

        $additionalAccessories = $this->selectParsedData( ['wyposazenie', 'pozycja'], $enun );
        $this->singleEnunCorrection( $additionalAccessories );

        $model = new $this->intAccessoriesModel();
        foreach( $additionalAccessories as $item ) {
            if( !empty( $item ) && is_string( $item ) ) {
                $key = $model->match( str_slug($item) );
                if( $key !== false ) {
                    $arr[ $key ] = true;
                }
            }
        }

        return $arr;
    }

    protected function getCarCategory( $enun )
    {
        $category = '';
        $index = $enun['ogloszenie_id'][0];
        if( array_key_exists($index, $this->getRules()['categories']) ) {
            $category = $this->getRules()['categories'][$index];
        }

        return $category;
    }

    protected function init()
    {
        parent::init()
                ->initSecondaryRules()
                ->initUserRules()
                ->initTechnicalRules()
                ->initOtherRules();

        return $this;
    }

    // methods / private zone
    // 
    
    private function getDeletedEnuns()
    {
        $arr = [];
        foreach( $this->getRules()['categories'] as $category ) {
            $categoryContent = $this->selectParsedData( ['usun', $category] );
            if( is_array($categoryContent) ){
                $arr[] = $categoryContent;
            }
        }

        return array_flatten( $arr );
    }

    private function getExportType()
    {
        return $this->selectParsedData(['rodzaj_eksportu']);
    }

    private function deleteMissingEnunsCallback()
    {
        $providerIdArray = array_column( $this->getEnuns(), 'provider_id' );

        $callback = function ( 
            \App\Models\Enunciations $enun, 
            \App\Models\Users $user ) 
            use ( $providerIdArray ) {
                $enun->whereNotIn( 'provider_id', $providerIdArray )->update( ['deleted_at' => date('Y-m-d H:i:s') ] );
        };

        return $callback;
    }

    private function selectEnunsFromCategory( $category )
    {
		$enuns = $this->selectParsedData(['ogloszenia', $category, 'ogloszenie']);
 		$this->singleEnunCorrection( $enuns, 'ogloszenie_id' );

		return $enuns;
    }

    private function selectUserToken()
    {
        return $this->selectParsedData(['komis', 'komis_klucz']);
    }

    private function initSecondaryRules()
    {
        $this->pushRules('secondary', [
            'provider_id'   => 'ogloszenie_id',
            'price'         => 'cena',
            'year'          => 'rok_produkcji',
            'distance'      => 'przebieg_w_km',
            'content'       => 'opis'
        ]);

        return $this;
    }

    private function initUserRules()
    {
        $this->pushRules('user', [
            'token'     =>  'komis_klucz',
            'email'     =>  'email',
            'city'      =>  'miasto',
            'street'    =>  'ulica',
            'phone'     =>  'telefon',
            'post_code' =>  'kod_pocztowy'
        ]);

        return $this;
    }

    private function initTechnicalRules()
    {
        $this->pushRules('technical', [
            'typ-nadwozia-osobowe'          => 'nadwozie',
            'kolor'                         => 'kolor',
            'stan-techniczny'               => ['uszkodzony' => [
                                                                'Nieuszkodzony' => 'Nie', 
                                                                'Uszkodzony' => 'Tak'  
                                                                ]
                                                ],
            'skrzynia-biegow'               => 'skrzynia_biegow',
            'zarejestrowany-w-polsce'       => ['kraj_rejestracji' => [
                                                                        'Tak'       => 'PL',
                                                                        'Nie'       => '\b[A-Z]{1,2}(?<!PL)\b'
                                                                    ]
                                                ],
            'liczba-drzwi'                  => 'liczba_drzwi',
            'pojemnosc-skokowa'             => 'pojemnosc_w_cm3',
            'rodzaj-paliwa'                 => 'paliwo'
        ]);

        return $this;
    }

    private function initOtherRules()
    {
        $this->pushRules('other', [
            'status',
            'promote',
            'user_id',
            'deleted_at',
            'country_id',
            'counter_id',
            'date_change_stat',
            'top',
            'token'            
        ]);

        return $this;
    }
}