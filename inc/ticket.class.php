<?php
/**
 * Plugin GitLab for GLPI - Ticket Tab & Issue Linking
 *
 * @author  Antigravity
 * @license MIT
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGitlabTicket extends CommonDBTM {

    public static function getTypeName($nb = 0) {
        return __('GitLab Issues', 'gitlab');
    }

    /**
     * Define the tab name on the Ticket object
     *
     * @param CommonGLPI $item
     * @param int $withtemplate
     * @return string|array
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() === 'Ticket' && $item->canViewItem()) {
            $nb = self::countForTicket($item->getID());
            return self::createTabEntry(__('GitLab', 'gitlab'), $nb);
        }
        return '';
    }

    /**
     * Count linked GitLab issues for a ticket
     *
     * @param int $ticketId
     * @return int
     */
    public static function countForTicket(int $ticketId): int {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_gitlab_tickets')) {
            return 0;
        }

        $res = $DB->request([
            'COUNT' => 'c',
            'FROM'  => 'glpi_plugin_gitlab_tickets',
            'WHERE' => ['tickets_id' => $ticketId]
        ]);

        return (int)($res->current()['c'] ?? 0);
    }

    /**
     * Get linked issues records for a ticket
     *
     * @param int $ticketId
     * @return array
     */
    public static function getLinkedIssues(int $ticketId): array {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_gitlab_tickets')) {
            return [];
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_gitlab_tickets',
            'WHERE' => ['tickets_id' => $ticketId],
            'ORDER' => 'id DESC'
        ]);

        $issues = [];
        foreach ($iterator as $row) {
            $issues[] = $row;
        }
        return $issues;
    }

    /**
     * Render the tab content on Ticket form
     *
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return boolean
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() !== 'Ticket') {
            return false;
        }

        $ticketId = (int)$item->getID();
        $config   = PluginGitlabConfig::getConfig();

        if (!PluginGitlabConfig::isConfigured()) {
            echo "<div class='alert alert-warning m-3'>";
            echo "<i class='ti ti-alert-triangle me-2'></i>";
            echo sprintf(
                __('Le plugin GitLab n\'est pas encore configuré. Rendez-vous dans <a href="%s">Configuration > GitLab</a>.', 'gitlab'),
                Plugin::getWebDir('gitlab') . '/front/config.form.php'
            );
            echo "</div>";
            return true;
        }

        $linkedIssues = self::getLinkedIssues($ticketId);
        $client       = new PluginGitlabClient();

        echo "<div class='spaced p-3'>";

        // Display existing linked issues
        if (!empty($linkedIssues)) {
            echo "<h4 class='mb-3'><i class='ti ti-brand-gitlab me-2 text-warning'></i>" . __('Issues GitLab associées à ce ticket', 'gitlab') . "</h4>";
            echo "<div class='table-responsive mb-4'>";
            echo "<table class='table table-hover table-striped card-table'>";
            echo "<thead><tr>";
            echo "<th>" . __('Issue', 'gitlab') . "</th>";
            echo "<th>" . __('Projet', 'gitlab') . "</th>";
            echo "<th>" . __('Titre', 'gitlab') . "</th>";
            echo "<th>" . __('Statut GitLab', 'gitlab') . "</th>";
            echo "<th>" . __('Date de liaison', 'gitlab') . "</th>";
            echo "</tr></thead>";
            echo "<tbody>";

            foreach ($linkedIssues as $link) {
                // Try fetching live issue status from GitLab
                $remoteIssue = $client->getIssue($link['gitlab_project_id'], (int)$link['gitlab_issue_iid']);
                $stateBadge = "<span class='badge bg-secondary'>" . __('Inconnu', 'gitlab') . "</span>";

                if ($remoteIssue) {
                    if ($remoteIssue['state'] === 'opened') {
                        $stateBadge = "<span class='badge bg-success'><i class='ti ti-circle-dot me-1'></i>" . __('Ouverte', 'gitlab') . "</span>";
                    } elseif ($remoteIssue['state'] === 'closed') {
                        $stateBadge = "<span class='badge bg-danger'><i class='ti ti-circle-check me-1'></i>" . __('Fermée', 'gitlab') . "</span>";
                    }
                }

                $issueTitle = htmlspecialchars($remoteIssue['title'] ?? $link['gitlab_issue_title'] ?? ('Issue #' . $link['gitlab_issue_iid']));
                $issueUrl   = htmlspecialchars($link['gitlab_issue_url']);

                echo "<tr>";
                echo "<td><a href='{$issueUrl}' target='_blank' rel='noopener noreferrer' class='fw-bold text-decoration-none'>#{$link['gitlab_issue_iid']} <i class='ti ti-external-link small'></i></a></td>";
                echo "<td><code>" . htmlspecialchars($link['gitlab_project_id']) . "</code></td>";
                echo "<td>{$issueTitle}</td>";
                echo "<td>{$stateBadge}</td>";
                echo "<td>" . Html::convDateTime($link['date_creation']) . "</td>";
                echo "</tr>";
            }

            echo "</tbody></table></div>";
        }

        // Form to create a new issue
        if ($item->canUpdateItem()) {
            $ticketName = $item->fields['name'] ?? '';
            $ticketContent = strip_tags(html_entity_decode($item->fields['content'] ?? ''));
            $glpiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $item->getLinkURL();

            $initialDescription = $glpiUrl;
            if (!empty($ticketContent)) {
                $initialDescription .= "\n\n---\n\n" . $ticketContent;
            }

            echo "<div class='card'>";
            echo "<div class='card-header'><h5 class='card-title mb-0'><i class='ti ti-plus me-2'></i>" . __('Créer une nouvelle issue GitLab', 'gitlab') . "</h5></div>";
            echo "<div class='card-body'>";

            echo "<form method='post' action='" . Plugin::getWebDir('gitlab') . "/front/ticket.form.php'>";
            echo "<input type='hidden' name='action' value='create_issue'>";
            echo "<input type='hidden' name='tickets_id' value='{$ticketId}'>";

            echo "<div class='row mb-3'>";
            echo "<label class='col-sm-3 col-form-label required'>" . __('Projet GitLab (ID ou Chemin)', 'gitlab') . "</label>";
            echo "<div class='col-sm-9'>";
            echo Html::input('project_id', [
                'value'    => $config['default_project_id'] ?? '',
                'required' => 'required',
                'class'    => 'form-control'
            ]);
            echo "<small class='form-hint'>" . __('Exemple : <code>123456</code> ou <code>groupe/nom-projet</code>', 'gitlab') . "</small>";
            echo "</div>";
            echo "</div>";

            echo "<div class='row mb-3'>";
            echo "<label class='col-sm-3 col-form-label required'>" . __('Titre de l\'issue', 'gitlab') . "</label>";
            echo "<div class='col-sm-9'>";
            echo Html::input('issue_title', [
                'value'    => "Ticket #{$ticketId} - " . $ticketName,
                'required' => 'required',
                'class'    => 'form-control'
            ]);
            echo "</div>";
            echo "</div>";

            echo "<div class='row mb-3'>";
            echo "<label class='col-sm-3 col-form-label'>" . __('Description (Markdown)', 'gitlab') . "</label>";
            echo "<div class='col-sm-9'>";
            echo "<textarea name='issue_description' rows='8' class='form-control' style='font-family: monospace;'>" . htmlspecialchars($initialDescription) . "</textarea>";
            echo "</div>";
            echo "</div>";

            echo "<div class='row mb-3'>";
            echo "<label class='col-sm-3 col-form-label'>" . __('Labels (séparés par des virgules)', 'gitlab') . "</label>";
            echo "<div class='col-sm-9'>";
            echo Html::input('issue_labels', [
                'value' => $config['default_labels'] ?? 'glpi',
                'class' => 'form-control'
            ]);
            echo "</div>";
            echo "</div>";

            echo "<div class='text-end'>";
            echo "<button type='submit' class='btn btn-primary'>";
            echo "<i class='ti ti-brand-gitlab me-2'></i>" . __('Créer l\'issue GitLab', 'gitlab');
            echo "</button>";
            echo "</div>";

            Html::closeForm();
            echo "</div></div>";
        }

        echo "</div>";
        return true;
    }
}
