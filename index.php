<?php

/**
 * Arcane: Intuitive Web Application
 * Copyright 2017-2018 Joshua Britt
 * https://github.com/capachow/arcane/
 * Released under the MIT License
**/

/* Application Settings */

define('DIR', [
  'IMAGES' => '/images/',
  'LAYOUTS' => '/layouts/',
  'LOCALES' => '/locales/',
  'SCRIPTS' => '/scripts/',
  'STYLES' => '/styles/',
  'VIEWS' => '/views/'
]);

define('SET', [
  'ERRORS' => false,
  'INDEX' => 'index',
  'LAYOUT' => null,
  'LOCALE' => null,
  'MINIFY' => true
]);

/* Application Functions */

function path($filter, $actual = false) {
  if(is_bool($filter)) {
    $return = str_replace('//', '/', '/' . implode(@URI, '/') . '/');
  } else if(is_int($filter)) {
    $return = @URI[$filter];
  } else {
    $return = $actual ? APP['DIR'] : APP['ROOT'];

    if(preg_match('/\.(jpe?g|.png|.gif|.svg)$/', $filter)) {
      if(!empty(DIR['IMAGES'])) {
        $return .= DIR['IMAGES'];
      }
    } else if(preg_match('/\.js$/', $filter)) {
      if(!empty(DIR['SCRIPTS'])) {
        $return .= DIR['SCRIPTS'];
      }
    } else if(preg_match("/\.css$/", $filter)) {
      if(!empty(DIR['STYLES'])) {
        $return .= DIR['STYLES'];
      }
    } else if(!$actual && !strpos($filter, '.')) {
      if(defined('LOCALE')) {
        $filter = LOCALE['URI'] . '/' . $filter;
      }
    }

    if(!strpos($filter, '.') && !strpos($filter, '?')) {
      $filter .= '/';
    }

    $return = $return . '/' . $filter;
    $return = preg_replace('#(^|[^:])//+#', '\\1/', $return);
  }

  return $return;
}

function relay($define, $filter) {
  ob_start();
    $filter();
  define(strtoupper($define), ob_get_clean());
}

function scribe($filter) {
  if(defined('TRANSCRIPT') && @TRANSCRIPT[$filter]) {
    $return = TRANSCRIPT[$filter];
  } else {
    $return = $filter;
  }

  return $return;
}

/* Application Constants */

(function() {
  define('__ROOT__', $_SERVER['DOCUMENT_ROOT']);
  define('APP', [
    'DIR' => __DIR__,
    'ROOT' => substr(__DIR__ . '/', strlen(realpath(__ROOT__))),
    'URI' => $_SERVER['REQUEST_URI']
  ]);
})();

/* Create Rewrite */

(function() {
  if(!file_exists('.htaccess')) {
    $htaccess = implode("\n", [
      '<IfModule mod_rewrite.c>',
      '  RewriteEngine On',
      '  RewriteCond %{REQUEST_URI} !(/$|\.|^$)',
      '  RewriteRule ^(.*)$ %{REQUEST_URI}/ [R=301,L]',
      '  RewriteCond %{REQUEST_FILENAME} !-f',
      '  RewriteRule . index.php [L]',
      '  RewriteCond %{REQUEST_FILENAME} -d',
      '  RewriteRule . index.php [L]',
      '</IfModule>'
    ]);

    file_put_contents('.htaccess', $htaccess);
  }
})();

/* Create Directories */

(function() {
  foreach(DIR as $directory => $path) {
    $path = trim($path, '/') . '/';

    if(!is_dir($path) && !empty($path)) {
      mkdir($path);
    }
  }
})();

/* Define LOCALES */

(function() {
  $files = rtrim(path(DIR['LOCALES'], true), '/');
  $locales = [];

  foreach(glob($files . '/*/*[-+]*.json') as $locale) {
    $filename = basename($locale, '.json');

    list($major, $minor) = [
      basename(dirname($locale)),
      trim(preg_replace('/' . $major . '/', '', $filename, 1), '+-')
    ];

    $uri = '/' . $major . '/';
    $files = [
      trim(DIR['LOCALES'], '/') . '/' . $minor . '.json',
      dirname($locale) . '/' . $major . '.json',
      $locale
    ];

    switch(substr($filename, 3)) {
      case $major:
        list($language, $country) = [$minor, $major];
      break;

      case $minor:
        list($language, $country) = [$major, $minor];
      break;
    }

    if(strpos($locale, '+')) {
      $minor = null;
    } else {
      $uri .= $minor . '/';
    }

    $locales[$major][$minor] = [
      'CODE' => $language . '-' . $country,
      'COUNTRY' => $country,
      'FILES' => $files,
      'LANGUAGE' => $language,
      'URI' => $uri,
    ];
  }

  define('LOCALES', $locales);
})();

