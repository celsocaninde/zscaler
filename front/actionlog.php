<?php

use GlpiPlugin\Zscaler\ActionLog;
use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Historico de acoes Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";
\Search::show(ActionLog::class);
\Html::footer();
