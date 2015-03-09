<?php

require('/config.php');
require('/core.php');

$core = new Core;

$core->do_mysql_connect();
$core->check_request_limit();
$core->get_search_query_validate();

$core->render($core->get_search(), 'item');

$core->close_mysql_connect();