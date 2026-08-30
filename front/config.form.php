<?php
/**
 * Plugin GitLab for GLPI - Front configuration controller
 *
 * @author  Antigravity
 * @license MIT
 */

include ('../../../inc/includes.php');

if (class_exists('Config') && method_exists('Config', 'canUpdate')) {
    if (!Config::canUpdate()) {
        Html::displayRightError();
    }
} else {
    Session::checkRight('config', UPDATE);
}

$config = new PluginGitlabConfig();

if (isset($_POST['update']) || isset($_POST['test_connection'])) {
    global $DB;

    $url       = trim($_POST['gitlab_url'] ?? 'https://gitlab.com');
    $projectId = trim($_POST['default_project_id'] ?? '');
    $labels    = trim($_POST['default_labels'] ?? 'glpi');
    $token     = trim($_POST['gitlab_token'] ?? '');

    $fields = [
        'gitlab_url'         => $url,
        'default_project_id' => $projectId,
        'default_labels'     => $labels,
        'date_mod'           => date('Y-m-d H:i:s')
    ];

    // Only update token if a new one was provided
    if (!empty($token)) {
        $fields['gitlab_token'] = $token;
    } else {
        $stored = PluginGitlabConfig::getConfig();
        $token  = $stored['gitlab_token'] ?? '';
    }

    $DB->update('glpi_plugin_gitlab_configs', $fields, ['id' => 1]);

    if (isset($_POST['test_connection'])) {
        $client = new PluginGitlabClient($url, $token);
        $res    = $client->testConnection();

        if ($res['success']) {
            Session::addMessageAfterRedirect(__('Configuration sauvegardée et connexion GitLab réussie !', 'gitlab') . ' ' . $res['message'], false, INFO);
        } else {
            Session::addMessageAfterRedirect(__('Configuration sauvegardée, mais le test de connexion a échoué :', 'gitlab') . ' ' . $res['message'], false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect(__('Configuration GitLab mise à jour avec succès.', 'gitlab'), false, INFO);
    }

    Html::back();
}

Html::header(
    __('Configuration GitLab', 'gitlab'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugin_gitlab_config'
);

$config->showForm();

Html::footer();
