<?php

/**
 * Profile and find out where WordPress spends most of its time.
 * @author: Martin Adamko
 */

namespace HackingWP\WPProfiler;

if (!defined('WP_PROFILER_TRESHHOLD_MILISECONDS')) {
  define ('WP_PROFILER_TRESHHOLD_MILISECONDS', 30);
}

class Log {
  protected static $initiated;
  protected static $lastTimestamp;
  protected static $outputFilePath;

  protected static $tree = [];

  public static function init(string $outputFilePathPattern = null) {
    if (static::$initiated) {
      return;
    }

    static::$initiated = true;
    static::$lastTimestamp = $_SERVER["REQUEST_TIME_FLOAT"];

    if ($outputFilePathPattern && !strstr($outputFilePathPattern, '%s')) {
      throw new \Exception("Expecting output file pattern to have `%s` placeholder where to put id of the request", 500);
    }

    if ($outputFilePathPattern && !(realpath(dirname($outputFilePathPattern)) && is_dir(dirname($outputFilePathPattern)))) {
      throw new \Exception("Expecting parend of the file pattern to be a real path and dir", 500);
    }

    static::$outputFilePath = $outputFilePathPattern ? $outputFilePathPattern : ABSPATH.'measurements.%s.tsv';
    static::$tree[static::id()] = [];

    \add_action('all', ['HackingWP\WPProfiler\Log', 'tick']);
  }

  protected static function getBacktrace() {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    while($backtrace[0]['function'] !== '_wp_call_all_hook') {
      array_shift($backtrace);
    }

    array_shift($backtrace);

    if (
      $backtrace[0]['function'] === 'apply_filters' ||
      $backtrace[0]['function'] === 'do_action'
    ) {
      array_shift($backtrace);
    }

    return $backtrace;
  }

  protected static function append($id, string $line) {
    static::init();

    if (file_put_contents(sprintf(static::$outputFilePath, $id), $line, FILE_APPEND | LOCK_EX) === false) {
      throw new \Exception("Error writing to file", 500);
    }
  }

  protected static function writeLine($id, string $line) {
    static::append($id, rtrim($line, "\n")."\n");
  }

  protected static function id() {
    return "".$_SERVER["REQUEST_TIME_FLOAT"];
  }

  protected static function bump(array $backtrace, float $diff) {
    $path = null;

    foreach ($backtrace as $trace) {
      $segment = isset($trace['file']) ? str_replace(ABSPATH, '', $trace['file']) : '(no file)';
      $segment.= isset($trace['line']) ? ':'.$trace['line'] : '';
      $segment.= ':';
      $segment.= isset($trace['class']) ? $trace['class'] : '';
      $segment.= isset($trace['type']) ? $trace['type'] : '';
      $segment.= isset($trace['function']) ? $trace['function'].'(' : '';
      if (isset($trace['args']) && count($trace['args']) === 1 && is_string($trace['args'][0])) {
        $segment.= str_replace(ABSPATH, '', $trace['args'][0]);
      }
      $segment.= isset($trace['function']) ? ')' : '';

      $path = $path === null ? $segment : $path."\t".$segment;

      static::$tree[static::id()][$path] = isset(static::$tree[static::id()][$path])
        ? static::$tree[static::id()][$path] + $diff
        : $diff;
    }
  }

  protected static function store(float $now) {
    $diff = ($now - static::$lastTimestamp) * 1000;
    $backtrace = array_reverse(static::getBacktrace());

    static::bump($backtrace, $diff);
  }

  protected static function filter() {
    foreach (static::$tree as &$subtree) {
      foreach ($subtree as $path => &$duration) {
        if (!is_float($duration)) {
          throw new \Exception("Something is wrong. Duration is not float. Type: ".gettype($duration), 500);
        }

        if (
          $duration <= WP_PROFILER_TRESHHOLD_MILISECONDS ||
          strstr($path.'$', ':do_action()$') ||
          strstr($path.'$', ':apply_filters()$') ||
          strstr($path.'$', ':apply_filters_ref_array()$') ||
          strstr($path.'$', ':WP_Hook->do_action()$') ||
          strstr($path.'$', ':WP_Hook->apply_filters()$')
        ) {
          unset($subtree[$path]);
        }
      }
    }
  }

  protected static function dump() {
    static::filter();

    foreach (static::$tree as $id => &$subtree) {
      static::writeLine($id, "Duration\tPath");
      static::writeLine($id, "\t".\home_url($_SERVER['REQUEST_URI']));

      foreach ($subtree as $path => &$duration) {
        static::writeLine($id, "${duration}\t${path}");
      }
    }
  }

  public static function tick(string $tag) {
    $now = microtime(true);
    static::store($now);
    static::$lastTimestamp = microtime(true);

    if ($tag === 'shutdown') {
      static::dump();
    }
  }
}
