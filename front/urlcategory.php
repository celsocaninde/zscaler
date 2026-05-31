<?php

use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\UrlCategory;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

\Html::header('Categorias de URL Zscaler', $_SERVER['PHP_SELF'], 'plugins', 'zscaler');
\Search::show(UrlCategory::class);
\Html::footer();
