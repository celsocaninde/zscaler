<?php

use GlpiPlugin\Zscaler\Config;
use GlpiPlugin\Zscaler\Profile;
use GlpiPlugin\Zscaler\Sync;

include('../../../inc/includes.php');

\Session::checkRight(Profile::RIGHT_READ, READ);

if (!Profile::hasActionRight() && !Profile::hasConfigReadRight()) {
   \Html::displayRightError();
}

$config = Config::getConfig();

if (!Config::isConfigured($config) && !Config::zccConfigured($config) && !Config::zdxConfigured($config)) {
   \Session::addMessageAfterRedirect('Nenhum modulo Zscaler esta configurado.', false, ERROR);
   \Html::back();
}

if (Config::isConfigured($config)) {
   try {
      $result = Sync::syncAll();
      \Session::addMessageAfterRedirect(sprintf(
         'ZIA: %d categorias, %d URLs na denylist.',
         $result['categories'],
         $result['denylist']
      ));
   } catch (\Throwable $error) {
      \Session::addMessageAfterRedirect('Erro na sincronizacao ZIA: ' . $error->getMessage(), false, ERROR);
   }
}

if (Config::zccConfigured($config)) {
   try {
      $result = Sync::syncZccDevices();
      \Session::addMessageAfterRedirect(sprintf('ZCC: %d dispositivos (%d vinculados a computadores).', $result['processed'], $result['matched']));
   } catch (\Throwable $error) {
      \Session::addMessageAfterRedirect('Erro na sincronizacao ZCC: ' . $error->getMessage(), false, ERROR);
   }
}

if (Config::zdxConfigured($config)) {
   try {
      $result = Sync::syncZdxAlerts();
      \Session::addMessageAfterRedirect(sprintf('ZDX: %d alertas (%d tickets).', $result['processed'], $result['tickets']));
   } catch (\Throwable $error) {
      \Session::addMessageAfterRedirect('Erro na sincronizacao ZDX: ' . $error->getMessage(), false, ERROR);
   }
}

\Html::back();
