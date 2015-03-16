<?php

require('/config.php');
require('/core.php');

$core = new Core;

$core->do_mysql_connect();
$core->check_request_limit();
$core->get_search_query_validate();

$search = $core->get_search();
$search = $core->set_items_avatars($search);

$core->render($search, 'item');

$core->close_mysql_connect();