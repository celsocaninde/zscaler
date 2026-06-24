<?php

use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\ZccDevice;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Dispositivos ZCC', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";
\Search::show(ZccDevice::class);
\Html::footer();
