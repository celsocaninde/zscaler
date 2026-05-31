<?php

use GlpiPlugin\Zscaler\ActionLog;
use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Historico de acoes Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
\Search::show(ActionLog::class);
\Html::footer();
