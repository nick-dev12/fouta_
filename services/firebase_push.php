<?php
/**
 * Service d'envoi de notifications push via Firebase Cloud Messaging (FCM)
 * Utilise kreait/firebase-php si disponible (composer install), sinon implémentation native
 */

/**
 * Retourne la configuration Firebase serveur
 */
function _firebase_get_config() {
    $config_path = __DIR__ . '/../config/firebase_server.php';
    if (file_exists($config_path)) {
        $config = require $config_path;
        $config['credentials_path'] = $config['credentials_path'] ?? __DIR__ . '/../sugar-paper-d34851eeca5a.json';
        return $config;
    }
    return ['credentials_path' => __DIR__ . '/../sugar-paper-d34851eeca5a.json'];
}

/**
 * Configure les certificats SSL pour corriger l'erreur cURL 60 (Windows/WAMP)
 */
function _firebase_configure_ssl() {
    $config = _firebase_get_config();
    $cacert = $config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
    if (file_exists($cacert)) {
        $path = realpath($cacert);
        putenv('SSL_CERT_FILE=' . $path);
        putenv('CURL_CA_BUNDLE=' . $path);
    }
}

/**
 * Envoie une notification push FCM via kreait/firebase-php (si installé)
 * Retourne null en cas d'erreur de dépendances (ex: PSR Cache) pour déclencher le fallback natif
 */
function _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data) {
    _firebase_configure_ssl();
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }

    try {
        require_once $autoload;
    } catch (\Throwable $e) {
        return null;
    }
    if (!class_exists('Kreait\Firebase\Factory')) {
        return null;
    }

    try {
        $config = _firebase_get_config();
        $cacert = $config['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($credentials_path);
        if (file_exists($cacert)) {
            $httpOptions = \Kreait\Firebase\Http\HttpClientOptions::default()
                ->withGuzzleConfigOption('verify', realpath($cacert));
            $factory = $factory->withHttpClientOptions($httpOptions);
        }
        $messaging = $factory->createMessaging();

        $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);
        $dataPayload = array_merge($data, ['title' => $title, 'body' => $body]);
        foreach ($dataPayload as $k => $v) {
            $dataPayload[$k] = (string) $v;
        }

        $success = 0;
        $errors = [];

        foreach ($tokens as $token) {
            try {
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($dataPayload);
                $link = $data['link'] ?? '/';
                if (!empty($link)) {
                    $message = $message->withWebPushConfig(\Kreait\Firebase\Messaging\WebPushConfig::fromArray([
                        'fcm_options' => ['link' => (string) $link]
                    ]));
                }
                $messaging->send($message);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => count($tokens) - $success,
            'errors' => $errors
        ];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        // Erreurs de dépendances (PSR Cache, etc.) : basculer vers l'implémentation native
        if (stripos($msg, 'CacheItemPoolInterface') !== false
            || stripos($msg, 'Interface') !== false && stripos($msg, 'not found') !== false
            || stripos($msg, 'Class') !== false && stripos($msg, 'not found') !== false) {
            return null;
        }
        return ['success' => 0, 'failed' => count($tokens), 'errors' => [$msg]];
    }
}

/**
 * Implémentation native (fallback sans Composer)
 */
function _fcm_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _firebase_get_project_id($credentials_path) {
    if (!file_exists($credentials_path)) {
        return 'sugar-paper';
    }
    $credentials = json_decode(file_get_contents($credentials_path), true);
    return $credentials['project_id'] ?? 'sugar-paper';
}

function firebase_get_access_token($credentials_path) {
    if (!file_exists($credentials_path)) {
        return null;
    }
    $credentials = json_decode(file_get_contents($credentials_path), true);
    if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
        return null;
    }
    $now = time();
    $payload = [
        'iss' => $credentials['client_email'],
        'sub' => $credentials['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ];
    $header = _fcm_base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payloadEnc = _fcm_base64url_encode(json_encode($payload));
    $signatureInput = $header . '.' . $payloadEnc;
    $privateKey = openssl_pkey_get_private($credentials['private_key']);
    if (!$privateKey) {
        return null;
    }
    openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $signature = _fcm_base64url_encode($signature);
    $jwt = $signatureInput . '.' . $signature;
    $data = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);
    $opts = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => $data
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if (!$result) {
        return null;
    }
    $response = json_decode($result, true);
    return $response['access_token'] ?? null;
}

function _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data) {
    $access_token = firebase_get_access_token($credentials_path);
    if (!$access_token) {
        return ['success' => 0, 'failed' => count($tokens), 'errors' => ['Impossible d\'obtenir le token d\'accès']];
    }
    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $success = 0;
    $errors = [];
    foreach ($tokens as $token) {
        $dataPayload = [];
        foreach (array_merge($data, ['title' => $title, 'body' => $body]) as $k => $v) {
            $dataPayload[$k] = (string) $v;
        }
        $message = [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $dataPayload,
                'webpush' => [
                    'fcm_options' => ['link' => isset($data['link']) ? (string) $data['link'] : '/']
                ]
            ]
        ];
        $opts = [
            'http' => [
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$access_token}\r\n",
                'method' => 'POST',
                'content' => json_encode($message)
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) {
            $response = json_decode($result, true);
            if (isset($response['name'])) {
                $success++;
            } else {
                $errors[] = $response['error']['message'] ?? 'Erreur inconnue';
            }
        } else {
            $errors[] = 'Échec de la requête HTTP';
        }
    }
    return [
        'success' => $success,
        'failed' => count($tokens) - $success,
        'errors' => $errors
    ];
}

/**
 * Envoie une notification push FCM à un ou plusieurs tokens
 * @param array $tokens Liste des tokens FCM
 * @param string $title Titre de la notification
 * @param string $body Corps du message
 * @param array $data Données additionnelles (optionnel)
 * @return array ['success' => int, 'failed' => int, 'errors' => array]
 */
function firebase_send_notification($tokens, $title, $body, $data = []) {
    if (empty($tokens)) {
        return ['success' => 0, 'failed' => 0, 'errors' => []];
    }
    $config = _firebase_get_config();
    $credentials_path = $config['credentials_path'];
    $project_id = _firebase_get_project_id($credentials_path);

    $result = _firebase_send_via_library($credentials_path, $tokens, $title, $body, $data);
    if ($result !== null) {
        return $result;
    }
    return _firebase_send_native($credentials_path, $project_id, $tokens, $title, $body, $data);
}
