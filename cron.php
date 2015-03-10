<?php


/* get user_id by user_name

$try_get_user_id = $this->try_get_url_content("http://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key=" . $config['steam_web_api_key'] . "&vanityurl=" . $id_name, 3);
$try_get_user_id = json_decode($try_get_user_id);

if(isset($try_get_user_id->response->success) && $try_get_user_id->response->success == 1)
    $steam_id = $try_get_user_id->response->steamid;
else
    $steam_id = false;


/* get profile by steam_id

$try_get_user_info = $this->try_get_url_content("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=" . $config['steam_web_api_key'] . "&steamids=" . $steam_id . "&format=json", 3);

return $try_get_user_info;

*/

require('/config.php');
require('/core.php');

class Cron extends Core
{

    public function start()
    {

        $this->do_mysql_connect();

        $this->collection_data_users();

    }

    private function collection_data_users()
    {



    }

    private function try_get_url_content($url, $try_count)
    {

        $try_get_content = @file_get_contents($url);

        if($try_get_content == false)
        {

            --$try_count;

            if($try_count < 0)
                return false;
            else
                return $this->try_get_url_content($url, $try_count);

        }
        else
            return $try_get_content;

    }

}

$ip = $_SERVER['REMOTE_ADDR'];
$config = Config::get_config();

if(in_array($ip, $config['cron_access_ip']))
{

    $cron = new Cron;
    $cron->start();

}