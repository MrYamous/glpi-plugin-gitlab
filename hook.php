<?php
/**
 * Plugin GitLab for GLPI - Hooks & Installation
 *
 * @author  Antigravity
 * @license MIT
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_gitlab_install() {
    global $DB;

    $version = defined('PLUGIN_GITLAB_VERSION') ? PLUGIN_GITLAB_VERSION : '1.0.3';
    $migration = new Migration($version);

    try {
        // 1. Table for global configuration
        if (!$DB->tableExists('glpi_plugin_gitlab_configs')) {
            $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_gitlab_configs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `gitlab_url` VARCHAR(255) NOT NULL DEFAULT 'https://gitlab.com',
                `gitlab_token` VARCHAR(255) NULL,
                `default_project_id` VARCHAR(255) NULL,
                `default_labels` VARCHAR(255) NOT NULL DEFAULT 'glpi',
                `date_mod` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $DB->doQuery($query);

            // Insert initial default config row using query builder
            $DB->insert('glpi_plugin_gitlab_configs', [
                'id'                 => 1,
                'gitlab_url'         => 'https://gitlab.com',
                'gitlab_token'       => '',
                'default_project_id' => '',
                'default_labels'     => 'glpi',
                'date_mod'           => date('Y-m-d H:i:s')
            ]);
        }

        // 2. Table to link GLPI tickets to GitLab issues
        if (!$DB->tableExists('glpi_plugin_gitlab_tickets')) {
            $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_gitlab_tickets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tickets_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `gitlab_project_id` VARCHAR(255) NOT NULL,
                `gitlab_issue_iid` INT UNSIGNED NOT NULL DEFAULT 0,
                `gitlab_issue_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `gitlab_issue_url` VARCHAR(512) NOT NULL,
                `gitlab_issue_title` VARCHAR(255) NULL,
                `date_creation` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `tickets_id` (`tickets_id`),
                KEY `gitlab_issue_iid` (`gitlab_issue_iid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $DB->doQuery($query);
        }

        $migration->executeMigration();

        return true;
    } catch (\Throwable $e) {
        if (class_exists('Toolbox')) {
            Toolbox::logInFile('php-errors', "Plugin GitLab Install Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        Session::addMessageAfterRedirect("Erreur installation plugin GitLab : " . $e->getMessage(), false, ERROR);
        return false;
    }
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_gitlab_uninstall() {
    global $DB;

    try {
        $tables = [
            'glpi_plugin_gitlab_configs',
            'glpi_plugin_gitlab_tickets'
        ];

        foreach ($tables as $table) {
            if ($DB->tableExists($table)) {
                $DB->doQuery("DROP TABLE IF EXISTS `$table`");
            }
        }

        return true;
    } catch (\Throwable $e) {
        if (class_exists('Toolbox')) {
            Toolbox::logInFile('php-errors', "Plugin GitLab Uninstall Error: " . $e->getMessage());
        }
        return false;
    }
}
