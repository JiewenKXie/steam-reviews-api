<?php

require('/config.php');
require('/core.php');

$core = new Core;

$core->do_mysql_connect();
$core->check_request_limit();
$core->get_query_validate();

$reviews = $core->get_reviews();
$reviews = $core->set_items_links($reviews);

$core->render($reviews, 'review');

$core->close_mysql_connect();