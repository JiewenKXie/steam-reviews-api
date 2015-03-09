<?php

/* apache change request time */
ini_set('max_execution_time', 300);

/* hide all php errors */
//error_reporting(0);

class Config
{

    public function get_config(){

        return array(

            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_pass' => '',
            'db_database' => 'project',

            'count_request_limit' => 30,
            'request_limit_time_in_minutes' => 1,

            'languages' => array('russian', 'english', 'polish'),

            'fake_user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.115 Safari/537.36',

            'language_header_components' => array(
                'russian' => array('header' => 'ru', 'cookie' => 'russian'),
                'english' => array('header' => 'en', 'cookie' => 'english'),
                'polish' => array('header' => 'pl', 'cookie' => 'polish'),
            ),

            'reviews_cache_time'  => 180,
            'search_cache_time'  => 60,

            'app_image_types' => array(
                '120_45' => 'capsule_sm_120.jpg',
                '184_69' => 'capsule_184x69.jpg',
                '460_215' => 'header.jpg'
            ),

            'steam_web_api_key' => 'FD6C42779FD77C760DAC94628A60EB6B'

        );

    }

}