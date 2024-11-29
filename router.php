<?php
class Router {
  private $routes = [];

  public function addRoute($method, $path, $handler) {
    $this->routes["$method:$path"] = $handler;
  }

  public function get($path, $handler) {
    $this->addRoute('GET', $path, $handler);
  }

  public function post($path, $handler) {
    $this->addRoute('POST', $path, $handler);
  }

  public function put($path, $handler) {
    $this->addRoute('PUT', $path, $handler);
  }

  public function delete($path, $handler) {
    $this->addRoute('DELETE', $path, $handler);
  }

  public function dispatch() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = strtok($_SERVER['REQUEST_URI'], '?');

    // Remove subdirectory from the path
    $baseDir = '/ras';
    if (strpos($path, $baseDir) === 0) {
      $path = substr($path, strlen($baseDir));
    }

    $routeKey = "$method:$path";

    error_log("Dispatching request: Method: $method, Path: $path, RouteKey: $routeKey");

    if (array_key_exists($routeKey, $this->routes)) {
      $handler = $this->routes[$routeKey];
      list($controller, $method) = explode('@', $handler);
      if (class_exists($controller) && method_exists($controller, $method)) {
        $controllerInstance = new $controller();
        call_user_func([$controllerInstance, $method]);
      } else {
        error_log("Handler not found: Controller: $controller, Method: $method");
        http_response_code(404);
        echo "404 Not Found";
      }
    } else {
      error_log("Route not found: $routeKey");
      http_response_code(404);
      echo "404 Not Found";
    }
  }
}
?>
