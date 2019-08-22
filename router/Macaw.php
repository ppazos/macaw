<?php

namespace router;

/**
 * @method static Macaw get(string $route, Callable $callback)
 * @method static Macaw post(string $route, Callable $callback)
 * @method static Macaw put(string $route, Callable $callback)
 * @method static Macaw delete(string $route, Callable $callback)
 * @method static Macaw options(string $route, Callable $callback)
 * @method static Macaw head(string $route, Callable $callback)
 */
class Macaw {
  public static $halts = false;
  public static $routes = array();
  public static $methods = array();
  public static $callbacks = array();
  public static $maps = array();
  public static $patterns = array(
      ':any'  => '[^/]+',
      ':num'  => '[0-9]+',
      ':all'  => '.*',
      ':uuid' => '[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-(8|9|a|b)[a-f0-9]{3}\-[a-f0-9]{12}'
  );
  public static $error_callback;
  public static $_base_uri = '';

  public static $error_callbacks = array(); // HTTP Status Code => callback

  /**
   * Defines a route w/ callback and method
   */
  public static function __callstatic($method, $params)
  {
    // ----------------------------------------------
    // echo '============================================='. PHP_EOL;
    // $pps = print_r($params, true);
    // echo "callstatic $method $pps ". PHP_EOL;
    // ----------------------------------------------

    if ($method == 'base_uri')
    {
      self::$_base_uri = $params[0];
      return;
    }
    else if ($method == 'map')
    {
      $maps = array_map('strtoupper', $params[0]);
      $uri = strpos($params[1], '/') === 0 ? $params[1] : '/' . $params[1];
      $callback = $params[2];
    }
    else if (in_array($method, array('get', 'post', 'any')))
    {
      $maps = null;
      $uri = strpos($params[0], '/') === 0 ? $params[0] : '/' . $params[0];
      $callback = $params[1];
    }
    else
    {
       throw new \Exception("Method '$method' not recognized");
    }

    $uri = self::$_base_uri . $uri;

    // ----------------------------------------------
    // echo $maps . PHP_EOL;
    // echo $uri . PHP_EOL;
    // echo $method . PHP_EOL;
    // echo $callback . PHP_EOL;
    // ----------------------------------------------

    array_push(self::$maps, $maps); // empty?
    array_push(self::$routes, $uri); // /a/b/(:num)
    array_push(self::$methods, strtoupper($method)); // GET, POST, ANY
    array_push(self::$callbacks, $callback); // \controllers\AController@action

    // ----------------------------------------------
    // print_r(self::$maps);
    // print_r(self::$routes);
    // print_r(self::$methods);
    // print_r(self::$callbacks);
    // echo PHP_EOL;
    // echo PHP_EOL;
    // ----------------------------------------------
  }

  /**
   * Defines callback if route is not found
  */
  public static function error($callback) {
    self::$error_callback = $callback;
  }

  // onError(405, function() {...})
  public static function onError($status, $callback) {
    self::$error_callbacks[$status] = $callback;
  }

  public static function haltOnMatch($flag = true) {
    self::$halts = $flag;
  }

