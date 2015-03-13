<?php

/* apache change request time */
ini_set('max_execution_time', 300);

/* hide all php errors */
//error_reporting(0);

class Config
{

    public function get_config(){

        return array(

            /* MySQL connect */
            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_pass' => '',
            'db_database' => 'project',

            /* Restrictions requests */
            'count_request_limit' => 30,
            'request_limit_time_in_minutes' => '1 minutes',

            /* Attempts to retrieve contents */
            'retrying_get_content' => 3,

            /* Parser user agent */
            'fake_user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.115 Safari/537.36',

            /* Available API languages */
            'languages' => array('russian', 'english', 'polish'),

            'language_header_components' => array(
                'russian' => array('header' => 'ru', 'cookie' => 'russian'),
                'english' => array('header' => 'en', 'cookie' => 'english'),
                'polish' => array('header' => 'pl', 'cookie' => 'polish'),
            ),

            /* Timings */
            'reviews_cache_time'  => '180 minutes',
            'search_cache_time'  => '60 minutes',

            /* Available avatars size */
            'app_image_types' => array(
                '120_45' => 'capsule_sm_120.jpg',
                '184_69' => 'capsule_184x69.jpg',
                '460_215' => 'header.jpg'
            ),

            /* Steam API config */
            'steam_web_api_key' => 'FD6C42779FD77C760DAC94628A60EB6B',

            /* Cron config */
            'cron_access_ip' => array('127.0.0.1'),
            'cron_update_users_cache' => '2 days'

        );

    }

}