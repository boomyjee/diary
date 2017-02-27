<?php

class CloudMailruAPI {
    
    const AUTH_DOMAIN = 'https://auth.mail.ru';
    const CLOUD_DOMAIN = 'https://cloud.mail.ru';
    
    private $login;
    private $password;
    private $curl;
    private $token;
    private $dispatcher;
    
    public function __construct($login, $password) {
        $this->login = $login;
        $this->password = $password;
        $this->curl = curl_init();
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
        $response = $this->request($this->getUploadUrl() . '?' . http_build_query([ 'cloud_domain' => 2, 'fileapi'.time() => '']), [
            'uploadData' => [
                'filepath' => $filePath,
                'filename' => $fileName
            ]
        ]);
        
        $fileData = explode(';', $response);
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

        $maxRedir = 5; $redirCount = 0; 
        $url = $this->getDownloadUrl().implode('/', array_map('rawurlencode', explode('/', $filePath)));
        $response = false;

        $fh = fopen('php://temp', 'w+');
        do {
            ftruncate($fh, 0);
            rewind($fh);
            $response = $this->request($url, ['filehandle' => $fh]);
            if (strpos($response, 'redirect') !== false)
                $url = str_replace('redirect: ', '', $response);
        } while (strpos($response, 'redirect') !== false && ++$redirCount <= $maxRedir);
       
        rewind($fh);
        $result = stream_get_contents($fh);
        fclose($fh);
        return $result;
    }
    
    public function getVideoBitrates($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        $videoPath = base64_encode(implode('/', array_map('rawurlencode', explode('/', $videoPath))));
        return $this->request($this->getVideoUrl().'0p/'.$videoPath.'.m3u8?double_encode=1');
    }
    
    public function getVideo($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();
        return $this->request(str_replace('/video/', '', $this->getVideoUrl()).'/'.$videoPath);
    }
    
    public function getVideoThumb($videoPath) {
        $this->authenticate();
        $this->ensureSdcCookie();

        $fh = fopen('php://temp', 'w+');
        $response = $this->request($this->getVideoThumbUrl().'vxw0/'.implode('/', array_map('rawurlencode', explode('/', $videoPath))), ['filehandle' => $fh]);
        rewind($fh);
        $result = stream_get_contents($fh);
        fclose($fh); 
        return $result;
    }
    
    
    private function executeMethod($method, $requestType = 'get', array $params = []) {
        if ($method !== 'tokens/csrf') {
            $params['token'] = $this->getToken();
        }
        
        $url = static::CLOUD_DOMAIN . '/api/v2/' . $method;
        if ($requestType == 'get') {
            $url .= '?' . http_build_query($params);
            $content = $this->request($url);
        } else {
            $content = $this->request($url, ['postData' => $params]);
        }
        
        $response = json_decode($content, true);
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
        $response = $this->request(static::AUTH_DOMAIN . '/cgi-bin/auth?from=splash');
        
        if ($response != 'redirect: https://e.mail.ru/messages/inbox/') {
            $response = $this->request(static::AUTH_DOMAIN . '/cgi-bin/auth?from=splash', [
                'referer' => 'https://mail.ru/',
                'postData' => [
                    'Domain' => 'mail.ru',
                    'Login' => $this->login,
                    'Password' => $this->password,
                    'new_auth_form' => 1,
                    'FromAccount' => 1,
                    'saveauth' => 1
                ]
            ]);
            
            if (strpos($response, 'https://e.mail.ru/messages') === false) {
                trigger_error('Cloud Mailru API: wrong authentication result '.$response);
                return false;
            }
        }
        return true;
    }
    
    private function ensureSdcCookie() {
        $response = $this->request(static::AUTH_DOMAIN . '/sdc?'.http_build_query(['from' => static::CLOUD_DOMAIN . '/home']));
        if (strpos($response, 'redirect') !== false)
            $this->request(str_replace('redirect: ', '', $response));
    }
    
    private function getDispatcher() {
        if (!$this->dispatcher)
            $this->dispatcher = $this->executeMethod('dispatcher');
        return $this->dispatcher;
    }

    public function getUploadUrl() {
        $nodes = array_column($this->getDispatcher()['upload'], 'url');
        return $nodes[mt_rand(0, count($nodes) - 1)];
    }
    
    public function getDownloadUrl() {
        $nodes = array_column($this->getDispatcher()['get'], 'url');
        return $nodes[mt_rand(0, count($nodes) - 1)];
    }
    
    public function getVideoUrl() {
        $nodes = array_column($this->getDispatcher()['video'], 'url');
        return $nodes[mt_rand(0, count($nodes) - 1)];
    }
    
    public function getVideoThumbUrl() {
        $nodes = array_column($this->getDispatcher()['thumbnails'], 'url');
        return $nodes[mt_rand(0, count($nodes) - 1)];
    }

    private function request($url, $options = []) {
        curl_reset($this->curl); 

        $userAgent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
        $connectTimeout = isset($options['connectTimeout']) ? $options['connectTimeout'] : 60;
 
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_HEADER, 1);
        curl_setopt($this->curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $connectTimeout);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);

        if (isset($options['postData'])) {
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($options['postData']));
        } else {
            curl_setopt($this->curl, CURLOPT_POST, 0);
        }

        if (isset($options['uploadData'])) {
            $filename = isset($options['uploadData']['filename']) ? $options['uploadData']['filename'] : basename($options['uploadData']['filepath']);
            $cFile = new \CURLFile($options['uploadData']['filepath'], '', $filename);
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, ['file' => $cFile]);
            array_merge(isset($options['header']) ? $options['header'] : [], ['Expect:', 'Accept-Encoding:', 'Content-Type: multipart/form-data']); 
        }

        if (isset($options['referer'])) {
            curl_setopt($this->curl, CURLOPT_REFERER, $options['referer']);
        }

        if (isset($options['header'])) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $options['header']);
        }

        if (isset($options['filehandle'])) {
            curl_setopt($this->curl, CURLOPT_FILE, $options['filehandle']);
            curl_setopt($this->curl, CURLOPT_HEADER, 0);
        }

        $cookiePath = APP_DIR . '/cache/temp_cookie.dat';
        if (!file_exists($cookiePath)) file_put_contents($cookiePath, '');
        curl_setopt($this->curl, CURLOPT_COOKIEJAR, $cookiePath);
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, $cookiePath);
        
        $result = curl_exec($this->curl);
        $response = curl_getinfo($this->curl);
        $error = curl_error($this->curl);
        
        if ($error) {           
            trigger_error("Mailru request error: ".$error." in ".$url);
            return false;
        }
         
        if (isset($response['http_code']) && ($response['http_code'] == 301 || $response['http_code'] == 302)) {
            return'redirect: ' . $response['redirect_url'];
        } else {
            return substr($result, curl_getinfo($this->curl, CURLINFO_HEADER_SIZE));
        }
    }
}