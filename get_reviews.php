<?php

require('/config.php');
require('/core.php');

$core = new Core;

$core->do_mysql_connect();
$core->check_request_limit();
$core->get_query_validate();

$core->render($core->get_reviews(), 'review');

$core->close_mysql_connect();