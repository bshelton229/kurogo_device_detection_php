## Kurogo Device Detection Tools

A small PHP library for accessing the Kurogo device detection service.

http://kurogo.org/docs/mw/current/devicedetection.html

_NOTE: The PHP library will fall back to $_SERVER['HTTP_USER_AGENT'] if no user agent is set_

### Example:

    <?php
      require_once('path_to/kurogo_device_detection.php);
      $kurogo_device = new KurogoDeviceDetection();
      // If you want disk caching
      $kurogo_device->enableCaching('/tmp/cache/location');
      $detected = $kurogo_device->detect();

      if ($detected->pagetype == "compliant") {
        echo "We are a compliant device<br />\n";
      }
      else {
        echo "We are not a compliant device<br />\n";
      }
    ?>
