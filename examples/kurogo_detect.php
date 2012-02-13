<?php
require_once(dirname(__FILE__) . "/../lib/kurogo_device_detection.php");
// Instantiate the KurogoDeviceDetection object and pass in
// an array of initial options.
$kurogo_detect = new KurogoDeviceDetection(array(
  'detection_mode' => 'local',
  'local_device_file' => dirname(__FILE__) . '/deviceData.json',
));
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta charset="utf-8" />
<title>Kurogo Detect Test</title>
</head>
<body>
<h3>User Agent: <?php echo $kurogo_detect->getUserAgent(); ?></h3>
<p><strong>Local Results:</strong></p>
<h1>Is mobile: <?php echo $kurogo_detect->isMobile() ? "Yes" : "No"; ?></h1>
<pre>
  <?php print_r($kurogo_detect->detect()); ?>
</pre>

<p><strong>Remote Results:</strong></p>
<pre>
  <?php
    // Switch to remote detection (which is the default)
    $kurogo_detect->setDetectionMode('remote');
    print_r($kurogo_detect->detect());
  ?>
</pre
</body>
</html>
