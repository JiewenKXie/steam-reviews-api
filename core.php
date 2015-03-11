<?php

/* HELPERS */
function overdie($array)
{

    echo '<pre>';
    print_r($array);
    echo '</pre>';

    die();

}

function recalculate_date($date, $rec)
{

    return date('Y-m-d H:i:s', strtotime($rec, strtotime($date)));

}

function get_string_between($string, $start, $end)
{

    $string = " ".$string;
    $ini = strpos($string,$start);
    if ($ini == 0) return "";
    $ini += strlen($start);
    $len = strpos($string,$end,$ini) - $ini;
    return substr($string,$ini,$len);

}

function str_replace_group($target, $replace_to, $string)
{

    $string = preg_replace('/(' . $target . ')\1+/', '$1', $string);
    $string = str_replace($target, $replace_to , $string);
    return $string;

}


/* CORE */
class Core extends Config
{

    /* MYSQL */
    public $mysql_connect = false;

    public function do_mysql_connect()
    {

        $config = $this->get_config();

        $this->mysql_connect = @mysqli_connect($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_database']);

        if (!$this->mysql_connect)
            die('db connect error');
        else
            return true;

    }

    public function close_mysql_connect()
    {

        mysqli_close($this->mysql_connect);

    }

    public function check_request_limit()
    {

        $config = $this->get_config();

        $ip = $_SERVER['REMOTE_ADDR'];

        $dirt_result = $this->mysql_connect->query("SELECT * FROM request_limit WHERE ip='" . $ip . "' ORDER BY id DESC");
        $result = $dirt_result->fetch_assoc();

        //if not found
        if($result == false)
        {

            $this->mysql_connect->query("INSERT INTO `request_limit` (`ip`, `count`, `datetime`) VALUES ('" . $ip . "', 1, '" . date('Y-m-d H:i:s') . "')");

        }

        //if it exists, but expired
        elseif($result['datetime'] < recalculate_date(date('Y-m-d H:i:s'), '-' . $config['request_limit_time_in_minutes'] . ' minutes'))
        {

            $this->mysql_connect->query("DELETE FROM `request_limit` WHERE `ip` = '" . $ip . "'");
            $this->mysql_connect->query("INSERT INTO `request_limit` (`ip`, `count`, `datetime`) VALUES ('" . $ip . "', 1, '" . date('Y-m-d H:i:s') . "')");

        }

        //if found
        else
        {

            $count = (int)$result['count'];
            $count++;

            if($count >= $config['count_request_limit'])
                die('Exceeded the query limit. The limit is reset at ' . recalculate_date($result['datetime'], '+' . $config['request_limit_time_in_minutes'] . ' minutes'));

            $this->mysql_connect->query("UPDATE `request_limit` SET `count` = " . $count . " WHERE `id` = " . $result['id']);

        }

    }

    public function get_query_validate()
    {

        $config = $this->get_config();

        if(!isset($_GET['app']) || !is_numeric($_GET['app']) && $_GET['app'] < 0)
            die('Wrong app id');

        if(!isset($_GET['language']) || !in_array($_GET['language'], $config['languages']))
            die('Wrong language');

        if(!isset($_GET['offset']) || !is_numeric($_GET['offset']) || $_GET['offset'] < 0 || ($_GET['offset'] / 20) != (int)($_GET['offset'] / 20))
            die('Wrong offset');

        $validate_filter = array('all', 'positive', 'negative', 'recent', 'funny');
        if(!isset($_GET['filter']) || !in_array($_GET['filter'], $validate_filter))
            die('Wrong filter');

        $validate_day_range = array(1, 7, 30, 180, 1800);
        if(!isset($_GET['day_range']) || !in_array($_GET['day_range'], $validate_day_range))
            die('Wrong day range');

    }

    public function get_reviews()
    {

        $config = $this->get_config();

        $dirt_result = $this->mysql_connect->query("SELECT `id`, `datetime`, `items` FROM (`reviews_cache`) WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "' ORDER BY id DESC");
        $result = $dirt_result->fetch_assoc();

        //if not found
        if($result == false)
        {

            $items = $this->get_reviews_from_steam();

            if(!empty($items))
            {
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                $this->mysql_connect->query("INSERT INTO `reviews_cache` (`app`, `offset`, `filter`, `language`, `datetime`, `items`, `day_range`) VALUES ('" . $_GET['app'] . "', '" . $_GET['offset'] . "', '" . $_GET['filter'] . "', '" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $safe_serialize_items . "', '" . $_GET['day_range'] . "')");
            }

            return $items;

        }

        //if it exists, but expired
        elseif(date('Y-m-d H:i:s') > recalculate_date($result['datetime'], '+' . $config['reviews_cache_time'] . ' minutes'))
        {

            $items = $this->get_reviews_from_steam();

            if(!empty($items))
            {
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                $this->mysql_connect->query("DELETE FROM `reviews_cache` WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "'");
                $this->mysql_connect->query("INSERT INTO `reviews_cache` (`app`, `offset`, `filter`, `language`, `datetime`, `items`, `day_range`) VALUES ('" . $_GET['app'] . "', '" . $_GET['offset'] . "', '" . $_GET['filter'] . "', '" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $safe_serialize_items . "', '" . $_GET['day_range'] . "')");
            }
            return $items;

        }

        //if cache isset
        else
        {

            $dirt_result = $this->mysql_connect->query("SELECT `items` FROM (`reviews_cache`) WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "' ORDER BY id DESC");
            $result = $dirt_result->fetch_assoc();

            return unserialize(base64_decode($result['items']));
        }

    }

    public function get_reviews_from_steam()
    {

        $UrlContent = new UrlContent();

        $url = 'http://store.steampowered.com/appreviews/' . $_GET['app'] . '?start_offset=' . $_GET['offset'] . '&day_range=' . $_GET['day_range'] . '&filter=' . $_GET['filter'] . '&language=' . $_GET['language'];
        $steam_json = json_decode($UrlContent->try_get_url_content($url));

        if($steam_json->success !== 1)
            die('App not found');

        return $this->detect_reviews_in_html($steam_json->html);

    }

    public function detect_reviews_in_html($html)
    {

        $reviews_explode = explode('<div class="review_box">', $html);
        unset($reviews_explode[0]);

        if(empty($reviews_explode))
            die('No reviews');

        $items = array();

        foreach($reviews_explode as $item)
        {

            $new_item = array();
            $resource_counter = 0;

            $dirty_date = get_string_between($item, '<div class="postedDate">', '</div>');
            $new_item['publish_date'] = str_replace("	", "", $dirty_date);

            //$test = explode(': ', $new_item['publish_date']);
            //overdie(date("d-m-Y", strtotime($test[1])));

            //detect and clear review text
            $dirty_text = get_string_between($item, '<div class="content">', '<div class="gradient"></div>');
            $dirty_text = str_replace("\r\n", "", $dirty_text);

            $dirty_text = str_replace('<div class="bb_h1">', "[h1]", $dirty_text);
            $dirty_text = str_replace('</div>', "[/h1]", $dirty_text);

            $dirty_text = str_replace('<div class="bb_code">', "", $dirty_text);

            $dirty_text = str_replace('<b>', "[b]", $dirty_text);
            $dirty_text = str_replace('</b>', "[/b]", $dirty_text);

            $dirty_text = str_replace('<span class="bb_spoiler"><span>', "[spoiler]", $dirty_text);
            $dirty_text = str_replace('</span></span>', "[/spoiler]", $dirty_text);

            $dirty_text = str_replace('<ul class="bb_ul">', "[ul]", $dirty_text);
            $dirty_text = str_replace('</ul>', "[/ul]", $dirty_text);

            $dirty_text = str_replace('<li>', "[li]", $dirty_text);
            $dirty_text = str_replace('</li>', "[/li]", $dirty_text);

            $dirty_text = str_replace('<u>', "[u]", $dirty_text);
            $dirty_text = str_replace('</u>', "[/u]", $dirty_text);

            $dirty_text = str_replace('<i>', "[i]", $dirty_text);
            $dirty_text = str_replace('</i>', "[/i]", $dirty_text);

            $dirty_text = str_replace('<blockquote class="bb_blockquote">', "[bq]", $dirty_text);
            $dirty_text = str_replace('</blockquote>', "[/bq]", $dirty_text);

            $dirty_text = str_replace('<br>', "[br]", $dirty_text);

            $dirty_text = str_replace('&quot;', '"', $dirty_text);

            /* parse insults */
            $dirty_text = str_replace_group('♥', "[c]", $dirty_text);


            /* parse links */

            //detect count resources in text
            $resource_count = substr_count($dirty_text, '<a class="bb_link" target="_blank" href="');

            if($resource_count > 0)
            {

                for ($iteration = 1; $iteration <= $resource_count; $iteration++) {

                    $resource_type = 'dynamiclink';
                    $detect_link = get_string_between($dirty_text, '<a class="bb_link" target="_blank" href="', '"  id="dynamiclink_' . ($iteration - 1) . '">');

                    if($detect_link == false)
                    {
                        $resource_type = 'otherlink';
                        $detect_link = get_string_between($dirty_text, '<a class="bb_link" target="_blank" href="', '" >');
                    }


                    if($detect_link != false)
                    {

                        $resource_counter++;
                        $new_item['resources']['resource_' . $resource_counter] = $this->detect_resource($detect_link);

                        switch ($resource_type)
                        {
                            case 'dynamiclink':
                                $resource_html = '<a class="bb_link" target="_blank" href="' . $detect_link . '"  id="dynamiclink_' . ($iteration - 1) . '">' . $detect_link . '</a>';
                                break;
                            case 'otherlink':
                                $detect_link_name = get_string_between($dirty_text, '<a class="bb_link" target="_blank" href="' . $detect_link . '" >', '</a>');
                                $resource_html = '<a class="bb_link" target="_blank" href="' . $detect_link . '" >' . $detect_link_name . '</a>';
                                break;
                        }

                        $dirty_text = str_replace($resource_html, '[resource_' . $resource_counter . ']', $dirty_text);

                    }

                }

            }


            $new_item['text'] = str_replace("	", "", $dirty_text);

            //detect and calculate stats
            $stats = get_string_between($item, '<div class="header">', '</div>');
            $detect_stats = explode(' ', $stats);
            $new_item['positive_votes'] = (int)str_replace(",", "", $detect_stats[0]);
            $new_item['all_votes'] = (int)str_replace(",", "", $detect_stats[2]);

            if($new_item['positive_votes'] == 0)
                $new_item['positive_votes'] = 1;

            if($new_item['all_votes'] == 0)
                $new_item['all_votes'] = 1;

            $new_item['rate'] = round($new_item['positive_votes'] / ($new_item['all_votes'] / 100));

            //detect review vote
            if(substr_count($item, 'icon_thumbsUp_v6.png') == 1)
                $new_item['vote'] = 'positive';
            elseif(substr_count($item, 'icon_thumbsDown_v6.png') == 1)
                $new_item['vote'] = 'negative';
            else
                $new_item['vote'] = 'undefined';

            //detect author info
            $dirty_author_name = get_string_between($item, '<div class="persona_name"><a href="', '</a></div>');
            $dirty_author_name_e = explode('">', $dirty_author_name);
            $new_item['author_name'] = $dirty_author_name_e[1];

            $new_item['author_profile'] = get_string_between($item, '<div class="persona_name"><a href="', '" data-miniprofile="');

            $dirty_author_number_of_games = get_string_between($item, '<div class="num_owned_games"><a href="', '</a></div>');
            $dirty_author_number_of_games_e = explode('">', $dirty_author_number_of_games);
            $dirty_author_number_of_games_e2 = explode(' ', $dirty_author_number_of_games_e[1]);
            $new_item['author_number_of_games'] = end($dirty_author_number_of_games_e2);

            $dirty_author_number_of_reviews = get_string_between($item, '<div class="num_reviews"><a href="', '</a></div>');
            $dirty_author_number_of_reviews_e = explode('">', $dirty_author_number_of_reviews);
            $dirty_author_number_of_reviews_e2 = explode(' ', $dirty_author_number_of_reviews_e[1]);
            $new_item['author_number_of_reviews'] = end($dirty_author_number_of_reviews_e2);

            $dirty_author_avatar = get_string_between($item, '<div class="playerAvatar', '</div>');
            $new_item['author_avatar'] = get_string_between($dirty_author_avatar, '<img src="', '" data-miniprofile="');

            $dirty_author_played = get_string_between($item, '<div class="hours">', '</div>');
            $dirty_author_played_e = explode(' ', $dirty_author_played);
            $new_item['author_played'] = $dirty_author_played_e[0];

            $items[] = $new_item;

        }

        return $items;

    }




    public function get_search_query_validate()
    {

        $config = $this->get_config();

        if(!isset($_GET['language']) || !in_array($_GET['language'], $config['languages']))
            die('Wrong language');

        if(!isset($_GET['term']))
            die('Wrong search word');

    }

    public function render($items, $item_name = 'review')
    {

        $Render = new Render();

        if(isset($_GET['result']) && in_array($_GET['result'], array('xml', 'json', 'serialize')))
            $render_type = $_GET['result'];
        else
            $render_type = 'xml';

        switch ($render_type)
        {

            case 'xml':
                $Render->render_xml($items, $item_name);
                break;

            case 'json':
                $Render->render_json($items);
                break;

            case 'serialize':
                $Render->render_serialize($items);
                break;

        }

    }

    public function get_search()
    {

        $config = $this->get_config();

        $dirt_result = $this->mysql_connect->query("SELECT `id`, `datetime`, `items` FROM (`search_cache`) WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "' ORDER BY id DESC");
        $result = $dirt_result->fetch_assoc();

        //if not found
        if($result == false)
        {

            $items = $this->get_search_result_from_steam();

            if(!empty($items))
            {
                $this->cache_search_app_items($items);
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                $this->mysql_connect->query("INSERT INTO `search_cache` (`language`, `datetime`, `search`, `items`) VALUES ('" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $_GET['term'] . "', '" . $safe_serialize_items . "')");
            }
            return $items;

        }

        //if it exists, but expired
        elseif(date('Y-m-d H:i:s') > recalculate_date($result['datetime'], '+' . $config['search_cache_time'] . ' minutes'))
        {

            $items = $this->get_search_result_from_steam();

            if(!empty($items))
            {
                $this->cache_search_app_items($items);
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                $this->mysql_connect->query("DELETE FROM `search_cache` WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "'");
                $this->mysql_connect->query("INSERT INTO `search_cache` (`language`, `datetime`, `search`, `items`) VALUES ('" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $_GET['term'] . "', '" . $safe_serialize_items . "')");
            }
            return $items;

        }

        //if cache isset
        else
        {
            $dirt_result = $this->mysql_connect->query("SELECT `id`, `datetime`, `items` FROM (`search_cache`) WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "' ORDER BY id DESC");
            $result = $dirt_result->fetch_assoc();
            $final_array = unserialize(base64_decode($result['items']));

            return $final_array;

        }

    }

    public function cache_search_app_items($array)
    {

        if(empty($array))
            return false;

        $generate_where_query = '';

        foreach($array as $item)
        {

            if(end($array) == $item)
                $generate_where_query = $generate_where_query . "`app_id` = '" . $item['app_id'] . "'";
            else
                $generate_where_query = $generate_where_query . "`app_id` = '" . $item['app_id'] . "' OR ";

        }

        $dirt_result = $this->mysql_connect->query("SELECT `app_id` FROM (`apps_list`) WHERE (" . $generate_where_query . ")");

        $remove_id_list = array();
        while ($row = $dirt_result->fetch_assoc()) {
            $remove_id_list[] = $row['app_id'];
        }

        foreach($array as $k => $v)
        {

            if(in_array($v['app_id'], $remove_id_list))
                unset($array[$k]);

        }

        $generate_insert_query = array();

        foreach($array as $item)
            $generate_insert_query[] = "('" . $item['app_id'] .  "', '" . mysql_real_escape_string($item['name']) . "')";

        $insert_values_string = implode(', ', $generate_insert_query);

        $this->mysql_connect->query("INSERT INTO `apps_list` (`app_id`, `name`) VALUES " . $insert_values_string . ";");

    }

    public function get_search_result_from_steam()
    {

        $config = $this->get_config();
        $UrlContent = new UrlContent();

        $url = 'http://store.steampowered.com/search/suggest?term=' . urlencode($_GET['term']) . '&f=games&cc=' . mb_strtoupper($config['language_header_components'][$_GET['language']]['header']) . '&l=' . $config['language_header_components'][$_GET['language']]['cookie'];
        $steam_html = $UrlContent->try_get_url_content($url);

        return $this->detect_search_result_in_html($steam_html);

    }

    public function detect_search_result_in_html($html)
    {

        $html_items = explode('<a class="match ds_collapse_flag "', $html);
        unset($html_items[0]);

        $items_id = array();

        foreach($html_items as $item)
        {

            $app_id = get_string_between($item, 'data-ds-appid="', '" href');
            $app_name = get_string_between($item, '<div class="match_name">', '</div>');

            $items_id[] = array('app_id' => $app_id, 'name' => $app_name);

        }

        return $items_id;

    }

    public function detect_resource($link)
    {


        if(substr_count($link, 'youtube') > 0)
        {

            $resource_stats = YouTubeInfo::get_youtube_info($link);

            return $resource_stats;

        }
        else
        {

            $resource_stats = array();
            $link_segments = explode('/', $link);

            /* Detect shared steam resources */
            if($link_segments[3] == 'sharedfiles')
                $resource_stats = SteamSharedFiles::get_sharedfiles($link);

            /* Detect steam profiles link */
            elseif($link_segments[3] == 'id' || $link_segments[3] == 'profiles')
                $resource_stats = SteamUserInfo::get_user_info_by_url($link);

            else
            {
                $resource_stats['type'] = 'undefined';
                $resource_stats['link'] = $link;
            }

            return $resource_stats;

        }

    }

}

class YouTubeInfo extends Core
{

    public function get_youtube_info($link)
    {
        $resource_stats = array('type' => 'youtube', 'link' => $link);

        $video_ID = preg_replace('~
            # Match non-linked youtube URL in the wild. (Rev:20130823)
            https?://         # Required scheme. Either http or https.
            (?:[0-9A-Z-]+\.)? # Optional subdomain.
            (?:               # Group host alternatives.
              youtu\.be/      # Either youtu.be,
            | youtube         # or youtube.com or
              (?:-nocookie)?  # youtube-nocookie.com
              \.com           # followed by
              \S*             # Allow anything up to VIDEO_ID,
              [^\w\s-]        # but char before ID is non-ID char.
            )                 # End host alternatives.
            ([\w-]{11})       # $1: VIDEO_ID is exactly 11 chars.
            (?=[^\w-]|$)      # Assert next char is non-ID or EOS.
            (?!               # Assert URL is not pre-linked.
              [?=&+%\w.-]*    # Allow URL (query) remainder.
              (?:             # Group pre-linked alternatives.
                [\'"][^<>]*>  # Either inside a start tag,
              | </a>          # or inside <a> element text contents.
              )               # End recognized pre-linked alts.
            )                 # End negative lookahead assertion.
            [?=&+%\w.-]*      # Consume any URL (query) remainder.
            ~ix',
            '$1',
            $link);


        $try_cache = $this->mysql_connect->query("SELECT `items` FROM (`youtube_cache`) WHERE `link` = '" . $video_ID . "'");

        if(mysqli_num_rows($try_cache) == 0)
        {

            $JSON = @file_get_contents("https://gdata.youtube.com/feeds/api/videos/{$video_ID}?v=2&alt=json");

            if($JSON === FALSE)
            {

                $resource_stats['error'] = 'Wrong video id';

            }
            else
            {

                $JSON_Data = json_decode($JSON);

                $resource_stats['views'] = $JSON_Data->{'entry'}->{'yt$statistics'}->{'viewCount'};
                $resource_stats['author'] = $JSON_Data->{'entry'}->{'author'}[0]->name->{'$t'};
                $resource_stats['title'] = $JSON_Data->{'entry'}->{'title'}->{'$t'};
                $resource_stats['thumbnail'] = 'http://img.youtube.com/vi/' . $video_ID . '/default.jpg';
                //$resource_stats['description'] = $JSON_Data->{'entry'}->{'media$group'}->{'media$description'}->{'$t'};
                $resource_stats['time'] = gmdate("H:i:s", (int)$JSON_Data->{'entry'}->{'media$group'}->{'media$content'}[0]->duration);

                $this->mysql_connect->query("INSERT INTO `youtube_cache` (`link`, `items`, `datetime`) VALUES ('" . $video_ID . "', '" . base64_encode(serialize($resource_stats)) . "', '" . date('Y-m-d H:i:s') . "');");

            }


        }
        else
        {

            $query = $try_cache->fetch_assoc();
            $resource_stats = unserialize(base64_decode($query['items']));

        }

        return $resource_stats;

    }

}

class SteamUserInfo extends Core
{

    public function get_user_info_by_url($link)
    {

        $resource_stats = array();

        $link_segments = explode('/', $link);

        $resource_stats['type'] = 'steam_profile';
        $resource_stats['link'] = $link;

        $try_cache = $this->mysql_connect->query("SELECT `link`, `profile_id`, `profile_name`, `real_name`, `avatar`, `lang`, `filled` FROM (`users_cache`) WHERE `link` = '" . $link . "'");

        if($link_segments[3] == 'id')
        {

            $resource_stats['title_type'] = 'profile_name';
            $resource_stats['profile_name'] = $link_segments[4];

            if(mysqli_num_rows($try_cache) == 0)
                $this->mysql_connect->query("INSERT INTO `users_cache` (`link`, `profile_name`, `filled`) VALUES ('" . $link. "', '" . $link_segments[4] . "', 0);");
            else
            {

                $cache_value = $try_cache->fetch_assoc();

                if($cache_value['filled'] == 1)
                {

                    foreach($cache_value as $key => $item)
                    {
                        if(!empty($item) && $item !== '' && $key !== 'filled' && !isset($resource_stats[$key]))
                            $resource_stats[$key] = $item;
                    }

                }

            }

        }

        elseif($link_segments[3] == 'profiles')
        {

            $resource_stats['title_type'] = 'profile_id';
            $resource_stats['profile_name'] = $link_segments[4];

            if(mysqli_num_rows($try_cache) == 0)
                $this->mysql_connect->query("INSERT INTO `users_cache` (`link`, `profile_id`, `filled`) VALUES ('" . $link. "', '" . $link_segments[4] . "', 0);");
            else
            {

                $cache_value = $try_cache->fetch_assoc();

                if($cache_value['filled'] == 1)
                {

                    foreach($cache_value as $key => $item)
                    {
                        if(!empty($item) && $item !== '' && $key !== 'filled' && !isset($resource_stats[$key]))
                            $resource_stats[$key] = $item;
                    }

                }

            }

        }

        return $resource_stats;

    }

}

class Render extends Core
{


    public function render_serialize($items)
    {

        echo serialize($items);

    }

    public function render_json($items)
    {

        echo json_encode($items);

    }

    public function render_xml($items, $item_name = 'review')
    {

        $xml = new SimpleXMLElement('<xml/>');

        foreach($items as $item)
        {

            $track = $xml->addChild($item_name);

            foreach($item as $key => $value)
                $this->loop_generate_xml($track, $key, $value);

        }

        Header('Content-type: text/xml');
        echo $xml->asXML();


    }

    private function loop_generate_xml($xml, $name, $array)
    {

        /* shit function */

        if(is_array($array))
            $current_condition = $xml->addChild($name, '');
        else
            $current_condition = $xml;

        if(is_array($array))
        {
            foreach($array as $key => $value)
                $result = $this->loop_generate_xml($current_condition, $key, $value);
        }
        else
            $result = $current_condition->addChild($name, $array);

        return $result;

    }

}

class UrlContent extends Core
{

    public function get_url_content($get_url)
    {

        $config = $this->get_config();

        $fake_browser_data = array(

            'http'=>array(
                'method' => "GET",
                'User-Agent' => $config['fake_user_agent'],
                'header' => "Accept-language: " . $config['language_header_components'][$_GET['language']]['header'] . "\r\n" .
                    "Cookie: Steam_Language=" . $config['language_header_components'][$_GET['language']]['cookie'] . "\r\n"
            )

        );

        $context = stream_context_create($fake_browser_data);
        $uconfig = @fopen($get_url, 'r', false, $context);
        $steam_html = @stream_get_contents($uconfig);

        return $steam_html;

    }

    public function try_get_url_content($url, $try_count = false)
    {

        $config = $this->get_config();

        if($try_count == false)
            $try_count = $config['retrying_get_content'];

        $try_get_content = @$this->get_url_content($url);

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

class SteamSharedFiles extends Core
{

    public function get_sharedfiles($link)
    {

        $resource_stats = array(
            'type' => 'steam_file',
            'link' => $link
        );

        $detect_file_id = explode('?id=', $link);

        if(count($detect_file_id) !== 2)
            return $resource_stats;

        $file_id = $detect_file_id[1];

        $try_cache = $this->mysql_connect->query("SELECT `id`, `file_type`, `direct_url`, `filled`, `game` FROM (`shared_files_cache`) WHERE `id` = '" . $file_id . "'");

        if(mysqli_num_rows($try_cache) == 0)
            $this->mysql_connect->query("INSERT INTO `shared_files_cache` (`id`, `filled`) VALUES ('" . $file_id . "', 0);");
        else
        {

            $cache_value = $try_cache->fetch_assoc();

            if($cache_value['filled'] == 1)
            {

                foreach($cache_value as $key => $item)
                {
                    if(!empty($item) && $item !== '' && $key !== 'filled' && !isset($resource_stats[$key]))
                        $resource_stats[$key] = $item;
                }

            }

        }

        return $resource_stats;

    }

}