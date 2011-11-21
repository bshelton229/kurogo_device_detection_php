<?php
class KurogoDeviceDetection {
  private $user_agent = '';
  private $api_version = "2";
  private $test = FALSE;
  // URLS for test and production
  private $test_url = 'https://modolabs-device-test.appspot.com/api/';
  private $production_url = 'https://modolabs-device.appspot.com/api/';
  // Remote data instance store
  private $remote_data = FALSE;
  private $caching = FALSE;
  private $cache_expire_min = "7200";

  public function __construct($options = array()) {
    // You can set most internal variables by passing keys to the $options array
    // Try to set user agent from options of $_SERVER['HTTP_USER_AGENT']
    if (isset($options['user_agent'])) {
      $this->user_agent = $options['user_agent'];
    }
    elseif (isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT'])) {
      $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
    }
    // Try to set api_version from options, default is 2
    if (isset($options['api_version'])) {
      $this->api_version = $options['api_version'];
    }
    // Try to set test mode from options, default is FALSE
    if (isset($options['test']) && $options['test']) {
      $this->test = TRUE;
    }
    // Try to enable caching
    if (isset($options['caching'])) {
      $this->enableCaching($options['caching']);
    }
  }

  // Getters and Setters, self explanatory
  public function getUserAgent() {
    return $this->user_agent;
  }

  public function setUserAgent($user_agent) {
    $this->clear();
    $this->user_agent = $user_agent;
  }

  public function getApiVersion() {
    return $this->api_version;
  }

  public function setApiVersion($api_version) {
    $this->clear();
    $this->api_version = $api_version;
  }

  public function testMode() {
    return $this->test_mode;
  }

  public function setTestMode($test) {
    $this->clear();
    $this->test = $test ? TRUE : FALSE;
  }

  /**
   * Set the cache expire threshold
   */
  public function setCacheExpire($min) {
    $this->cache_expire_min = intval($min);
  }

  /**
   * Attempt to enable disk based caching
   */
  public function enableCaching($dir) {
    $dir = realpath($dir);
    if (is_dir($dir) && is_writable($dir)) {
      $this->caching = $dir;
      return TRUE;
    }
    else {
      $this->caching = FALSE;
      return FALSE;
    }
  }

  /**
   * Return the Kurogo API url based on mode
   */
  public function url() {
    return $this->test ? $this->test_url : $this->production_url;
  }

  /**
   * The main detect method
   * @return array
   *  Returns an array of the user agent classification or Kurogo default
   */
  public function detect() {
    return json_decode($this->getRemote());
  }

  /**
   * Get the raw JSON from the remote API url
   */
  private function getRemote() {
    // Return from instance cache if available
    if ($this->remote_data) {
      return $this->remote_data;
    }
    // Return from $this->caching if available
    if ($from_cache = $this->fromCache()) {
      $this->remote_data = $from_cache;
      return $from_cache;
    }

    // If we didn't return from instance of disk cache
    // pull the remote request
    $query_string = http_build_query(array(
      'user-agent' => $this->user_agent,
      'version' => $this->api_version,
    ));
    $url = $this->url();
    $ch = curl_init("$url?$query_string");
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    $this->remote_data = $data;
    // Cache the remote request if caching is enabled
    $this->toCache($data);
    return $data;
  }

  /**
   * Send data to the disk cache if enabled
   */
  private function toCache($data) {
    if (!$this->caching) { return FALSE; }
    $write = array(
      'cached' => time(),
      'data' => $data,
    );
    $fh = fopen($this->cacheFile(), 'w+');
    fwrite($fh, serialize($write));
    fclose($fh);
  }

  /**
   * Return data from the disk cache if enabled
   */
  private function fromCache() {
    if (!$this->caching) { return FALSE; }
    $cache_file = $this->cacheFile();
    if (is_readable($cache_file)) {
      $read = unserialize(file_get_contents($cache_file));
      // If we read a file that doesn't contain the correct data
      // remove it and return false
      if (!isset($read['cached']) || !isset($read['data'])) {
        unlink($cache_file);
        return FALSE;
      }
      // Check for expiration
      $cached = $read['cached'];
      $expires = $read['cached'] + ($this->cache_expire_min * 60);

      // Check for an expired cache file
      if (time() > $expires) {
        unlink($cache_file);
        return FALSE;
      }
      else {
        return $read['data'] ? $read['data'] : FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Generate the unique caching id
   */
  private function cacheFile() {
    if (!$this->caching) { return FALSE; }
    $id = md5($this->user_agent . ':::' . $this->api_version);
    return $this->caching . '/' . $id . '.cache';
  }

  /**
   * Clear the remote_data contents
   */
  private function clear() {
    $this->remote_data = FALSE;
  }
}
