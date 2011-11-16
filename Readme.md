## Kurogo Device Detection Tools

A very lightweight set of libraries (currently PHP and Ruby) for accessing the
Kurogo device detection API.

_NOTE: The PHP library will fall back to $_SERVER['HTTP_USER_AGENT'] if no user agent is set_

### Example:

    <?php
      require_once('path_to/kurogo_device_detection.php);
      $kurogo_device = new KurogoDeviceDetection();
      $detected = $kurogo_device->detect();

      if ($detected->pagetype == "compliant") {
        echo "We are a compliant device<br />\n";
      }
      else {
        echo "We are not a compliant device<br />\n";
      }
    ?>
