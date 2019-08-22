<?php

session_start();

spl_autoload_register(function ($class) {
  global $_BASE;
  if (file_exists($_BASE.str_replace('\\', '/', $class).'.php'))
  {
    require_once($_BASE.str_replace('\\', '/', $class).'.php');
  }
});

error_reporting(E_ALL);
ini_set('display_errors', 1);

// echo __FILE__ . PHP_EOL;                  // /home/pablo/GitHub/macaw/index.php
// echo dirname(__FILE__) . PHP_EOL;         // /home/pablo/GitHub/macaw
// echo getcwd() . "\n";                     // /home/pablo/GitHub/macaw
// echo $_SERVER["DOCUMENT_ROOT"] . PHP_EOL; // /home/pablo/GitHub

use \router\Macaw;

Macaw::base_uri('/macaw');
Macaw::haltOnMatch();

Macaw::get('/',                       '\controllers\AuthController@login');
Macaw::get('login',                   '\controllers\AuthController@login');
Macaw::post('auth',                   '\controllers\AuthController@auth');
Macaw::get('logout',                  '\controllers\AuthController@logout');

Macaw::get('a',                   '\controllers\AController@index');
Macaw::get('a/(:num)',            '\controllers\AController@show');
Macaw::get('a/create',            '\controllers\AController@create');
Macaw::post('a/save',             '\controllers\AController@save');
Macaw::get('a/edit/(:num)',       '\controllers\AController@edit');

Macaw::get('a//edit/(:num)',       '/controllers/AController@edit');

// Macaw::onError(404, function() {
//   header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
//   echo "My 404";
// });

// Macaw::onError(405, function() {
//   header($_SERVER['SERVER_PROTOCOL']." 405 Method Not Allowed");
//   echo "My 405";
// });

//Macaw::onError(404, '\controllers\AController@errork');

Macaw::dispatch();



?>
