<?php

/**
 * Client library for Sharedown project.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 * @link https://github.com/lokhman/sharedown-php
 */
class Sharedown {

    protected $host;
    protected $scheme;
    protected $login;
    protected $password;

    protected $session;
    protected $level;

    protected static $SCHEMES = [
        'https' => ['ssl://', 443],
        'http'  => ['', 80],
    ];

    /**
     * Sharedown client constructor.
     *
     * @param string $server    Backend server URL, e.g. <b>"https://share.example.com:8080"</b>.
     *
     * @throws InvalidArgumentException
     */
    public function __construct($server) {
        $url = parse_url($server);
        if (!isset($url['host']) || !isset($url['scheme']) || !isset(self::$SCHEMES[$url['scheme']])) {
            throw new InvalidArgumentException('Server parameter must be valid URL.');
        }

        $this->host = $url['host'];
        $this->scheme = self::$SCHEMES[$url['scheme']];
        if (isset($url['port'])) {
            $this->scheme[1] = $url['port'];
        }
    }

    /**
     * Performs memory-safe request to the server API.
     *
     * @param string $method    Request method, e.g. <b>"GET"</b>, <b>"POST"</b>, <b>"PATCH"</b>, etc.
     * @param string $uri       Request URI, e.g. <b>"/files"</b>.
     * @param mixed $data       [optional] <p>Data to pass with the request.</p>
     * @param bool $isStateful  [optional] <p>If the request is stateful.</p>
     * @param array $params     [optional] <p>Additional query string parameters.</p>
     * @param array $headers    [optional] <p>Additional headers.</p>
     *
     * @return mixed            JSON decoded data, usually an array of values.
     *
     * @throws SharedownException
     */
    protected function request($method, $uri, $data = null, $isStateful = false, array $params = [], array $headers = []) {
        $fp = @fsockopen($this->scheme[0] . $this->host, $this->scheme[1], $errno, $errstr);
        if ($fp === false) {
            throw new SharedownException($errstr, $errno);
        }

        if ($params) {
            $uri .= '?' . http_build_query($params);
        }

        if (is_array($data)) {
            $data = http_build_query($data);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $headers['Content-Length'] = strlen($data);
        } elseif ($data instanceof SplFileInfo) {
            $headers['Content-Length'] = $data->getSize();
        }

        if ($isStateful && $this->session) {
            $headers['X-API-Key'] = $this->session;
        }

        $headers['Host'] = $this->host;
        $headers['Connection'] = 'close';

        fwrite($fp, sprintf("%s /api%s HTTP/1.1\r\n", $method, $uri));
        foreach ($headers as $header => $value) {
            fwrite($fp, $header . ': ' . $value . "\r\n");
        }
        fwrite($fp, "\r\n");

        if ($data instanceof SplFileInfo) {
            $handle = fopen($data->getPathname(), 'rb');
            while ($chunk = fread($handle, 4096)) {
                fwrite($fp, $chunk);
            }
            fclose($handle);
        } else {
            fwrite($fp, $data);
        }

        $response = '';
        while (!feof($fp)) {
            $response .= fread($fp, 1024);
        }
        fclose($fp);

        if (false === $pos = strpos($response, "\r\n\r\n")) {
            throw new SharedownException('Bad server response body.');
        }

        $headers = explode("\r\n", substr($response, 0, $pos));
        if (!isset($headers[0]) || strpos($headers[0], 'HTTP/1.1 ')) {
            throw new SharedownException('Bad server response headers.');
        }
        if ($headers[0] != 'HTTP/1.1 200 OK') {
            throw new SharedownException($headers[0]);
        }

        $contents = @json_decode(substr($response, $pos + 4), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SharedownException('Response JSON format is invalid.');
        }
        return $contents;
    }

    /**
     * Performs GET request to the server API.
     *
     * @param string $uri       Request URI, e.g. <b>"/files"</b>.
     * @param bool $isStateful  [optional] <p>If the request is stateful.</p>
     * @param array $params     [optional] <p>Additional query string parameters.</p>
     * @param array $headers    [optional] <p>Additional headers.</p>
     *
     * @return mixed            JSON decoded data, usually an array of values.
     *
     * @throws SharedownException
     */
    protected function get($uri, $isStateful = false, array $params = [], array $headers = []) {
        return $this->request('GET', $uri, null, $isStateful, $params , $headers);
    }

