<?php

/*
 * DictionaryAutoPanel.php
 * Marcin Wachulec <wachulec.marcin@gmail.com>
 * 2016-03-10
 */

namespace App\Integration\Dictionaries;

use App\Integration\Interfaces\DictionaryInterface;
use App\Integration\Integration\StorageInterface;

class DictionaryAutoPanel extends DictionaryAbstract
{

    // methods
    // 
    
    // methods / public zone
    // 

    public function __construct()
    {
        parent::__construct();
        $this->setProviderName( "AutoPanel" )
             ->setProviderCode( 'ap' )
             ->setLocation( '/autopanel' )
             ->setFilesNameExpression( 'eksport-[a-zA-Z0-9]{32}-(19[0-9]{2}|2[0-9]{3})(0[1-9]|1[012])([123]0|[012][1-9]|31)-([01][0-9]|2[0-3])([0-5][0-9])([0-5][0-9]).zip' )
             ->setDisabledFileNames([]);
    }

    public function buildTranslation( $parsedXml )
    {
        parent::buildTranslation( $parsedXml );
        $this->popTranslationByIndex( 'user' );

        return $this;
    }

    public function getNamesForToken( $token, $sufix = null )
    {
        $fileNameFormat = '';
        $fakeArr = [];
         if( preg_match('/[a-zA-Z0-9]{32}/', $token, $fakeArr) === 1 ) {
            $fileNameFormat = 'eksport-'.$token.'-'.'*'.'.zip';
            if( isset($sufix) ) {
                $fileNameFormat .= '.'.ltrim($sufix,'.');
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
        return null;
    }

    protected function buildCredentials()
    {
        return [ 
            'provider_code' => $this->getProviderCode(),
            'token'         => $this->selectUserToken(),
            'name'          => ''
        ];
    }

    protected function getFullCarName( $enun )
    {
        $marka = '';
        $model = '';
        $wersja = '';

        if( isset( $enun['marka'] ) && !is_array( $enun['marka'] ) ) {
            $marka = $enun['marka'];
        }
        if( isset( $enun['model'] ) && !is_array( $enun['model'] ) ) {
            $model = $enun['model'];
        }
        if( isset( $enun['typ_slownie'] ) && !is_array( $enun['typ_slownie'] ) ) {
            $wersja = $enun['typ_slownie'];
        }
                // var_dump( trim($marka)." ".trim($model)." ".trim($wersja) );
        return trim($marka)." ".trim($model)." ".trim($wersja);
    }

    protected function selectEnuns()
    {
        $enuns = $this->selectParsedData(['import', 'ogloszenie']);
        $this->singleEnunCorrection( $enuns, 'ofertaid' );

        return $enuns;
    }

    protected function getAdditionalAccessories( $enun ) 
    {
        $arr = [];

        $additionalAccessories = explode(
            ';',
            trim( 
                $this->selectParsedData( ['wyposazenie'], $enun ), 
                " ;\t\n\r\0\x0B"
            )
        );

        $model = new $this->intAccessoriesModel();
        foreach( $additionalAccessories as $item ) {
            if( !empty( $item ) && is_string( $item ) ) {
                $key = $model->match( str_slug( $item ) );
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
        $index = $enun['rodzaj'][0];
        if( array_key_exists($index, $this->getRules()['categories']) ) {
            $category = $this->getRules()['categories'][$index];
        }

        return $category;
    }

    protected function getCustomizeTitle( $enun )
    {
        $title = trim( $this->getFullCarName( $enun ) );
        $userModelDescription = $this->selectParsedData(['wersja_modelu'], $enun);
        if( is_string($userModelDescription) ) {
            $title .= " ".trim( $userModelDescription );
        }

        return trim( $title );
    }

    protected function init()
    {   
        parent::init()
            ->initSecondaryRules()
            ->initTechnicalRules()
            ->initOtherRules();

        return $this;
    }

    // methods / private zone
    // 

    private function selectUserToken()
    {
        return $this->selectParsedData( ['id_komisu'] );
    }

    private function initSecondaryRules()
    {
        $this->pushRules('secondary', [
            'provider_id'   => 'ofertaid',
            'price'         => 'cena_brutto',
            'year'          => 'rok_produkcji',
            'distance'      => 'przebieg',
            'content'       => 'opis'
        ]);

        return $this;
    }

    private function initTechnicalRules()
    {
        $this->pushRules('technical', [
            'nadwozie'                      => 'nadwozie',
            'kolor'                         => 'kolor',
            'stan-techniczny'               => ['uszkodzony' => [
                                                                'Nieuszkodzony' => 'NIE', 
                                                                'Uszkodzony' => 'TAK'  
                                                                 ]
                                                ],
            'skrzynia-biegow'               => ['rodzaj_skrzyni' =>[
                                                                    'Automatyczna' => 1,
                                                                    'Manualna' => 2,
                                                                    'Półautomatyczna/sekwencyjna' => 3
                                                                    ]
                                                ],
            'zarejestrowany-w-polsce'       => ['kraj_rejestracji' => [
                                                                        'Tak'       => 'PL',
                                                                        'Nie'       => '\b[A-Z]{1,2}(?<!PL)\b'
                                                                    ]
                                                ],
            'liczba-drzwi'                  => 'ilosc_drzwi',
            'pojemnosc-skokowa'             => 'pojemnosc',
            'rodzaj-paliwa'                 => 'typsilnika'
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
            'header',
            'provinces'         
        ]);

        return $this;
    }
}