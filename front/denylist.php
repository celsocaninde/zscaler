<?php

use GlpiPlugin\Zscaler\DenylistEntry;
use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('URLs bloqueadas Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
\Search::show(DenylistEntry::class);
\Html::footer();
