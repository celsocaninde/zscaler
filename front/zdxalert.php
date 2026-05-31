<?php

use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\ZdxAlert;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Alertas ZDX', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
\Search::show(ZdxAlert::class);
\Html::footer();
