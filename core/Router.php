<?php

class Router {
    private $routes = [];

    public function get($path, $callback) {
        $this->addRoute('GET', $path, $callback);
    }

    public function post($path, $callback) {
        $this->addRoute('POST', $path, $callback);
    }

    public function put($path, $callback){
        $this->addRoute("PUT", $path, $callback);
    }

    public function delete($path, $callback){
        $this->addRoute("DELETE", $path, $callback);
    }

    public function addRoute($method, $path, $callback) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'callback' => $callback,
        ];
    }

    public function dispatch($requestUri, $requestMethod) {
    $uri = parse_url($requestUri, PHP_URL_PATH);

    foreach ($this->routes as $route) {
        if ($route['method'] === $requestMethod && $route['path'] === $uri) {
            $callback = $route['callback'];

            if (is_array($callback)) {
                // Exemple : [AuthController::class, 'login']
                $controller = $callback[0];
                $method = $callback[1];
                $instance = new $controller(); // <--- instanciation
                return call_user_func([$instance, $method]);
            }

            // Sinon, on appelle directement
            return call_user_func($callback);
        }
    }

    // Route non trouvée
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
}