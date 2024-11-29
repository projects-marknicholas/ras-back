<?php
require 'config.php';
require 'router.php';

// Controllers
require 'controllers/device.php';

// Initialize Router
$router = new Router();

// Post Requests
$router->post('/api/v1/data', 'DeviceController@insert_data');
$router->get('/api/v1/data', 'DeviceController@get_data_by_parameter');
$router->get('/api/v1/recent', 'DeviceController@get_latest_values');
$router->get('/api/v1/history', 'DeviceController@history');

// Dispatch the request
$router->dispatch();
?>
