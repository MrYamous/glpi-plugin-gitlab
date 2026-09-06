<?php
/**
 * Plugin GitLab for GLPI - Front ticket issue controller
 *
 * @author  Antigravity
 * @license MIT
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();

$action   = $_POST['action'] ?? '';
$ticketId = (int)($_POST['tickets_id'] ?? 0);

if ($ticketId <= 0) {
    Html::displayErrorAndDie(__('ID de ticket invalide', 'gitlab'));
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    Html::displayErrorAndDie(__('Ticket introuvable', 'gitlab'));
}

if (!Session::haveRight('ticket', UPDATE)) {
    Html::displayRightError();
}

$backUrl = $ticket->getLinkURL() . '&forcetab=PluginGitlabTicket$1';

if ($action === 'create_issue') {
    $projectId   = trim($_POST['project_id'] ?? '');
    $title       = trim($_POST['issue_title'] ?? '');
    $description = trim($_POST['issue_description'] ?? '');
    $labels      = trim($_POST['issue_labels'] ?? '');

    if (empty($projectId) || empty($title)) {
        Session::addMessageAfterRedirect(__('Le projet et le titre sont obligatoires pour créer une issue.', 'gitlab'), false, ERROR);
        Html::redirect($backUrl);
    }

    $client = new PluginGitlabClient();
    $res    = $client->createIssue($projectId, $title, $description, $labels);

    if ($res['success'] && !empty($res['issue'])) {
        global $DB;
        $issue = $res['issue'];

        $now = date('Y-m-d H:i:s');
        // Save issue link in database
        $DB->insert('glpi_plugin_gitlab_tickets', [
            'tickets_id'         => $ticketId,
            'gitlab_project_id'  => $projectId,
            'gitlab_issue_iid'   => (int)$issue['iid'],
            'gitlab_issue_id'    => (int)$issue['id'],
            'gitlab_issue_url'   => $issue['web_url'] ?? '',
            'gitlab_issue_title' => $issue['title'] ?? $title,
            'date_creation'      => $now
        ]);

        // Add a follow-up (suivi) in the GLPI ticket for audit trail
        $followup = new ITILFollowup();
        $followup->add([
            'itemtype'  => 'Ticket',
            'items_id'  => $ticketId,
            'is_private'=> 0,
            'content'   => sprintf(
                __('Issue GitLab #%d créée sur le projet <code>%s</code> : <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', 'gitlab'),
                $issue['iid'],
                htmlspecialchars($projectId),
                htmlspecialchars($issue['web_url']),
                htmlspecialchars($issue['title'])
            )
        ]);

        Session::addMessageAfterRedirect(
            sprintf(__('Issue GitLab #%d créée avec succès !', 'gitlab'), $issue['iid']),
            false,
            INFO
        );
    } else {
        Session::addMessageAfterRedirect(
            sprintf(__('Erreur lors de la création de l\'issue GitLab : %s', 'gitlab'), $res['error'] ?? __('Erreur inconnue', 'gitlab')),
            false,
            ERROR
        );
    }

    Html::redirect($backUrl);
} elseif ($action === 'unlink_issue') {
    global $DB;
    $linkId = (int)($_POST['link_id'] ?? 0);

    if ($linkId > 0) {
        $DB->delete('glpi_plugin_gitlab_tickets', [
            'id'         => $linkId,
            'tickets_id' => $ticketId
        ]);
        Session::addMessageAfterRedirect(__('Liaison avec l\'issue GitLab supprimée.', 'gitlab'), false, INFO);
    }

    Html::redirect($backUrl);
} else {
    Html::redirect($backUrl);
}
