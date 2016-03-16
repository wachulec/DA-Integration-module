<?php

/*
 * DictionaryAutoMarket.php
 * Marcin Wachulec <wachulec.marcin@gmail.com>
 * 2016-03-10
 */

namespace App\Integration\Dictionaries;

use App\Integration\Interfaces\DictionaryInterface;
use App\Integration\Interfaces\StorageInterface;

class DictionaryAutoMarket extends DictionaryAbstract
{
    // methods
    // 

    // methods / public
    // 

    public function __construct()
    {
        parent::__construct();
        $this->setProviderName( "Auto-Market" )
             ->setProviderCode( 'am' )
             ->setLocation( '/automarket' )
             ->setFilesNameExpression( '[a-zA-Z0-9]{32}.xml' )
             ->setDisabledFileNames([]);
    }

    public function getNamesForToken( $token, $sufix = '' )
    {
        $fileNameFormat = '';
        $fakeArr = [];
         if( preg_match('/[a-zA-Z0-9]{32}/', $token, $fakeArr) === 1 ) {
            $fileNameFormat = '*'.'.xml';
            if( isset($sufix) ) {
                $fileNameFormat .= $sufix;
            }
         } else {
            $fileNameFormat = false;
         }

         return $fileNameFormat;
    }

    public function buildTranslation( $parsedXml )
    {
        parent::buildTranslation( $parsedXml );
        $this->popTranslationByIndex('user');

        return $this;
    }

    // methods / protected
    // 

    protected function buildCredentials()
    {
        return [ 
            'provider_code' => $this->getProviderCode(),
            'token'         => $this->selectUserToken(),
            'name'          => ''
        ];
    }

    protected function buildUser()
    {
        return null;
    }

    protected function getFullCarName( $enun )
    {
        $model = '';
        if( isset( $enun['wersja_modelu']['wartosc'] ) ) {
            $model = $enun['wersja_modelu']['wartosc'];
        } 

        return trim($enun['marka']['wartosc'])." ".trim($enun['model']['wartosc'])." ".trim($model);
    }

    protected function selectEnuns()
    {
        $enuns = $this->selectParsedData(['oferty', 'ogloszenie']);
        $this->singleEnunCorrection( $enuns, 'identyfikator' );

        return $enuns;
    }    

    protected function getAdditionalAccessories( $enun ) 
    {
        $arr = [];

        $accessories = $this->selectParsedData( ['wyposazenie'], $enun );
        $this->singleEnunCorrection( $accessories );

        $model = new $this->intAccessoriesModel();
        foreach( $accessories as $key => $item ) {
            if( $item == 1 ) {
                $index = $model->match( str_slug($key) );
                if( !empty($index) && is_string($index) ) {
                    $arr[ $index ] = true;
                }
            }
        }

        return $arr;
    }
    
    protected function getCarCategory( $enun )
    {
        return strtolower( $enun['rodzaj']['wartosc'] );
    }

    protected function getPhotos( $enun )
    {
        $result = $this->selectParsedData( ['foto', 'zdjecie'], $enun );
        $this->singleEnunCorrection( $result );

        foreach( $result as &$r ) {
            $r = realpath( storage_path( 'integration' . DIRECTORY_SEPARATOR . trim($this->getLocation(), '/\\') . DIRECTORY_SEPARATOR . $r ) );
        }

        return $result;
    }

    // methods / private
    // 

    private function selectUserToken()
    {
        return $this->selectParsedData( ['firma', 'klucz'] );
    }

    protected function init()
    {
        parent::init()
            ->initSecondaryRules()
            ->initTechnicalRules()
            ->initOtherRules();

        return $this;
    }

    private function initSecondaryRules()
    {
        $this->pushRules('secondary', [
            'provider_id'   => ['identyfikator' => 'wartosc'],
            'price'         => ['cena' => 'wartosc'],
            'year'          => 'rok_produkcji',
            'distance'      => 'przebieg',
            'content'       => 'opis'
        ]);

        return $this;
    }

    private function initTechnicalRules()
    {
        $this->pushRules('technical', [
            'nadwozie'                      => ['kategoria' => 'wartosc'],
            'kolor'                         => ['kolor' => 'wartosc'],
            'stan-techniczny'               => ['stan_techniczny' => 'wartosc'],
            'skrzynia-biegow'               => ['skrzynia_biegow' => 'wartosc'],
            'zarejestrowany-w-polsce'       => ['kraj_rejestracji' => [
                                                                        'wartosc' => [
                                                                            'Tak'       => 'Polska',
                                                                            'Nie'       => '\b[A-Z]{1}[a-z]+(?<!Polska)\b'
                                                                        ]
                                                                    ]
                                                ],
            'liczba-drzwi'                  => 'liczba_drzwi',
            'pojemnosc'                     => 'pojemnosc',
            'rodzaj-paliwa'                 => ['paliwo' => 'wartosc']
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
            'token',
            'provinces'         
        ]);

        return $this;
    }
} 