    /**
     * Performs POST request to the server API.
     *
     * @param string $uri       Request URI, e.g. <b>"/files"</b>.
     * @param mixed $data       [optional] <p>Data to pass with the request.</p>
     * @param bool $isStateful  [optional] <p>If the request is stateful.</p>
     * @param array $params     [optional] <p>Additional query string parameters.</p>
     * @param array $headers    [optional] <p>Additional headers.</p>
     *
     * @return mixed            JSON decoded data, usually an array of values.
     *
     * @throws SharedownException
     */
    protected function post($uri, $data, $isStateful = false, array $params = [], array $headers = []) {
        return $this->request('POST', $uri, $data, $isStateful, $params, $headers);
    }

    /**
     * Performs PATCH request to the server API.
     *
     * @param string $uri       Request URI, e.g. <b>"/files"</b>.
     * @param mixed $data       [optional] <p>Data to pass with the request.</p>
     * @param bool $isStateful  [optional] <p>If the request is stateful.</p>
     * @param array $params     [optional] <p>Additional query string parameters.</p>
     * @param array $headers    [optional] <p>Additional headers.</p>
     *
     * @return mixed            JSON decoded data, usually an array of values.
     *
     * @throws SharedownException
     */
    protected function patch($uri, $data, $isStateful = false, array $params = [], array $headers = []) {
        return $this->request('PATCH', $uri, $data, $isStateful, $params, $headers);
    }

    /**
     * Performs DELETE request to the server API.
     *
     * @param string $uri       Request URI, e.g. <b>"/files"</b>.
     * @param bool $isStateful  [optional] <p>If the request is stateful.</p>
     * @param array $params     [optional] <p>Additional query string parameters.</p>
     * @param array $headers    [optional] <p>Additional headers.</p>
     *
     * @return mixed            JSON decoded data, usually an array of values.
     *
     * @throws SharedownException
     */
    protected function delete($uri, $isStateful = false, array $params = [], array $headers = []) {
        return $this->request('DELETE', $uri, null, $isStateful, $params , $headers);
    }

    /**
     * Returns current user access level.
     *
     * @return string|null  User access level, e.g. <b>"USER"</b>, <b>"ADMIN"</b>, etc.
     */
    public function getLevel() {
        return $this->level;
    }

    /**
     * Returns current server version number.
     *
     * @return string   Server version number, e.g. <b>"0.1"</b>.
     *
     * @throws SharedownException
     */
    public function getServerVersion() {
        return $this->get('/')['version'];
    }

    /**
     * Checks if user is authenticated.
     *
     * @return boolean
     *
     * @throws SharedownException
     */
    public function isAuthenticated() {
        try {
            $this->get('/auth', true);
            return true;
        } catch (SharedownException $ex) {
            if ($ex->getStatusCode() == 403) {
                return false;
            }
            throw $ex;
        }
    }

    /**
     * Logs in user by the given credentials.
     *
     * @param string $login     User username to log in.
     * @param string $password  User password to log in.
     *
     * @return string           Current auth session.
     *
     * @throws SharedownException
     */
    public function login($login, $password) {
        $response = $this->post('/auth/login', [
            'login' => $login,
            'password' => $password,
        ]);

        $this->login = $login;
        $this->password = $password;
        $this->session = $response['session'];
        $this->level = $response['level'];

        return $this->session;
    }

    /**
     * Attemts to log out user.
     *
     * @throws SharedownException
     */
    public function logout() {
        try {
            $this->get('/auth/logout', true);
            $this->level = null;
        } catch (SharedownException $ex) {
            if ($ex->getStatusCode() != 403) {
                throw $ex;
            }
        }
    }

