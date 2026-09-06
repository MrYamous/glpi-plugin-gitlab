<?php
/**
 * Plugin GitLab for GLPI
 *
 * @author  GLPI Community
 * @license MIT
 */

define('PLUGIN_GITLAB_VERSION', '0.1.8');
define('PLUGIN_GITLAB_MIN_GLPI', '10.0.0');
define('PLUGIN_GITLAB_MAX_GLPI', '11.9.99');

/**
 * Init the hooks of the plugin
 */
function plugin_init_gitlab() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['gitlab'] = true;

    // Register classes in GLPI
    Plugin::registerClass(
        'PluginGitlabTicket',
        [
            'addtabon' => ['Ticket']
        ]
    );

    Plugin::registerClass('PluginGitlabConfig');

    // Add configuration entry in Setup / Plugins menu
    $PLUGIN_HOOKS['config_page']['gitlab'] = 'front/config.form.php';
}

/**
 * Get the name and the version of the plugin
 *
 * @return array
 */
function plugin_version_gitlab() {
    return [
        'name'           => 'GitLab Issues Integration',
        'version'        => PLUGIN_GITLAB_VERSION,
        'author'         => 'GLPI Community',
        'license'        => 'MIT',
        'homepage'       => 'https://gitlab.com',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_GITLAB_MIN_GLPI,
                'max' => PLUGIN_GITLAB_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.1.0'
            ]
        ]
    ];
}

/**
 * Check plugin prerequisites
 *
 * @return boolean
 */
function plugin_gitlab_check_prerequisites() {
    return true;
}

/**
 * Check plugin configuration
 *
 * @param boolean $verbose
 * @return boolean
 */
function plugin_gitlab_check_config($verbose = false) {
    return true;
}
