<?php
// Isolated Jeedom/API doubles: never connect to a real account or database.
$fixture = json_decode(file_get_contents(dirname(__DIR__, 2) . '/case.json'), true);
$requests = array();
$updates = array();
$errors = array();
set_error_handler(function ($severity, $message) {
  global $errors;
  $errors[] = $message;
  return true;
});
register_shutdown_function(function () {
  global $requests, $updates, $errors;
  echo json_encode(array('requests' => $requests, 'updates' => $updates, 'errors' => $errors));
});

class eqLogic {
  public static $items = array();
  private $data;
  public function __construct($data) {
    $this->data = $data;
  }
  public static function byType($type, $onlyEnable = false) {
    return array_values(array_filter(self::$items, function ($item) use ($onlyEnable) {
      return !$onlyEnable || $item->getIsEnable() == 1;
    }));
  }
  public static function byLogicalId($id, $type) {
    foreach (self::$items as $item) {
      if ($item->getLogicalId() === $id) {
        return $item;
      }
    }
    return null;
  }
  public function getLogicalId() {
    return $this->data['id'];
  }
  public function getIsEnable() {
    return isset($this->data['enabled']) ? $this->data['enabled'] : 1;
  }
  public function getConfiguration($key, $default = '') {
    return isset($this->data[$key]) ? $this->data[$key] : $default;
  }
  public function getCmd($type = null, $id = null) {
    if ($id !== null) {
      return null;
    }
    return array_map(function ($logicalId) {
      return new cmd($logicalId);
    }, isset($this->data['commands']) ? $this->data['commands'] : array());
  }
  public function checkAndUpdateCmd($command, $value, $date = null) {
    global $updates;
    $updates[] = array($this->getLogicalId(), is_object($command) ? $command->getLogicalId() : $command, $value);
  }
}
class cmd {
  private $logicalId;
  public function __construct($logicalId) {
    $this->logicalId = $logicalId;
  }
  public function getLogicalId() {
    return $this->logicalId;
  }
}
class config {
  public static function byKey($key, $plugin = '') {
    return $key === 'mode' ? 'internal' : '';
  }
}
class log {
  public static function add($plugin, $level, $message) {}
}
class message {
  public static function add($plugin, $message) {}
}
class jeedom {
  public static function apiAccess($key, $plugin) {
    return $key === 'test-key';
  }
}
class netatmo_standalone_api {
  public function __construct($config) {}
  public function api($path, $method, $data) {
    global $fixture, $requests;
    $path = '/' . $path;
    $requests[] = array($path, $data, $method);
    $key = $path === '/homestatus' ? $path . ':' . $data['home_id'] : $path;
    if (isset($fixture['responses'][$key])) {
      return $fixture['responses'][$key];
    }
    return array('homes' => array(), 'devices' => array());
  }
}
function init($key) {
  return isset($_GET[$key]) ? $_GET[$key] : '';
}
foreach ($fixture['equipment'] as $data) {
  eqLogic::$items[] = new eqLogic($data);
}
require_once dirname(__DIR__, 2) . '/plugins/netatmo/core/class/netatmo.class.php';
