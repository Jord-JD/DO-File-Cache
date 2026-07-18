<?php

namespace JordJD\DOFileCache;

use Exception;

class DOFileCache
{
    /**
     * Cache configurations.
     *
     * @var string[]
     */
    protected $config = [
        'unixLoadUpperThreshold' => -1,
        'gzipCompression'        => true,
        'cacheDirectory'         => '/tmp/do-file-cache-storage/',
        'fileExtension'          => 'cache',
    ];

    /**
     * Change the configuration values.
     *
     * @param array $configArray
     *
     * @return bool
     */
    public function changeConfig($config)
    {
        if (!is_array($config)) {
            return false;
        }

        $this->config = array_merge($this->config, $config);

        return true;
    }

    /**
     * Sets an item in the cache.
     *
     * @param mixed $key
     * @param mixed $content
     * @param int   $expiry
     *
     * @return bool
     */
    public function set($key, $content, $expiry = 0)
    {
        $cacheObj = new \stdClass();

        $cacheObj->content = serialize($content);

        if (!$expiry) {
            // If no expiry specified, set to 'Never' expire timestamp (+10 years)
            $cacheObj->expiryTimestamp = time() + 315360000;
        } elseif ($expiry > 2592000) {
            // For value greater than 30 days, interpret as timestamp
            $cacheObj->expiryTimestamp = $expiry;
        } else {
            // Else, interpret as number of seconds
            $cacheObj->expiryTimestamp = time() + $expiry;
        }

        // Do not save if cache has already expired
        if ($cacheObj->expiryTimestamp < time()) {
            $this->delete($key);

            return false;
        }

        $cacheFileData = json_encode($cacheObj);

        if ($this->config['gzipCompression']) {
            $cacheFileData = gzcompress($cacheFileData);
        }

        $filePath = $this->getFilePathFromKey($key);
        $result = file_put_contents($filePath, $cacheFileData);
        clearstatcache(true, $filePath);

        return $result ? true : false;
    }

    /**
     * Returns a value from the cache.
     *
     * @param string $key
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function get($key)
    {
        $cacheObj = $this->readCacheObject($key);

        if ($cacheObj === null || !$this->isCacheObjectAvailable($cacheObj)) {
            return false;
        }

        return unserialize($cacheObj->content);
    }

    /**
     * Determine whether a non-expired cache item exists, including falsey values.
     *
     * @param string $key
     *
     * @throws Exception
     *
     * @return bool
     */
    public function has($key)
    {
        $cacheObj = $this->readCacheObject($key);

        return $cacheObj !== null && $this->isCacheObjectAvailable($cacheObj);
    }

    /**
     * Check if the string contains serialized data.
     *
     * @param string $string
     *
     * @return bool
     */
    public function isSerialized($string)
    {
        if (!is_string($string)) {
            return false;
        }

        if ($string === 'N;') {
            return true;
        }

        if (strlen($string) < 4) {
            return false;
        }

        if ($string[1] !== ':') {
            return false;
        }

        if (!in_array($string[0], ['s', 'a', 'O', 'b', 'i', 'd'])) {
            return false;
        }

        return $string === serialize(false) || @unserialize($string) !== false;
    }

    /**
     * Remove a value from the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key)
    {
        $filePath = $this->getFilePathFromKey($key);
        clearstatcache(true, $filePath);

        if (!file_exists($filePath)) {
            return false;
        }

        $result = unlink($filePath);
        clearstatcache(true, $filePath);

        return $result;
    }

    /**
     * Wipe out all cache values.
     *
     * @return bool
     */
    public function flush()
    {
        return $this->deleteDirectoryTree($this->config['cacheDirectory']);
    }

