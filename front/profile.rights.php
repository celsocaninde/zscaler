<?php

use GlpiPlugin\Zscaler\Profile;

include('../../../inc/includes.php');

\Session::checkRight('profile', UPDATE);

$profileId = (int)($_POST['profiles_id'] ?? 0);
if ($profileId <= 0) {
   \Session::addMessageAfterRedirect('Perfil nao encontrado.', false, ERROR);
   \Html::back();
}

Profile::saveRightsForProfile($profileId, (array)($_POST['plugin_zscaler_rights'] ?? []));
\Session::addMessageAfterRedirect('Permissoes Zscaler atualizadas com sucesso.');
\Html::redirect('/front/profile.form.php?id=' . $profileId);
