<?php
class KurogoDeviceDetection {
  private $user_agent = '';
  private $api_version = "2";
  private $test = FALSE;
  // URLS for test and production
  private $test_url = 'https://modolabs-device-test.appspot.com/api/';
  private $production_url = 'https://modolabs-device.appspot.com/api/';

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
  }

  // Getters and Setters, self explanatory
  public function getUserAgent() {
    return $this->user_agent;
  }

  public function setUserAgent($user_agent) {
    $this->user_agent = $user_agent;
  }

  public function getApiVersion() {
    return $this->api_version;
  }

  public function setApiVersion($api_version) {
    $this->api_version = $api_version;
  }

  public function testMode() {
    return $this->test_mode;
  }

  public function setTestMode($test) {
    $this->test = $test ? TRUE : FALSE;
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
    $url = $this->test ? $this->test_url : $this->production_url;
    $query_string = http_build_query(array(
      'user-agent' => $this->user_agent,
      'version' => $this->api_version,
    ));
    $ch = curl_init("$url?$query_string");
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }
}