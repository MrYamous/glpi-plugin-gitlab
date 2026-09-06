<?php
/**
 * Plugin GitLab for GLPI - Configuration Manager
 *
 * @author  Antigravity
 * @license MIT
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGitlabConfig extends CommonDBTM {

    public static function getTypeName($nb = 0) {
        return __('Configuration GitLab', 'gitlab');
    }

    /**
     * Retrieve the unique configuration record
     *
     * @return array
     */
    public static function getConfig(): array {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_gitlab_configs')) {
            return [];
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_gitlab_configs',
            'WHERE' => ['id' => 1]
        ]);

        if (count($iterator)) {
            return $iterator->current();
        }

        return [];
    }

    /**
     * Check if the plugin has minimal required configuration
     *
     * @return boolean
     */
    public static function isConfigured(): bool {
        $config = self::getConfig();
        return !empty($config['gitlab_url']) && !empty($config['gitlab_token']);
    }

    /**
     * Display configuration form
     *
     * @param int $id
     * @param array $options
     * @return boolean
     */
    public function showForm($id = 1, array $options = []) {
        $config = self::getConfig();

        if (class_exists('Config') && !Config::canUpdate()) {
            return false;
        }

        $hasToken = !empty($config['gitlab_token']);

        echo "<div class='center'>";
        echo "<form method='post' action='" . Plugin::getWebDir('gitlab') . "/front/config.form.php'>";
        echo "<input type='hidden' name='id' value='1'>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<thead><tr class='tab_bg_1'><th colspan='2'>" . __('Configuration du connecteur GitLab', 'gitlab') . "</th></tr></thead>";

        // URL GitLab
        echo "<tr class='tab_bg_2'>";
        echo "<td style='width: 30%;'>" . __('URL GitLab (Instance Cloud ou Auto-hébergée)', 'gitlab') . "</td>";
        echo "<td>";
        echo Html::input('gitlab_url', [
            'value'    => $config['gitlab_url'] ?? 'https://gitlab.com',
            'size'     => 50,
            'required' => 'required'
        ]);
        echo "<br><small class='text-muted'>" . __('Exemple : https://gitlab.com ou https://gitlab.mon-entreprise.com', 'gitlab') . "</small>";
        echo "</td>";
        echo "</tr>";

        // Token GitLab
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Token d\'API GitLab (Personal ou Project Access Token)', 'gitlab') . "</td>";
        echo "<td>";
        echo Html::input('gitlab_token', [
            'type'         => 'password',
            'value'        => '',
            'size'         => 50,
            'placeholder'  => $hasToken ? '•••••••••••••••••••• (Token déjà configuré - laisser vide pour conserver)' : 'Entrez votre token API GitLab...',
            'autocomplete' => 'new-password'
        ]);
        if ($hasToken) {
            echo " <span class='badge bg-success-subtle text-success ms-2'><i class='ti ti-check'></i> " . __('Token enregistré', 'gitlab') . "</span>";
        }
        echo "<br><small class='text-muted'>" . __('Scopes requis : <code>api</code> (ou <code>read_api</code> + <code>write_repository</code>)', 'gitlab') . "</small>";
        echo "</td>";
        echo "</tr>";

        // Default project ID / Path
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Projet GitLab par défaut', 'gitlab') . "</td>";
        echo "<td>";

        $projects = [];
        if (self::isConfigured()) {
            $client = new PluginGitlabClient();
            $projects = $client->getProjects();
        }

        $currentDefault = $config['default_project_id'] ?? '';

        if (!empty($projects)) {
            echo "<select name='default_project_id' class='form-select' style='max-width: 500px;'>";
            echo "<option value=''>" . __('-- Aucun projet par défaut (sélection manuelle) --', 'gitlab') . "</option>";
            foreach ($projects as $project) {
                $pid   = (string)$project['id'];
                $pPath = $project['path_with_namespace'] ?? $project['name_with_namespace'] ?? $pid;
                $pName = $project['name_with_namespace'] ?? $pPath;
                $selected = ($currentDefault == $pid || $currentDefault == $pPath) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($pid) . "' {$selected}>" . htmlspecialchars($pName) . " (#{$pid})</option>";
            }
            echo "</select>";
        } else {
            echo Html::input('default_project_id', [
                'value' => $currentDefault,
                'size'  => 50
            ]);
            echo "<br><small class='text-muted'>" . __('Exemple : <code>12345678</code> ou <code>mon-groupe/mon-projet</code>', 'gitlab') . "</small>";
        }

        echo "</td>";
        echo "</tr>";

        // Default labels
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Labels par défaut pour les issues créées', 'gitlab') . "</td>";
        echo "<td>";
        echo Html::input('default_labels', [
            'value' => $config['default_labels'] ?? 'glpi',
            'size'  => 50
        ]);
        echo "<br><small class='text-muted'>" . __('Labels séparés par des virgules (ex: <code>glpi,support,bug</code>)', 'gitlab') . "</small>";
        echo "</td>";
        echo "</tr>";

        // Submit & Test buttons
        echo "<tr class='tab_bg_2'>";
        echo "<td class='center' colspan='2'>";
        echo "<button type='submit' name='update' class='btn btn-primary me-2'>";
        echo "<i class='ti ti-device-floppy'></i> " . __('Sauvegarder', 'gitlab');
        echo "</button>";

        echo "<button type='submit' name='test_connection' class='btn btn-secondary'>";
        echo "<i class='ti ti-plug'></i> " . __('Tester la connexion GitLab', 'gitlab');
        echo "</button>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        Html::closeForm();
        echo "</div>";

        return true;
    }
}