  /**
   * Runs the callback for the given request
   */
  public static function dispatch()
  {
    // $uri us the requested route that should match wit the items in $routes
    // exactly or using regex matchers

    //print_r($_SERVER);
    //echo $_SERVER['REQUEST_URI'] . PHP_EOL;
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // /macaw/a/123
    $method = $_SERVER['REQUEST_METHOD'];

    $searches = array_keys(static::$patterns);
    $replaces = array_values(static::$patterns);

    $found_route = false;

    // FIXME: add try/catch to action call to be able to return 500 errors
    // 404, 405, ...
    $return_status = 0;

    // echo "DISPATCH ========================================". PHP_EOL;
    //echo 'URI:'. $uri . PHP_EOL;
    //print_r(self::$routes);
    //print_r($searches); // (:num)
    //print_r($replaces); // [0-9]+

    // changes // to /
    self::$routes = preg_replace('/\/+/', '/', self::$routes);

    // Check if route is defined without regex
    if (in_array($uri, self::$routes))
    {
      //echo "Exact uri match $uri" . PHP_EOL;
      $route_positions = array_keys(self::$routes, $uri);
      foreach ($route_positions as $pos)
      {
        $found_route = true;

        // if method matches
        // Using an ANY option to match both GET and POST requests
        if (self::$methods[$pos] == $method ||
            self::$methods[$pos] == 'ANY' ||
            (!empty(self::$maps[$pos]) && in_array($method, self::$maps[$pos])))
        {

          // callback for route is not an function (is a controller@action)
          // would be the same to do is_string(...) instead of !is_object(..)
          if (!is_object(self::$callbacks[$pos]))
          {
            // // Grab all parts based on a / separator
            // $parts = explode('/',self::$callbacks[$pos]);
            //
            // print_r($parts);
            //
            // // Collect the last index of the array
            // $last = end($parts);

            // \controller\ControllerA@action
            $controller_action_string = self::$callbacks[$pos];

            // Grab the controller name and method call
            $controller_action = explode('@', $controller_action_string);

            // Instantitate controller
            if (!class_exists($controller_action[0]))
            {
              echo 'controller class doesnt exists';
              $return_status = 404;
              return;
            }
            $controller = new $controller_action[0]();

            if (!method_exists($controller, $controller_action[1]))
            {
              echo "controller and action not found";
              $return_status = 404;
              return;
            }

            $controller->{$controller_action[1]}();

            if (self::$halts) return;
          }
          else
          {
            // Call closure
            call_user_func(self::$callbacks[$pos]);

            if (self::$halts) return;
          }
        }
        else
        {
          // FIXME: Matched route but incorrect method: 405
          $return_status = 405;
        }
      }
    }
    else
    {
      //echo 'Check if uri matches with regex' . PHP_EOL;

      // Check if defined with regex
      $pos = 0; // the index of the methods, routes and callbacks
      foreach (self::$routes as $route)
      {
        // Puts the correspondent regex in place of the short matcher,
        // e.g. (:num) => [0-9]+
        if (strpos($route, ':') !== false) {
          $route = str_replace($searches, $replaces, $route);
        }

        // If the route with the regex matches with the requested route
        if (preg_match('#^' . $route . '$#', $uri, $matched))
        {
          $found_route = true;

          // if method matches
          if (self::$methods[$pos] == $method ||
              self::$methods[$pos] == 'ANY' ||
              (!empty(self::$maps[$pos]) && in_array($method, self::$maps[$pos])))
          {

            // Array
            // (
            //     [0] => /macaw/a/edit/23423
            //     [1] => 23423
            // )

            // removes the url but keeps the parameters for the action call!
            // Remove $matched[0] as [1] is the first parameter.
            array_shift($matched);

            // Array
            // (
            //     [0] => 23423
            // )


            // if the callback is not a function (is a Controller@action)
            if (!is_object(self::$callbacks[$pos]))
            {
              /*
              // Grab all parts based on a / separator
              $parts = explode('/',self::$callbacks[$pos]);

              echo 'CALLBACK PARTS: ';
              print_r($parts);

              // Collect the last index of the array
              $last = end($parts);

              echo $last . PHP_EOL;
              */

              // \controller\ControllerA@action
              $controller_action_string = self::$callbacks[$pos];

              // array(controller, action)
              $controller_action = explode('@', $controller_action_string);

              // Instantitate controller
              if (!class_exists($controller_action[0]))
              {
                echo 'controller class doesnt exists';
                $return_status = 404;
                return;
              }
              $controller = new $controller_action[0]();

              // Fix multi parameters
              if (!method_exists($controller, $controller_action[1]))
              {
                echo "controller and action not found";
                $return_status = 404;
                return;
              }

              call_user_func_array(array($controller, $controller_action[1]), $matched);
            }
            else // if the callback is directly a function, just call it!
            {
              call_user_func_array(self::$callbacks[$pos], $matched);
            }

            if (self::$halts) return;
          }
          else
          {
            // FIXME: Matched route but incorrect method: 405
            $return_status = 405;
          }
        }
        $pos++;
      }
    }

    // print_r(self::$error_callbacks);

    if (!$found_route) $return_status = 404;

    //echo $return_status . PHP_EOL;
    //echo $_SERVER['REQUEST_URI'] . PHP_EOL;

    // user defined error handlers
    if (array_key_exists($return_status, self::$error_callbacks))
    {
      //echo "Error callback exists for $return_status". PHP_EOL;

      $c = self::$error_callbacks[$return_status];
      if (is_string($c))
      {
        //echo "is string $c". PHP_EOL;
        // Avoid loops on 404
        // Next dispatch wont find the error callback because was already used
        unset(self::$error_callbacks[$return_status]);

        // Set the controller@action as callback to the requested route and
        // re-dispatch to make all the process and execute the error callback
        $method_low = strtolower($_SERVER['REQUEST_METHOD']); // get/post
        $uri_no_base = substr($uri, strlen(self::$_base_uri));
        self::$method_low($uri_no_base, $c); // will add the base_uri prefix
        self::dispatch();
        return;
      }
      else // callback should be a function, TODO: check
      {
        $c();
      }
      return;
    }

    // default 404 and 405 handlers
    switch ($return_status)
    {
      case 404:
        header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
        echo "Controller or action not found";
      break;
      case 405:
        header($_SERVER['SERVER_PROTOCOL']." 405 Method Not Allowed");
        echo "Requested method doesn't match allowed methods";
      break;
    }
  }
}
