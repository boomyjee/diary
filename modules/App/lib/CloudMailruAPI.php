<?php

class CloudMailruAPI {
    
    const AUTH_DOMAIN = 'https://auth.mail.ru';
    const CLOUD_DOMAIN = 'https://cloud.mail.ru';
    
    private $login;
    private $password;
    private $http;
    private $token;
    private $uploadUrl;
    private $downloadUrl;
    private $videoUrl;
    private $videoThumbUrl;
    
    public function __construct($login, $password) {
        $this->login = $login;
        $this->password = $password;
        $this->http = new \GuzzleHttp\Client(['cookies' => new GuzzleHttp\Cookie\SessionCookieJar('SESSION_STORAGE', true), 'timeout' => 20, 'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.87 Safari/537.36'
        ],'debug' => false]);
    }
    
    public function getFiles($folder = '') {
        $response = $this->executeMethod('folder', 'get', [
            'home' => '/'.$folder,
            'sort' => '{"type":"mtime","order":"desc"}'
        ]);
        
        $files = [];
        if (!empty($response['list'])) {
            foreach ($response['list'] as $item) {
                if ($item['type'] === 'file') {
                    $files[] = $item;
                }
            }
        }
        return $files;
    }
    
    public function loadFile($filePath, $fileName, $destinationFolder = '') {
        $response = $this->http->post($this->getUploadUrl(), [
            'query' => [
                'cloud_domain' => 2,
                'fileapi'.time() => ''
            ], 
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName
                ]
            ]
        ]);
        
        $fileData = explode(';', (string)$response->getBody());
        return $this->executeMethod('file/add', 'post', [
            'home' => $destinationFolder.'/'.$fileName,
            'hash' => $fileData[0],
            'size' => intval($fileData[1]),
            'conflict' => 'rewrite',
            'api' => 2
        ]);
    }
    
    public function moveFile($filePath, $destinationFolder) {
        return $this->executeMethod('file/move', 'post', [
            'folder' => '/'.$destinationFolder,
            'home' => $filePath,
            'conflict' => 'rename',
            'api' => 2
        ]);
    }
    
    public function removeFile($filePath) {
        return $this->executeMethod('file/remove', 'post', [
            'home' => $filePath,
            'api' => 2
        ]);
    }
    
    public function getFileContent($filePath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        return (string)$this->http->get($this->getDownloadUrl().implode('/', array_map('rawurlencode', explode('/', $filePath))))->getBody();
    }
    
    public function getVideoBitrates($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        $videoPath = base64_encode(implode('/', array_map('rawurlencode', explode('/', $videoPath))));
        return (string)$this->http->get($this->getVideoUrl().'0p/'.$videoPath.'.m3u8?double_encode=1')->getBody();
    }
    
    public function getVideo($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        return (string)$this->http->get(str_replace('/video/', '', $this->getVideoUrl()).'/'.$videoPath)->getBody();
    }
    
    public function getVideoThumb($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        return (string)$this->http->get($this->getVideoThumbUrl().'/vxw0/'.implode('/', array_map('rawurlencode', explode('/', $videoPath))))->getBody();
    }
    
    
    private function executeMethod($method, $requestType = 'get', array $params = []) {
        if ($method !== 'tokens/csrf') {
            $params['token'] = $this->getToken();
        }
        
        $url = static::CLOUD_DOMAIN . '/api/v2/' . $method;
        if ($requestType == 'get') {
            $content = $this->http->get($url, [
                'query' => $params,
                'http_errors' => false,
            ])->getBody();
        } else {
            $content = $this->http->post($url, [
                'form_params' => $params
            ])->getBody();
        }
        
        $response = json_decode((string)$content, true);
        if (!$response && json_last_error() !== JSON_ERROR_NONE) {
            trigger_error('Cloud Mailru API response was not parsed');
            return false;
        }
        
        if (!$response) $response = [];
        $status = array_key_exists('status', $response) ? (int)$response['status'] : 0;
        if (!array_key_exists('body', $response)) {
            trigger_error('Empty body in Cloud Mailru API response');
            return false;
        }
        
        $body = $response['body'];
        if ($status === 403) {
            switch ($body) {
                case 'user':
                    trigger_error('Cloud Mailru API response: authentication required');
                case 'nosdc':
                    trigger_error('Cloud Mailru API response: no SDC cookie');
                case 'token':
                    trigger_error('Cloud Mailru API response: invalid token');
                default:
                    trigger_error('Cloud Mailru API response: '.$body);
            }
            return false;
        }
        
        return $response['body'];
    }
    
    private function getToken() {
        if (!$this->token) {
            $this->authenticate();
            $this->ensureSdcCookie();
            $response = $this->executeMethod('tokens/csrf');
            $this->token = isset($response['token']) ? $response['token'] : null;
        }
        return $this->token;
    }
    
    private function authenticate() {
        $redirectUrl = '';
        $response = $this->http->get(static::AUTH_DOMAIN . '/cgi-bin/auth?from=splash', ['on_stats' => function ($stats) use (&$redirectUrl) {
            $redirectUrl = $stats->getEffectiveUri();
        }]);
        
        if ($redirectUrl != 'https://e.mail.ru/messages/inbox/') {
            $response = $this->http->post(static::AUTH_DOMAIN . '/cgi-bin/auth?from=splash', [
                'form_params' => [
                    'Domain' => 'mail.ru',
                    'Login' => $this->login,
                    'Password' => $this->password,
                    'new_auth_form' => 1,
                    'FromAccount' => 1,
                    'saveauth' => 1
                ],
                'on_stats' => function ($stats) use (&$redirectUrl) {
                    $redirectUrl = $stats->getEffectiveUri();
                }
            ]);
            
            if (strpos($redirectUrl, 'https://e.mail.ru/messages') === false) {
                trigger_error('Cloud Mailru API: wrong authentication result '.$redirectUrl);
                return false;
            }
        }
        return true;
    }
    
    private function ensureSdcCookie() {
        $this->http->get(static::AUTH_DOMAIN . '/sdc', [
            'query' => [
                'from' => static::CLOUD_DOMAIN . '/home',
            ]
        ]);
    }
    
    private function getUploadUrl() {
        if (!$this->uploadUrl) {
            $response = $this->executeMethod('dispatcher');
            $nodes = array_column($response['upload'], 'url');
            $this->uploadUrl = $nodes[mt_rand(0, count($nodes) - 1)];
        }
        
        return $this->uploadUrl;
    }
    
    private function getDownloadUrl() {
        if (!$this->downloadUrl) {
            $response = $this->executeMethod('dispatcher');
            $nodes = array_column($response['get'], 'url');
            $this->downloadUrl = $nodes[mt_rand(0, count($nodes) - 1)];
        }
        return $this->downloadUrl;
    }
    
    public function getVideoUrl() {
        if (!$this->videoUrl) {
            $response = $this->executeMethod('dispatcher');
            $nodes = array_column($response['video'], 'url');
            $this->videoUrl = $nodes[mt_rand(0, count($nodes) - 1)];
        }
        return $this->videoUrl;
    }
    
    public function getVideoThumbUrl() {
        if (!$this->videoThumbUrl) {
            $response = $this->executeMethod('dispatcher');
            $nodes = array_column($response['thumbnails'], 'url');
            $this->videoThumbUrl = $nodes[mt_rand(0, count($nodes) - 1)];
        }
        return $this->videoThumbUrl;
    }
}