/* Define LOCALE and URI */

(function() {
  $uri = explode('/', strtok(APP['URI'], '?'));
  $uri = array_filter(array_diff($uri, explode('/', APP['ROOT'])));

  if(!empty($uri)) {
    $uri = array_combine(range(1, count($uri)), $uri);

    if(array_key_exists($uri[1], LOCALES)) {
      if(isset($uri[2]) && array_key_exists($uri[2], LOCALES[$uri[1]])) {
        $locale = LOCALES[$uri[1]][$uri[2]];

        array_shift($uri);
        array_shift($uri);
      } else if(array_key_exists(null, LOCALES[$uri[1]])) {
        $locale = LOCALES[$uri[1]][null];

        array_shift($uri);
      }
    }

    if(isset($locale)) {
      define('LOCALE', $locale);
    }

    if(!empty($uri)) {
      $uri = array_combine(range(1, count($uri)), $uri);
    }
  }

  define('URI', $uri);
})();

/* Define TRANSCRIPT or Redirect */

(function() {
  if(defined('LOCALE')) {
    $transcripts = [];

    foreach(LOCALE['FILES'] as $file) {
      if(file_exists($file)) {
        $file = json_decode(file_get_contents($file), true);
        $transcripts = $file + $transcripts;
      }
    }

    define('TRANSCRIPT', $transcripts);
  } else if(!empty(SET['LOCALE'])) {
    $pattern = '/[a-z]{2}-[a-z]{2}/';
    $language = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);

    preg_match_all($pattern, $language, $request, PREG_PATTERN_ORDER);

    foreach(reset($request) as $locale) {
      foreach(LOCALES as $locales) {
        if(in_array($locale, array_column($locales, 'CODE'))) {
          header('Location: ' . path(reset($locales)['URI']));

          exit;
        }
      }
    }

    header('Location: ' . path(SET['LOCALE']));

    exit;
  }
})();

/* Define CONTENT and Evaluate ROUTE */

(function() {
  $path = URI;

  ini_set('display_errors', SET['ERRORS'] ? 1 : 0);

  if(SET['ERRORS']) {
    error_reporting(E_ALL);
  } else {
    error_reporting(E_ALL & ~(E_NOTICE|E_DEPRECATED));
  }

  do {
    $view = path(DIR['VIEWS'] . '/' . implode('/', $path) . '.php', true);

    if(!is_file($view) && is_dir(substr($view, 0, -4) . '/')) {
      $view = rtrim(str_replace('.php', '', $view), '/');
      $view = $view . '/' . SET['INDEX'] . '.php';
    }

    if(is_file($view)) {
      ob_start();
        define('REALPATH', $path);
        define('VIEWFILE', $view);

        unset($path, $view);

        require_once VIEWFILE;

        $path = REALPATH;
      define('CONTENT', ob_get_clean());

      if(defined('ROUTE')) {
        $facade = array_diff_assoc(URI, $path);

        foreach(ROUTE as $route) {
          if(count($route) === count($facade)) {
            foreach(array_values($facade) as $increment => $segment) {
              if(is_array($route[$increment])) {
                if(!in_array($segment, $route[$increment])) {
                  break;
                }
              } else if($route[$increment] !== $segment) {
                break;
              }

              if(end($facade) === $segment) {
                $path = $path + $facade;

                break 2;
              }
            }
          }
        }
      }

      if(end($path) !== SET['INDEX']) {
        break;
      }
    } else if(empty($path)) {
      return false;
    }

    array_pop($path);
  } while(true);

  define('PATH', $path);
})();

/* Redirect or Render View */

(function() {
  ob_start(function($filter) {
    if(SET['MINIFY']) {
      $return = str_replace([
        "\r\n", "\r", "\n", "\t", '  '
      ], '', $filter);

      return $return;
    } else {
      return $filter;
    }
  });
    if(array_diff(URI, PATH)) {
      header('Location: ' . path(implode('/', PATH)));

      exit;
    } else if(defined('REDIRECT')) {
      header('Location: ' . path(REDIRECT));

      exit;
    } else {
      if((defined('LAYOUT') && !empty(LAYOUT)) || !empty(SET['LAYOUT'])) {
        $layout = defined('LAYOUT') ? LAYOUT : SET['LAYOUT'];
        $layout = path(DIR['LAYOUTS'] . '/' . $layout . '.php', true);
      }

      if(isset($layout) && file_exists($layout)) {
        define('LAYOUTFILE', $layout);

        unset($layout);

        require_once LAYOUTFILE;
      } else {
        echo CONTENT;
      }
    }
  ob_get_flush();
})();

?>