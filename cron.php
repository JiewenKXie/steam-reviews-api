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

        $this->check_to_relevant_cache();
        $this->collection_users_in_cache();
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

                    $form_array[$k]['datetime'] = date('Y-m-d H:i:s');

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

    private function collection_users_in_cache()
    {

        /* Get current users cache */
        $try_cache = $this->mysql_connect->query("SELECT `link` FROM (`users_cache`)");

        $form_array = array();
        while ($row = $try_cache->fetch_assoc()){
            $form_array[] = $row;
        }

        $user_cache = array();
        foreach($form_array as $link)
            $user_cache[] = $link['link'];

        /* Users from reviews cache */
        $try_reviews_cache = $this->mysql_connect->query("SELECT `items` FROM (`reviews_cache`)");

        $form_reviews_array = array();
        while ($row = $try_reviews_cache->fetch_assoc()){
            $form_reviews_array[] = $row;
        }

        /* Decode */
        $form_decode_cache_list = array();
        foreach($form_reviews_array as $item)
            $form_decode_cache_list[] = unserialize(base64_decode($item['items']));

        /* Connect items */
        $connected_items = array();
        foreach($form_decode_cache_list as $item)
            $connected_items = array_merge($connected_items, $item);

        /* Remove duplicate */
        $cache_duplicate = array();
        foreach($connected_items as $id => $item)
        {

            if(in_array($item['text'], $cache_duplicate))
                unset($connected_items[$id]);
            else
                $cache_duplicate[] = $item['text'];

        }

        $already_added = array();
        $formation_insert_query = array();

        $current_datetime = date('Y-m-d H:i:s');

        foreach($connected_items as $item)
        {

            if(!in_array($item['author_profile'], $user_cache) && !in_array($item['author_profile'], $already_added))
            {

                $already_added[] = $item['author_profile'];
                $formation_insert_query[] = "('" . mysql_real_escape_string($item['author_profile']) . "', '" . mysql_real_escape_string($item['author_name']) . "', '" . mysql_real_escape_string($item['author_avatar']) . "', 1, '" . $current_datetime . "')";

            }

        }

        /* Set new users */
        if(!empty($formation_insert_query))
        {
            $formation_query_values = implode(',', $formation_insert_query);
            $this->mysql_connect->query("INSERT INTO `users_cache` (`link`, `profile_name`, `avatar`, `filled`, `datetime`) VALUES " . $formation_query_values);
        }

    }

    private function check_to_relevant_cache()
    {

        /* Get config */
        $config = $this->get_config();

        /* Get dates steam users from base */
        $try_cache = $this->mysql_connect->query("SELECT `link`, `datetime` FROM (`users_cache`)");

        $form_array = array();
        while ($row = $try_cache->fetch_assoc()){
            $form_array[] = $row;
        }

        /* Formation old users array */
        $update_list = array();
        foreach($form_array as $id => $item)
        {

            if(empty($item['datetime']))
                $update_list[] = $item['link'];

            $compare_datetime = recalculate_date($item['datetime'], '+' . $config['cron_update_users_cache']);
            $current_datetime = date('Y-m-d H:i:s');

            if($compare_datetime < $current_datetime)
                $update_list[] = $item['link'];

        }

        /* Update not relevant users */
        $pre_mysql_form = array();
        foreach($update_list as $item)
            $pre_mysql_form[] = "`link` = '" . $item . "'";

        $where_part = implode(' OR ', $pre_mysql_form);

        $this->mysql_connect->query("UPDATE `users_cache` SET `datetime` = '" . date('Y-m-d H:i:s') . "', `filled` = '0' WHERE " . $where_part);

    }

}

$ip = $_SERVER['REMOTE_ADDR'];
$config = Config::get_config();

if(in_array($ip, $config['cron_access_ip']))
{

    $cron = new Cron;
    $cron->start();

}