    /**
     * Removes cache files from a given directory.
     *
     * @param string $directory
     *
     * @return bool
     */
    private function deleteDirectoryTree($directory)
    {
        clearstatcache(true, $directory);

        if (!is_dir($directory)) {
            return true;
        }

        $filePaths = scandir($directory);

        foreach ($filePaths as $filePath) {
            if ($filePath == '.' || $filePath == '..') {
                continue;
            }

            $fullFilePath = $directory.'/'.$filePath;
            clearstatcache(true, $fullFilePath);
            if (is_dir($fullFilePath)) {
                $result = $this->deleteDirectoryTree($fullFilePath);
                if ($result) {
                    $result = rmdir($fullFilePath);
                }
            } else {
                if (basename($fullFilePath) == '.keep') {
                    continue;
                }
                $result = unlink($fullFilePath);
            }

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Increments a value within the cache.
     *
     * @param string $key
     * @param int    $offset
     *
     * @return bool
     */
    public function increment($key, $offset = 1)
    {
        $cacheObj = $this->readCacheObject($key);

        if ($cacheObj === null || !$this->isCacheObjectAvailable($cacheObj)) {
            return false;
        }

        $content = unserialize($cacheObj->content);

        if (!is_numeric($content)) {
            return false;
        }

        $content += $offset;

        return $this->set($key, $content, $cacheObj->expiryTimestamp);
    }

    /**
     * Decrements a value within the cache.
     *
     * @param string $key
     * @param int    $offset
     *
     * @return bool
     */
    public function decrement($key, $offset = 1)
    {
        return $this->increment($key, -$offset);
    }

    /**
     * Replaces a value within the cache.
     *
     * @param string $key
     * @param mixed  $content
     * @param int    $expiry
     *
     * @throws Exception
     *
     * @return bool
     */
    public function replace($key, $content, $expiry = 0)
    {
        if (!$this->has($key)) {
            return false;
        }

        return $this->set($key, $content, $expiry);
    }

    /**
     * Returns the file path from a given cache key, creating the relevant directory structure if necessary.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getFilePathFromKey($key)
    {
        $key = basename($key);
        $badChars = ['-', '.', '_', '\\', '*', '\"', '?', '[', ']', ':', ';', '|', '=', ','];
        $key = str_replace($badChars, '/', $key);
        while (strpos($key, '//') !== false) {
            $key = str_replace('//', '/', $key);
        }

        $directoryToCreate = $this->config['cacheDirectory'];

        $endOfDirectory = strrpos($key, '/');

        if ($endOfDirectory !== false) {
            $directoryToCreate = $this->config['cacheDirectory'].substr($key, 0, $endOfDirectory);
        }

        clearstatcache(true, $directoryToCreate);
        if (!file_exists($directoryToCreate)) {
            $result = mkdir($directoryToCreate, 0777, true);
            if (!$result) {
                return false;
            }
        }

        $filePath = $this->config['cacheDirectory'].$key.'.'.$this->config['fileExtension'];

        return $filePath;
    }

    /**
     * Read and validate a cache object, regardless of the current compression setting.
     *
     * @param string $key
     *
     * @return object|null
     */
    private function readCacheObject($key)
    {
        $filePath = $this->getFilePathFromKey($key);
        clearstatcache(true, $filePath);

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $cacheFileData = file_get_contents($filePath);

        if ($cacheFileData === false) {
            return null;
        }

        $cacheObj = json_decode($cacheFileData);

        if ($cacheObj === null) {
            $uncompressedData = @gzuncompress($cacheFileData);

            if ($uncompressedData === false) {
                return null;
            }

            $cacheObj = json_decode($uncompressedData);
        }

        if ($cacheObj === null || !isset($cacheObj->content) || !isset($cacheObj->expiryTimestamp)) {
            return null;
        }

        if (!$this->isSerialized($cacheObj->content)) {
            return null;
        }

        return $cacheObj;
    }

    /**
     * Determine whether a cache object is unexpired or may be served under load.
     *
     * @param object $cacheObj
     *
     * @throws Exception
     *
     * @return bool
     */
    private function isCacheObjectAvailable($cacheObj)
    {
        if ($cacheObj->expiryTimestamp > time()) {
            return true;
        }

        if ($this->config['unixLoadUpperThreshold'] == -1) {
            return false;
        }

        if (!function_exists('sys_getloadavg')) {
            throw new Exception('Your PHP installation does not support `sys_getloadavg` (Windows?). Please set `unixLoadUpperThreshold` to `-1` in your DOFileCache config.');
        }

        $unixLoad = sys_getloadavg();

        return is_array($unixLoad) && $unixLoad[0] >= $this->config['unixLoadUpperThreshold'];
    }
}