    /**
     * Uploads the given file to the server.
     *
     * @param SplFileInfo|string $file  File to upload to the server.
     * @param string|null $folder       [optional] <p>Folder to group the uploaded file.</p>
     * @param string|null $caption      [optional] <p>Alternative file name.</p>
     * @param string|null $password     [optional] <p>Password to secure file download.</p>
     * @param bool $isPermanent         [optional] <p><b>TRUE</b> if file will have no expiry date.</p>
     * @param bool $isPublic            [optional] <p><b>TRUE</b> if file will be displayed in the feed.</p>
     * @param string|null $contentType  [optional] <p>Advisory file MIME content type.</p>
     *
     * @return string                   The key of the uploaded file.
     *
     * @throws SharedownException
     */
    public function upload($file, $folder = null, $caption = null, $password = null, $isPermanent = false, $isPublic = false, $contentType = null) {
        if (!$file instanceof SplFileInfo) {
            $file = new SplFileInfo($file);
        }

        if (!$file->isFile() || !$file->isReadable()) {
            throw new SharedownException('File is not readable.');
        }

        $headers = ['X-SF-Name' => $file->getBasename()];
        if (is_string($folder) && $folder) {
            $headers['X-SF-Folder'] = $folder;
        }
        if (is_string($caption) && $caption) {
            $headers['X-SF-Caption'] = $caption;
        }
        if (is_string($password) && $password) {
            $headers['X-SF-Password'] = $password;
        }
        if ($isPublic) {
            $headers['X-SF-Public'] = '1';
        }
        if ($isPermanent) {
            $headers['X-SF-Permanent'] = '1';
        }
        if (is_string($contentType) && $contentType) {
            $headers['Content-Type'] = $contentType;
        }
        return $this->post('/upload', $file, true, [], $headers)['key'];
    }

    /**
     * Returns list of files for the current user.
     *
     * @param array $params [optional] <p>Additional search parameters, e.g. <b>login</b>, <b>search</b>, <b>folder</b>, etc.</p>
     *
     * @return array        Array of file details arrays.
     *
     * @throws SharedownException
     */
    public function getFiles(array $params = []) {
        return $this->get('/files', true, $params)['files'];
    }

    /**
     * Returns file details by the given key.
     *
     * @param string $key   Key of the file.
     *
     * @return array        File details array.
     *
     * @throws SharedownException
     */
    public function getFile($key) {
        return $this->get('/files/' . $key, true);
    }

    /**
     * Updates the file details.
     *
     * @param string $key   Key of the file.
     * @param array $file   File details array with <b>password</b>, <b>caption</b>, and/or <b>folder</b> keys.
     *
     * @throws SharedownException
     */
    public function setFile($key, array $file) {
        $this->patch('/files/' . $key, array_intersect_key($file, [
            'password' => null,
            'caption' => null,
            'folder' => null,
        ]), true);
    }

    /**
     * Removes the file by the given key.
     *
     * @param string $key   Key of the file.
     *
     * @throws SharedownException
     */
    public function removeFile($key) {
        $this->delete('/files/' . $key, true);
    }

    /**
     * Returns the feed of the current user.
     *
     * @return array    Feed details array.
     *
     * @throws SharedownException
     */
    public function getFeed() {
        return $this->get('/feed', true);
    }

    /**
     * Updates the feed details.
     *
     * @param array $feed   Feed details array with <b>password</b>, <b>title</b>, <b>readme</b>, and <b>is_enabled</b> keys.
     *
     * @throws SharedownException
     */
    public function setFeed(array $feed) {
        $this->post('/feed', array_intersect_key($feed, [
            'password' => null,
            'title' => null,
            'readme' => null,
            'is_enabled' => null,
        ]), true);
    }

    /**
     * Returns list of users.
     *
     * @return array    Array of user details arrays.
     *
     * @throws SharedownException
     */
    public function getUsers() {
        return $this->get('/users', true);
    }

    /**
     * Returns user details by the given login.
     *
     * @param string $login Login of the user.
     *
     * @return array        User details array.
     *
     * @throws SharedownException
     */
    public function getUser($login) {
        return $this->get('/users/' . $login, true);
    }

    /**
     * Updates the user details.
     *
     * @param string $login Login of the user.
     * @param array $user   User details array with <b>login</b>, <b>level</b>, <b>password</b>, and/or <b>is_enabled</b> keys.
     *
     * @throws SharedownException
     */
    public function setUser($login, array $user) {
        $this->patch('/users/' . $login, array_intersect_key($user, [
            'login' => null,
            'level' => null,
            'password' => null,
            'is_enabled' => null,
        ]), true);
    }

    /**
     * Adds new user.
     *
     * @param array $user   User details array with <b>login</b>, <b>level</b>, <b>password</b>, and <b>is_enabled</b> keys.
     *
     * @throws SharedownException
     */
    public function addUser(array $user) {
        $this->post('/users', array_intersect_key($user, [
            'login' => null,
            'level' => null,
            'password' => null,
            'is_enabled' => null,
        ]), true);
    }

    /**
     * Removes the user by the given login.
     *
     * @param string $login Login of the user.
     *
     * @throws SharedownException
     */
    public function removeUser($login) {
        $this->delete('/users/' . $login, true);
    }

}
