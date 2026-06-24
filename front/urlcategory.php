<?php

use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\UrlCategory;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Categorias de URL Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
echo "<style>.container-xl,.container-lg{max-width:100%!important}</style>";
\Search::show(UrlCategory::class);
\Html::footer();
