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
  private $local_device_file = '';
  private $detection_mode = 'remote';

  /**
   * Constructor allows options to be set in an array
   * on construction
   */
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
    // Try to set detection mode
    if (isset($options['detection_mode'])) {
      $this->setDetectionMode($options['detection_mode']);
    }
    // Try to set the local device file
    if (isset($options['local_device_file'])) {
      $this->setLocalDeviceFile($options['local_device_file']);
    }
  }

  /**
   * Return the user_agent instance variable
   */
  public function getUserAgent() {
    return $this->user_agent;
  }

  /**
   * Set the user_agent instance variable
   */
  public function setUserAgent($user_agent) {
    $this->clear();
    $this->user_agent = $user_agent;
  }

  /**
   * Return the api_version instance variable
   */
  public function getApiVersion() {
    return $this->api_version;
  }

  /**
   * Set the api_version instance variable
   */
  public function setApiVersion($api_version) {
    $this->clear();
    $this->api_version = $api_version;
  }

  /**
   * Return test mode boolean from the test_mode
   * instance variable
   */
  public function testMode() {
    return $this->test_mode;
  }

  /**
   * Set the test_mode boolean instance variable
   */
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
   * Set the local_device_file instance variable
   * Make sure the file exists
   */
  public function setLocalDeviceFile($file) {
    $file = realpath($file);
    if (file_exists($file)) {
      $this->clear();
      $this->local_device_file = $file;
      return $file;
    }
    else {
      return FALE;
    }
  }

  /**
   * Return the local_device_file instance variable
   */
  public function getLocalDeviceFile() {
    return $this->local_device_file;
  }

  /**
   * Set the detection_mode instance variable
   */
  public function setDetectionMode($mode) {
    $mode = trim($mode);
    if (preg_match('/^(local|remote)$/i', $mode)) {
      $this->clear();
      $this->detection_mode = strtolower($mode);
      return $this->detection_mode;
    }
    else {
      return FALSE;
    }
  }
  
  /**
   * Return the detection_mode instance variable
   */
  public function getDetectionMode() {
    return $this->detection_mode;
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
    // Determine if we're using local or remote detection
    if ($this->detection_mode == "remote") {
      return json_decode($this->getRemote());
    }
    else {
      $local_device = $this->getLocal();
      return $local_device ? $this->translateDevice($local_device) : FALSE;
    }
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
   * Get Kurogo Detection data from a local deviceDetection.json file
   * This was mildly adapted straight from Kurogo's lib/DeviceClassifier.php
   * https://github.com/modolabs/Kurogo-Mobile-Web/blob/master/lib/DeviceClassifier.php
   */
  private function getLocal() {
    if (empty($this->local_device_file) || !file_exists($this->local_device_file)) {
      return FALSE;
    }
    // Set up
    $get_devices = json_decode(file_get_contents($this->local_device_file), TRUE);
    $devices = $get_devices['devices'];
    // Grab user_agent from our instance variable
    $user_agent = $this->user_agent;

    foreach($devices as $device)
    {
        foreach($device['match'] as $match)
        {
            if(isset($match['regex']))
            {
                $mods = "";
                if(isset($match['options']))
                {
                    if(isset($match['options']['DOT_ALL']) && $match['options']['DOT_ALL'] === true)
                    {
                        $mods .= "s";
                    }
                    if(isset($match['options']['CASE_INSENSITIVE']) && $match['options']['CASE_INSENSITIVE'] === true)
                    {
                        $mods .= "i";
                    }

                }
                if(preg_match('/'.str_replace('/', '\\/'.$mods, $match['regex']).'/', $user_agent))
                {
                    return $device;
                }
            }
            elseif(isset($match['partial']))
            {
                if(isset($match['options']) && isset($match['options']['CASE_INSENSITIVE']) && $match['options']['CASE_INSENSITIVE'] === true)
                {
                    if(stripos($user_agent, $match['partial']) !== false)
                    {
                        return $device;
                    }
                }

                // Case insensitive either isn't set, or is set to false.
                if(strpos($user_agent, $match['partial']) !== false)
                {
                    return $device;
                }
            }
            elseif(isset($match['prefix']))
            {
                if(isset($match['options']) && isset($match['options']['CASE_INSENSITIVE']) && $match['options']['CASE_INSENSITIVE'] === true)
                {
                    if(stripos($user_agent, $match['partial']) === 0)
                    {
                        return $device;
                    }
                }

                // Case insensitive either isn't set, or is set to false.
                if(strpos($user_agent, $match['prefix']) === 0)
                {
                    return $device;
                }
            }
            elseif (isset($match['suffix']))
            {
                if(isset($match['options']) && isset($match['options']['CASE_INSENSITIVE']) && $match['options']['CASE_INSENSITIVE'] === true)
                    $case_insens = true;
                else
                    $case_insens = false;
                // Because substr_compare is supposedly designed for this purpose...
                if(substr_compare($user_agent, $match['partial'], -(strlen($match['partial'])), strlen($match['partial']), $case_insens) === 0)
                {
                    return $device;
                }
            }
        }

    }
    return false;
  }

  /**
   * Translate a device from an array returned from
   * getLocal()
   *
   * This was mildly adapted straight from Kurogo's lib/DeviceClassifier.php
   * https://github.com/modolabs/Kurogo-Mobile-Web/blob/master/lib/DeviceClassifier.php
   */
  private function translateDevice($device) {
    $newDevice = array();
    $newDevice['supports_certificate'] = $device['classification'][strval($this->api_version)]['supports_certificate'];
    $newDevice['pagetype'] = $device['classification'][strval($this->api_version)]['pagetype'];
    $newDevice['description'] = isset($device['description']) ? $device['description'] : '';
    $newDevice['platform'] = $device['classification'][strval($this->api_version)]['platform'];
    return (object) $newDevice;
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
