<?php

/* HELPERS */

/*
 * Fast print_r array
 */
function overdie($array)
{

    echo '<pre>';
    print_r($array);
    echo '</pre>';

    die();

}

/*
 *  Shift dates
 */
function recalculate_date($date, $rec)
{

    return date('Y-m-d H:i:s', strtotime($rec, strtotime($date)));

}

/*
 * Getting values between two words in the text, for the first occurrence
 */
function get_string_between($string, $start, $end)
{

    $string = " ".$string;
    $ini = strpos($string,$start);
    if ($ini == 0) return "";
    $ini += strlen($start);
    $len = strpos($string,$end,$ini) - $ini;
    return substr($string,$ini,$len);

}

/*
 * Replacement function of unknown sequence of characters consisting of one template
 */
function str_replace_group($target, $replace_to, $string)
{

    $string = preg_replace('/(' . $target . ')\1+/', '$1', $string);
    $string = str_replace($target, $replace_to , $string);
    return $string;

}

/*
 * Static DB connection
 * Keeps a connection to MySQL
 */
class DB
{

    public static $conn;

}

/* CORE */
class Core extends Config
{

    /* MYSQL */
    public function do_mysql_connect()
    {

        $config = $this->get_config();
        DB::$conn = @mysqli_connect($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_database']);

        if (!DB::$conn)
            die('db connect error');
        else
            return true;

    }

    public function close_mysql_connect()
    {

        mysqli_close(DB::$conn);

    }

    /*
     * Checks number of requests from the same IP
     */
    public function check_request_limit()
    {

        $config = $this->get_config();

        $ip = $_SERVER['REMOTE_ADDR'];

        //Current number of requests
        $dirt_result = DB::$conn->query("SELECT * FROM request_limit WHERE ip='" . $ip . "' ORDER BY id DESC");
        $result = $dirt_result->fetch_assoc();

        //if not found
        if($result == false && $config['save_cache'] == true)
            DB::$conn->query("INSERT INTO `request_limit` (`ip`, `count`, `datetime`) VALUES ('" . $ip . "', 1, '" . date('Y-m-d H:i:s') . "')");

        //if it exists, but expired
        elseif($result['datetime'] < recalculate_date(date('Y-m-d H:i:s'), '-' . $config['request_limit_time_in_minutes']))
        {

            DB::$conn->query("DELETE FROM `request_limit` WHERE `ip` = '" . $ip . "'");

            if($config['save_cache'] == true)
                DB::$conn->query("INSERT INTO `request_limit` (`ip`, `count`, `datetime`) VALUES ('" . $ip . "', 1, '" . date('Y-m-d H:i:s') . "')");

        }

        //if found
        else
        {

            $count = (int)$result['count'];
            $count++;

            if($count >= $config['count_request_limit'])
                die('Exceeded the query limit. The limit is reset at ' . recalculate_date($result['datetime'], '+' . $config['request_limit_time_in_minutes']));

            DB::$conn->query("UPDATE `request_limit` SET `count` = " . $count . " WHERE `id` = " . $result['id']);

        }

    }

    /*
     * Checking GET parameters
     */
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

    /*
     * Search cached reviews
     */
    public function get_reviews()
    {

        $config = $this->get_config();

        $dirt_result = DB::$conn->query("SELECT `id`, `datetime`, `items` FROM (`reviews_cache`) WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "' ORDER BY id DESC");
        $result = $dirt_result->fetch_assoc();

        //if not found
        if($result == false)
        {

            $items = $this->get_reviews_from_steam();

            if(!empty($items))
            {
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `reviews_cache` (`app`, `offset`, `filter`, `language`, `datetime`, `items`, `day_range`) VALUES ('" . $_GET['app'] . "', '" . $_GET['offset'] . "', '" . $_GET['filter'] . "', '" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $safe_serialize_items . "', '" . $_GET['day_range'] . "')");
            }

            return $items;

        }

        //if it exists, but expired
        elseif(date('Y-m-d H:i:s') > recalculate_date($result['datetime'], '+' . $config['reviews_cache_time']))
        {

            $items = $this->get_reviews_from_steam();

            if(!empty($items))
            {
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                DB::$conn->query("DELETE FROM `reviews_cache` WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "'");

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `reviews_cache` (`app`, `offset`, `filter`, `language`, `datetime`, `items`, `day_range`) VALUES ('" . $_GET['app'] . "', '" . $_GET['offset'] . "', '" . $_GET['filter'] . "', '" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $safe_serialize_items . "', '" . $_GET['day_range'] . "')");
            }
            return $items;

        }

        //if cache isset
        else
        {

            $dirt_result = DB::$conn->query("SELECT `items` FROM (`reviews_cache`) WHERE `app` = '" . $_GET['app'] . "' AND `offset` = '" . $_GET['offset'] . "' AND `filter` = '" . $_GET['filter'] . "' AND `day_range` = '" . $_GET['day_range'] . "' AND `language` = '" . $_GET['language'] . "' ORDER BY id DESC");
            $result = $dirt_result->fetch_assoc();

            return unserialize(base64_decode($result['items']));

        }

    }

    /*
     * Checking GET parameters
     */
    public function get_search_query_validate()
    {

        $config = $this->get_config();

        if(!isset($_GET['language']) || !in_array($_GET['language'], $config['languages']))
            die('Wrong language');

        if(!isset($_GET['term']))
            die('Wrong search word');

    }

    /*
     * Displaying results
     */
    public function render($items, $item_name = 'review')
    {

        $Render = new Render();

        /* Detect render type */
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

    /*
     * Search results in the cache
     */
    public function get_search()
    {

        $config = $this->get_config();

        $dirt_result = DB::$conn->query("SELECT `id`, `datetime`, `items` FROM (`search_cache`) WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "' ORDER BY id DESC");
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

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `search_cache` (`language`, `datetime`, `search`, `items`) VALUES ('" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $_GET['term'] . "', '" . $safe_serialize_items . "')");
            }
            return $items;

        }

        //if it exists, but expired
        elseif(date('Y-m-d H:i:s') > recalculate_date($result['datetime'], '+' . $config['search_cache_time']))
        {

            $items = $this->get_search_result_from_steam();

            if(!empty($items))
            {
                $this->cache_search_app_items($items);
                $serialize_items = base64_encode(serialize($items));
                $safe_serialize_items = mysql_real_escape_string($serialize_items);

                DB::$conn->query("DELETE FROM `search_cache` WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "'");

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `search_cache` (`language`, `datetime`, `search`, `items`) VALUES ('" . $_GET['language'] . "', '" . date('Y-m-d H:i:s') . "', '" . $_GET['term'] . "', '" . $safe_serialize_items . "')");
            }
            return $items;

        }

        //if cache isset
        else
        {
            $dirt_result = DB::$conn->query("SELECT `id`, `datetime`, `items` FROM (`search_cache`) WHERE `language` = '" . $_GET['language'] . "' AND `search` = '" . $_GET['term'] . "' ORDER BY id DESC");
            $result = $dirt_result->fetch_assoc();
            $final_array = unserialize(base64_decode($result['items']));

            return $final_array;

        }

    }

    /*
     * Filling avatars
     */
    public function set_items_avatars($array)
    {

        if(!empty($array))
        {

            $config = $this->get_config();

            foreach($array as $id => $item)
            {

                if(isset($item['app_id']))
                {

                    foreach($config['app_image_types'] as $size => $part)
                        $array[$id]['avatars'][$size] = 'http://cdn.akamai.steamstatic.com/steam/apps/' . $item['app_id'] . '/' . $part;

                }

            }

        }

        return $array;

    }

    /*
     * Filling links
     */
    public function set_items_links($array)
    {

        if(!empty($array))
        {

            $config = $this->get_config();

            foreach($array as $id => $item)
            {

                if(isset($item['author_avatar']))
                    $array[$id]['author_avatar'] = $config['avatar_url_part'] . $item['author_avatar'];

                if(isset($item['author_profile']))
                    $array[$id]['author_profile'] = $config['profile_url_part'] . $item['author_profile'];

            }

        }

        return $array;

    }

    /*
     * Sending cache to the database
     */
    private function cache_search_app_items($array)
    {
        $config = $this->get_config();

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

        $dirt_result = DB::$conn->query("SELECT `app_id` FROM (`apps_list`) WHERE (" . $generate_where_query . ")");

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

        if($config['save_cache'] == true)
            DB::$conn->query("INSERT INTO `apps_list` (`app_id`, `name`) VALUES " . $insert_values_string . ";");

    }

    /*
     * Getting search results from Steam
     */
    private function get_search_result_from_steam()
    {

        $config = $this->get_config();

        $UrlContent = new UrlContent();
        $HtmlProcessing = new HtmlProcessing();

        $url = 'http://store.steampowered.com/search/suggest?term=' . urlencode($_GET['term']) . '&f=games&cc=' . mb_strtoupper($config['language_header_components'][$_GET['language']]['header']) . '&l=' . $config['language_header_components'][$_GET['language']]['cookie'];
        $steam_html = $UrlContent->try_get_url_content($url);

        return $HtmlProcessing->detect_search_result_in_html($steam_html);

    }

    /*
     * Get reviews from steam
     */
    private function get_reviews_from_steam()
    {

        $UrlContent = new UrlContent();
        $HtmlProcessing = new HtmlProcessing();

        $url = 'http://store.steampowered.com/appreviews/' . $_GET['app'] . '?start_offset=' . $_GET['offset'] . '&day_range=' . $_GET['day_range'] . '&filter=' . $_GET['filter'] . '&language=' . $_GET['language'];
        $steam_json = json_decode($UrlContent->try_get_url_content($url));

        if($steam_json->success !== 1)
            die('App not found');

        return $HtmlProcessing->detect_reviews_in_html($steam_json->html);

    }

}

class YouTubeInfo extends Core
{

    public function get_youtube_info($link)
    {
        $config = $this->get_config();

        $resource_stats = array('type' => 'youtube', 'link' => $link);

        $video_id = $this->detect_video_id($link);

        $try_cache = DB::$conn->query("SELECT `items` FROM (`youtube_cache`) WHERE `link` = '" . $video_id . "'");

        if(mysqli_num_rows($try_cache) == 0)
        {

            $JSON = @file_get_contents("https://gdata.youtube.com/feeds/api/videos/{$video_id}?v=2&alt=json");

            if($JSON === FALSE)
                $resource_stats['error'] = 'Wrong video id';
            else
            {

                $JSON_Data = json_decode($JSON);

                $resource_stats['views'] = $JSON_Data->{'entry'}->{'yt$statistics'}->{'viewCount'};
                $resource_stats['author'] = $JSON_Data->{'entry'}->{'author'}[0]->name->{'$t'};
                $resource_stats['title'] = $JSON_Data->{'entry'}->{'title'}->{'$t'};
                $resource_stats['thumbnail'] = 'http://img.youtube.com/vi/' . $video_id . '/default.jpg';
                //$resource_stats['description'] = $JSON_Data->{'entry'}->{'media$group'}->{'media$description'}->{'$t'};
                $resource_stats['time'] = gmdate("H:i:s", (int)$JSON_Data->{'entry'}->{'media$group'}->{'media$content'}[0]->duration);

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `youtube_cache` (`link`, `items`, `datetime`) VALUES ('" . $video_id . "', '" . base64_encode(serialize($resource_stats)) . "', '" . date('Y-m-d H:i:s') . "');");

            }


        }
        else
        {

            $query = $try_cache->fetch_assoc();
            $resource_stats = unserialize(base64_decode($query['items']));

        }

        return $resource_stats;

    }

    private function detect_video_id($link)
    {

        return preg_replace('~
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

    }

}

class SteamCurator extends Core
{

    public function get_curator_info($link)
    {

        $link_segments = explode('/', $link);

        $resource_stats['type'] = 'curator';
        $resource_stats['link'] = $link;
        $resource_stats['curator_id'] = $link_segments[4];

        return $resource_stats;

    }

}

class SteamDiscussions extends Core
{

    public function get_discussions_link($link)
    {

        $resource_stats = array();

        $exploded_link = explode('/', $link);

        $resource_stats['type'] = 'discussions link';
        $resource_stats['link'] = $link;

        if(isset($exploded_link[4]))
            $resource_stats['app_id'] = $exploded_link[4];

        if(isset($exploded_link[7]))
            $resource_stats['discussion_id'] = $exploded_link[7];

        if(isset($exploded_link[8]))
            $resource_stats['navigate_to_id'] = str_replace('#', '', $exploded_link[8]);

        return $resource_stats;

    }

}


class SteamExternalLink extends Core
{

    public function get_direct_link($link)
    {

        $resource_stats = array();

        $detect_real_link = explode('?url=', $link);

        $resource_stats['type'] = 'external link';
        $resource_stats['direct_link'] = mb_strtolower($detect_real_link[1]);

        $detect_domain = str_replace("http://", "", mb_strtolower($detect_real_link[1]));
        $detect_domain = str_replace("https://", "", $detect_domain);

        if(substr_count($detect_domain, '/') > 0)
        {

            $segments = explode('/', $detect_domain);
            $detect_domain = $segments[0];

        }

        $resource_stats['domain_name'] = $detect_domain;

        return $resource_stats;

    }

}

class SteamUserInfo extends Core
{

    public function get_user_info_by_url($link)
    {
        $config = $this->get_config();
        $resource_stats = array();

        $link_segments = explode('/', $link);

        $resource_stats['type'] = 'steam_profile';
        $resource_stats['link'] = $link;

        $try_cache = DB::$conn->query("SELECT `link`, `profile_id`, `profile_name`, `real_name`, `avatar`, `lang`, `filled` FROM (`users_cache`) WHERE `link` = '" . $link . "'");

        if($link_segments[3] == 'id')
        {

            $resource_stats['title_type'] = 'profile_name';
            $resource_stats['profile_name'] = $link_segments[4];

            if(mysqli_num_rows($try_cache) == 0 && $config['save_cache'] == true)
                DB::$conn->query("INSERT INTO `users_cache` (`link`, `profile_name`, `filled`) VALUES ('" . $link. "', '" . $link_segments[4] . "', 0);");
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

            if(mysqli_num_rows($try_cache) == 0 && $config['save_cache'] == true)
                DB::$conn->query("INSERT INTO `users_cache` (`link`, `profile_id`, `filled`) VALUES ('" . $link. "', '" . $link_segments[4] . "', 0);");
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

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xml/>');

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

    private function get_url_content($get_url)
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

}

class SteamSharedFiles extends Core
{

    public function get_sharedfiles($link)
    {

        $config = $this->get_config();

        $resource_stats = array(
            'type' => 'steam_file',
            'link' => $link
        );

        $detect_file_id = explode('?id=', $link);

        if(count($detect_file_id) !== 2)
            return $resource_stats;

        $file_id = $detect_file_id[1];

        $try_cache = DB::$conn->query("SELECT `id`, `file_type`, `direct_url`, `title`, `filled` FROM (`shared_files_cache`) WHERE `id` = '" . $file_id . "'");

        /* if new resource */
        if(mysqli_num_rows($try_cache) == 0)
        {

            /* get url steam_file content */
            $resource = $this->detect_steam_file($link);

            if(!empty($resource))
            {

                $resource['id'] = $file_id;

                $resource_stats = array_merge($resource_stats, $resource);

                $first_part = array();
                $second_part = array();

                foreach($resource as $row => $value)
                {
                    $first_part[] = $row;
                    $second_part[] = $value;
                }

                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `shared_files_cache` (`" . implode('`, `', $first_part) . "`) VALUES ('" . implode("', '", $second_part) . "');");

                unset($resource_stats['filled']);

            }
            else
            {

                /* if impossible detect resource, set empty value */
                if($config['save_cache'] == true)
                    DB::$conn->query("INSERT INTO `shared_files_cache` (`id`, `filled`) VALUES ('" . $file_id . "', 0);");

            }

        }
        else
        {

            $cache_value = $try_cache->fetch_assoc();

            /* if already in db */
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

    private function detect_steam_file($link)
    {

        $UrlContent = new UrlContent();
        $html = $UrlContent->try_get_url_content($link);

        $return_array = array();

        /* detect steam images */
        if(substr_count($html, '<img id="ActualMedia" class="artPreviewImage"') == 1)
        {
            $return_array['file_type'] = 'image';
            $return_array['title'] = get_string_between($html, '<div class="workshopItemTitle">', '</div>');
            $return_array['direct_url'] = get_string_between($html, '<img id="ActualMedia" class="artPreviewImage" src="', '" width="');
            $return_array['filled'] = 1;
        }
        /* detect steam screen */
        elseif(substr_count($html, '<img id="ActualMedia" class="screenshotEnlargeable"') == 1)
        {
            $return_array['file_type'] = 'screen';
            $return_array['game'] = get_string_between($html, '<div class="apphub_AppName ellipsis">', '</div>');
            $return_array['direct_url'] = get_string_between($html, '<img id="ActualMedia" class="screenshotEnlargeable" src="', '" width="');
            $return_array['filled'] = 1;
        }

        return $return_array;

    }

}

class HtmlProcessing extends Core
{

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

    public function detect_reviews_in_html($html)
    {

        $config = $this->get_config();

        $reviews_explode = explode('<div class="review_box">', $html);
        unset($reviews_explode[0]);

        if(empty($reviews_explode))
            die('No reviews');

        $items = array();

        foreach($reviews_explode as $item)
        {

            /* harvester block */
            $new_item = array();

            $dirty_date = get_string_between($item, '<div class="postedDate">', '</div>');
            $new_item['publish_date'] = trim($dirty_date);

            /* detect and clear html */
            $dirty_text = get_string_between($item, '<div class="content">', '<div class="gradient"></div>');
            $dirty_text = str_replace("\r\n", "", $dirty_text);

            $dirty_text = trim($dirty_text);
            $dirty_text = str_replace('<div class="bb_code">', "", $dirty_text);

            $dirty_text = str_replace('<b>', "[b]", $dirty_text);
            $dirty_text = str_replace('</b>', "[/b]", $dirty_text);

            $replaces_tag_array = array(

                /* Remove empty spans */
                'span' => array(
                    'start_what' => '<span>',
                    'start_than' => '',
                    'finish_what' => '</span>',
                    'finish_than' => '',
                    'tag_name' => 'span'
                ),

                'h1' => array(
                    'start_what' => '<div class="bb_h1">',
                    'start_than' => '[h1]',
                    'finish_what' => '</div>',
                    'finish_than' => '[/h1]',
                    'tag_name' => 'div'
                ),

                /* Spoilers */
                'spoiler' => array(
                    'start_what' => '<span class="bb_spoiler">',
                    'start_than' => '[spoiler]',
                    'finish_what' => '</span>',
                    'finish_than' => '[/spoiler]',
                    'tag_name' => 'span'
                ),

                /* Strikethrough */
                's' => array(
                    'start_what' => '<span class="bb_strike">',
                    'start_than' => '[s]',
                    'finish_what' => '</span>',
                    'finish_than' => '[/s]',
                    'tag_name' => 'span'
                ),

                /* Quotations */
                'blockquote' => array(
                    'start_what' => '<blockquote class="bb_blockquote">',
                    'start_than' => '[bq]',
                    'finish_what' => '</blockquote>',
                    'finish_than' => '[/bq]',
                    'tag_name' => 'blockquote'
                ),

                /* Lists */
                'ul' => array(
                    'start_what' => '<ul class="bb_ul">',
                    'start_than' => '[ul]',
                    'finish_what' => '</ul>',
                    'finish_than' => '[/ul]',
                    'tag_name' => 'ul'
                )

            );

            foreach($replaces_tag_array as $conf)
                $dirty_text = $this->str_replace_tags($conf, $dirty_text);

            $dirty_text = str_replace('<li>', "[li]", $dirty_text);
            $dirty_text = str_replace('</li>', "[/li]", $dirty_text);

            $dirty_text = str_replace('<u>', "[u]", $dirty_text);
            $dirty_text = str_replace('</u>', "[/u]", $dirty_text);

            $dirty_text = str_replace('<i>', "[i]", $dirty_text);
            $dirty_text = str_replace('</i>', "[/i]", $dirty_text);

            $dirty_text = str_replace('<br>', "[br]", $dirty_text);

            $dirty_text = str_replace('&quot;', '"', $dirty_text);

            /* parse insults */
            $dirty_text = str_replace_group('♥', "[c]", $dirty_text);

            /* resources block */
            /* detect count resources in text */
            $resource_count = substr_count($dirty_text, '<a class="bb_link" target="_blank" href="');

            if($resource_count > 0)
            {

                $resource_counter = 1;

                /* explode text by links */
                $resources_array = explode('<a class="bb_link" ', $dirty_text);
                unset($resources_array[0]);

                foreach($resources_array as $key => $resource)
                {

                    /* Check to isset link */
                    $detect_link = substr_count($resource, 'target="_blank"');

                    if($detect_link == 1)
                    {

                        /* Detect dynamiclink */
                        $detect_dynamic = substr_count($resource, 'id="dynamiclink_');

                        if($detect_dynamic == 1)
                        {

                            /* dynamiclink */
                            $dynamiclink_value = get_string_between($resource, '  id="dynamiclink_', '">');
                            $detect_link = get_string_between($resource, 'target="_blank" href="', '"  id="dynamiclink_' . $dynamiclink_value);

                            /* detect resource */
                            $new_item['resources']['resource_' . $resource_counter] = $this->detect_resource($detect_link);

                            /* cap link */
                            $resource_title = get_string_between($dirty_text, '<a class="bb_link" target="_blank" href="' . $detect_link . '"  id="dynamiclink_' . $dynamiclink_value . '">', '</a>');

                            $changeable_code = '<a class="bb_link" target="_blank" href="' . $detect_link . '"  id="dynamiclink_' . $dynamiclink_value . '">' . $resource_title . '</a>';
                            $dirty_text = str_replace($changeable_code, "[resource_" . $resource_counter . "]", $dirty_text);

                            $resource_counter++;

                        }
                        else
                        {

                            /* other link */
                            $detect_link = get_string_between($resource, 'target="_blank" href="', '" >');

                            /* detect resource */
                            $new_item['resources']['resource_' . $resource_counter] = $this->detect_resource($detect_link);

                            /* cap link */
                            $resource_title = get_string_between($dirty_text, '<a class="bb_link" target="_blank" href="' . $detect_link . '" >', '</a>');

                            $changeable_code = '<a class="bb_link" target="_blank" href="' . $detect_link . '" >' . $resource_title . '</a>';
                            $dirty_text = str_replace($changeable_code, "[resource_" . $resource_counter . "]", $dirty_text);

                            $resource_counter++;

                        }

                    }

                }

            }

            $new_item['text'] = trim($dirty_text);

            //detect and calculate stats
            $stats = get_string_between($item, '<div class="header">', '</div>');
            $stats = trim($stats);

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

            $new_item['author_profile'] = get_string_between($item, '<div class="persona_name"><a href="' . $config['profile_url_part'], '" data-miniprofile="');

            $dirty_author_number_of_games = get_string_between($item, '<div class="num_owned_games"><a href="', '</a></div>');
            $dirty_author_number_of_games_e = explode('">', $dirty_author_number_of_games);
            $dirty_author_number_of_games_e2 = explode(' ', $dirty_author_number_of_games_e[1]);
            $new_item['author_number_of_games'] = end($dirty_author_number_of_games_e2);

            $dirty_author_number_of_reviews = get_string_between($item, '<div class="num_reviews"><a href="', '</a></div>');
            $dirty_author_number_of_reviews_e = explode('">', $dirty_author_number_of_reviews);
            $dirty_author_number_of_reviews_e2 = explode(' ', $dirty_author_number_of_reviews_e[1]);
            $new_item['author_number_of_reviews'] = end($dirty_author_number_of_reviews_e2);

            $dirty_author_avatar = get_string_between($item, '<div class="playerAvatar', '</div>');
            $new_item['author_avatar'] = get_string_between($dirty_author_avatar, '<img src="' . $config['avatar_url_part'], '" data-miniprofile="');

            $dirty_author_played = get_string_between($item, '<div class="hours">', '</div>');
            $dirty_author_played_e = explode(' ', $dirty_author_played);
            $new_item['author_played'] = $dirty_author_played_e[0];

            $items[] = $new_item;

        }

        return $items;

    }

    /*
     * Identification of type of resource links
     */
    private function detect_resource($link)
    {

        if(substr_count($link, 'youtube') > 0)
        {

            $YouTubeInfo = new YouTubeInfo();

            $resource_stats = $YouTubeInfo->get_youtube_info($link);
            return $resource_stats;

        }
        else
        {

            $resource_stats = array();
            $link_segments = explode('/', $link);

            $SteamSharedFiles = new SteamSharedFiles();
            $SteamUserInfo = new SteamUserInfo();
            $SteamCurator = new SteamCurator();
            $SteamExternalLink = new SteamExternalLink();
            $SteamDiscussions = new SteamDiscussions();

            /* Detect shared steam resources */
            if($link_segments[3] == 'sharedfiles')
                $resource_stats = $SteamSharedFiles->get_sharedfiles($link);

            /* Detect steam profiles link */
            elseif($link_segments[3] == 'id' || $link_segments[3] == 'profiles')
                $resource_stats = $SteamUserInfo->get_user_info_by_url($link);

            /* Detect steam curator */
            elseif($link_segments[3] == 'curator')
                $resource_stats = $SteamCurator->get_curator_info($link);

            elseif($link_segments[3] == 'linkfilter')
                $resource_stats = $SteamExternalLink->get_direct_link($link);

            elseif($link_segments[5] == 'discussions')
                $resource_stats = $SteamDiscussions->get_discussions_link($link);

            else
            {
                $resource_stats['type'] = 'undefined';
                $resource_stats['link'] = $link;
            }

            return $resource_stats;

        }

    }

    /*
     * Finding and replacing tags, excluding other tags in text
     */
    private function str_replace_tags($conf, $text)
    {

        /* Tags management script */
        if(isset($conf['start_what']) && isset($conf['start_than']))
        {

            $tag_count = substr_count($text, $conf['start_what']);

            if($tag_count == 0)
                return $text;
            else
            {

                $split_text = explode($conf['start_what'], $text);

                /* If !isset finish config tag */
                if(!isset($conf['finish_what']) && !isset($conf['finish_than']))
                    return implode($conf['start_than'], $split_text);
                else
                {

                    /* If isset finish config tag */
                    $detect_count_start_tag = substr_count($text, $conf['start_what']);
                    $detect_count_finish_tag = substr_count($text, $conf['finish_what']);

                    /* If number of start tag equal to number of finish tags  */
                    if($detect_count_start_tag == $detect_count_finish_tag)
                    {

                        /* Direct replace and return */
                        $text = str_replace($conf['start_what'], $conf['start_than'], $text);
                        $text = str_replace($conf['finish_what'], $conf['finish_than'], $text);

                        return $text;

                    }
                    else
                    {

                        /* Collect all tags string */
                        $original_array = $this->convert_text_to_tags_array($conf['tag_name'], $text);

                        if(!empty($original_array))
                        {

                            /* Duplicate tags array */
                            $replaced_array = $original_array;

                            /* Replacing relevant tags to template */
                            foreach($replaced_array as $tag_name => $tag_value)
                            {

                                $detect_count_start_tag = substr_count($tag_value, $conf['start_what']);

                                if($detect_count_start_tag == 1)
                                {
                                    $replaced_array[$tag_name] = str_replace($conf['start_what'], $conf['start_than'], $tag_value);
                                    $replaced_array[$tag_name] = str_replace($conf['finish_what'], $conf['finish_than'], $replaced_array[$tag_name]);
                                }

                            }

                            /* Combination of template and original */
                            foreach($replaced_array as $tag_name => $tag_value)
                            {

                                foreach($replaced_array as $t_name => $t_value)
                                {
                                    if(substr_count($tag_value, $t_name) > 0)
                                        $replaced_array[$tag_name] = str_replace('[' . $t_name . ']', $t_value, $replaced_array[$tag_name]);
                                }

                                foreach($original_array as $t_name => $t_value)
                                {
                                    if(substr_count($tag_value, $t_name) > 0)
                                        $original_array[$tag_name] = str_replace('[' . $t_name . ']', $t_value, $original_array[$tag_name]);
                                }

                            }

                            /* Duplicate of original text */
                            $converted_text = $text;
                            $replaced_array = array_reverse($replaced_array);

                            /* Replace in text */
                            foreach($replaced_array as $tag_name => $tag_value)
                            {

                                if($tag_value !== $original_array[$tag_name])
                                    $converted_text = str_replace($original_array[$tag_name], $tag_value, $converted_text);

                            }

                            return $converted_text;

                        }
                        else
                            return $text;

                    }

                }

            }

        }
        else
            return false;

    }

    /*
     * The search function tags in the text, and transform them into an array
     */
    private function convert_text_to_tags_array($tag, $text, $collected_tags = array(), $counter = 1)
    {

        /* Detect tags value */
        $detect_count_start_tag = substr_count($text, '<' . $tag);

        /* If the tag is not present, the accumulated return array */
        if($detect_count_start_tag == 0)
            return $collected_tags;
        else
        {

            /* Splitting text by open tags */
            $split_by_open_tag = explode('<' . $tag, $text);

            foreach($split_by_open_tag as $item)
            {

                $tag_count = substr_count($item, '</' . $tag);

                /* If in array element exists closing tags, verifies and processes */
                if($tag_count > 0)
                {

                    $set_tag = '<' . $tag . $item;
                    $clear = '<' . $tag . get_string_between($set_tag, '<' . $tag, '</' . $tag) . '</' . $tag . '>';

                    if(!in_array($clear, $collected_tags))
                        $collected_tags['tag_' . $counter] = $clear;

                    $text = str_replace($clear, '[tag_' . $counter . ']', $text);

                    $counter++;

                }

            }

            return $this->convert_text_to_tags_array($tag, $text, $collected_tags, $counter);
        }

    }

}
