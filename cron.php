<?php

require('/config.php');
require('/core.php');

function get_link_segments($link)
{

    $link = str_replace("http://", "", $link);
    $link = str_replace("https://", "", $link);
    $link_array = explode('/', $link);

    return $link_array;

}

class Cron extends Core
{

    public function start()
    {

        $this->do_mysql_connect();

        $this->collection_data_users();

    }

    private function collection_data_users()
    {

        $UrlContent = new UrlContent();
        $config = $this->get_config();

        $try_cache = $this->mysql_connect->query("SELECT `link`, `profile_id`, `profile_name`, `real_name`, `avatar`, `lang`, `filled` FROM (`users_cache`) WHERE `filled` = '0'");

        $form_array = array();
        while ($row = $try_cache->fetch_assoc()){
            $form_array[] = $row;
        }


        foreach($form_array as $k => $item)
        {

            $segments_array = get_link_segments($item['link']);

            /* detect user steam id */
            if($segments_array[1] == 'id')
            {

                $try_get_user_id = $UrlContent->try_get_url_content("http://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key=" . $config['steam_web_api_key'] . "&vanityurl=" . $segments_array[2], 3);
                $try_get_user_id = json_decode($try_get_user_id);

                if(isset($try_get_user_id->response->success) && $try_get_user_id->response->success == 1)
                    $user_id = $try_get_user_id->response->steamid;
                else
                    $user_id = false;

            }
            if($segments_array[1] == 'profiles')
                $user_id = $segments_array[2];

            /* get steam profile by id */
            if(isset($user_id) && $user_id !== false)
            {

                $try_get_user_info = $UrlContent->try_get_url_content("http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=" . $config['steam_web_api_key'] . "&steamids=" . $user_id . "&format=json", 3);

                if($try_get_user_info !== false)
                {

                    $info = json_decode($try_get_user_info);

                    if(isset($info->response->players[0]->steamid))
                        $form_array[$k]['profile_id'] = $info->response->players[0]->steamid;

                    if(isset($info->response->players[0]->realname))
                        $form_array[$k]['real_name'] = $info->response->players[0]->realname;

                    if(isset($info->response->players[0]->personaname))
                        $form_array[$k]['profile_name'] = $info->response->players[0]->personaname;

                    if(isset($info->response->players[0]->avatar))
                        $form_array[$k]['avatar'] = $info->response->players[0]->avatar;

                    if(isset($info->response->players[0]->loccountrycode))
                        $form_array[$k]['lang'] = $info->response->players[0]->loccountrycode;

                    $form_array[$k]['filled'] = 1;

                    $gen_query = '';
                    foreach($form_array[$k] as $name => $value)
                    {

                        if(end($form_array[$k]) == $value)
                            $after = '';
                        else
                            $after = ', ';

                        if($name !== 'link')
                            $gen_query = $gen_query . '`' . $name . "` = '" . mysql_real_escape_string($value) . "'" . $after;

                    }

                    $this->mysql_connect->query("UPDATE `users_cache` SET " . $gen_query . " WHERE `link` = '" . $item['link'] . "'");

                }

            }

        }

    }

}

$ip = $_SERVER['REMOTE_ADDR'];
$config = Config::get_config();

if(in_array($ip, $config['cron_access_ip']))
{

    $cron = new Cron;
    $cron->start();

}