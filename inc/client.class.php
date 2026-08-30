<?php
/**
 * Plugin GitLab for GLPI - GitLab API Client
 *
 * @author  Antigravity
 * @license MIT
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGitlabClient {
    private string $baseUrl;
    private string $token;

    public function __construct(?string $baseUrl = null, ?string $token = null) {
        if ($baseUrl === null || $token === null) {
            $config = PluginGitlabConfig::getConfig();
            $this->baseUrl = rtrim($config['gitlab_url'] ?? 'https://gitlab.com', '/');
            $this->token   = $config['gitlab_token'] ?? '';
        } else {
            $this->baseUrl = rtrim($baseUrl, '/');
            $this->token   = $token;
        }
    }

    /**
     * Test connection to GitLab API
     *
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function testConnection(): array {
        if (empty($this->token)) {
            return [
                'success' => false,
                'message' => __('Token GitLab manquant.', 'gitlab'),
                'user'    => null
            ];
        }

        $res = $this->request('GET', '/api/v4/user');
        if ($res['http_code'] === 200 && is_array($res['body'])) {
            return [
                'success' => true,
                'message' => sprintf(__('Connexion réussie en tant que : %s (@%s)', 'gitlab'), $res['body']['name'] ?? '', $res['body']['username'] ?? ''),
                'user'    => $res['body']
            ];
        }

        $errorMsg = $res['body']['message'] ?? ($res['error'] ?: 'Code HTTP: ' . $res['http_code']);
        return [
            'success' => false,
            'message' => sprintf(__('Échec de connexion : %s', 'gitlab'), is_array($errorMsg) ? json_encode($errorMsg) : $errorMsg),
            'user'    => null
        ];
    }

    /**
     * Get project details
     *
     * @param string|int $projectId
     * @return array|null
     */
    public function getProject($projectId): ?array {
        $encodedId = urlencode((string)$projectId);
        $res = $this->request('GET', "/api/v4/projects/{$encodedId}");
        if ($res['http_code'] === 200 && is_array($res['body'])) {
            return $res['body'];
        }
        return null;
    }

    /**
     * Get an issue by project and IID
     *
     * @param string|int $projectId
     * @param int $issueIid
     * @return array|null
     */
    public function getIssue($projectId, int $issueIid): ?array {
        $encodedId = urlencode((string)$projectId);
        $res = $this->request('GET', "/api/v4/projects/{$encodedId}/issues/{$issueIid}");
        if ($res['http_code'] === 200 && is_array($res['body'])) {
            return $res['body'];
        }
        return null;
    }

    /**
     * Create a new issue in GitLab
     *
     * @param string|int $projectId
     * @param string $title
     * @param string $description
     * @param string|array $labels
     * @return array ['success' => bool, 'issue' => array|null, 'error' => string|null]
     */
    public function createIssue($projectId, string $title, string $description, $labels = ''): array {
        $encodedId = urlencode((string)$projectId);

        if (is_array($labels)) {
            $labels = implode(',', $labels);
        }

        $payload = [
            'title'       => $title,
            'description' => $description,
            'labels'      => $labels,
        ];

        $res = $this->request('POST', "/api/v4/projects/{$encodedId}/issues", $payload);

        if ($res['http_code'] === 201 && is_array($res['body'])) {
            return [
                'success' => true,
                'issue'   => $res['body'],
                'error'   => null
            ];
        }

        $errorDetail = '';
        if (isset($res['body']['message'])) {
            $errorDetail = is_array($res['body']['message']) ? json_encode($res['body']['message']) : $res['body']['message'];
        } elseif (!empty($res['error'])) {
            $errorDetail = $res['error'];
        } else {
            $errorDetail = 'HTTP ' . $res['http_code'];
        }

        return [
            'success' => false,
            'issue'   => null,
            'error'   => $errorDetail
        ];
    }

    /**
     * Perform HTTP request to GitLab API
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $endpoint API path
     * @param array $data Data for POST/PUT or query params
     * @return array ['http_code' => int, 'body' => array|string, 'error' => string|null]
     */
    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $headers = [
            'PRIVATE-TOKEN: ' . $this->token,
            'Accept: application/json',
        ];

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif (in_array($method, ['PUT', 'DELETE', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $rawResponse = curl_exec($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'http_code' => $httpCode,
                'body'      => null,
                'error'     => $curlError
            ];
        }

        $decoded = json_decode($rawResponse, true);
        return [
            'http_code' => $httpCode,
            'body'      => $decoded !== null ? $decoded : $rawResponse,
            'error'     => null
        ];
    }
}
