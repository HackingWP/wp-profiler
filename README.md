# WordPress Profiler

Profile and find out where WordPress spends most of its time.

There's an option to hook to every single hook/filter that we can "use" to profile perfromance of almost every single part of the server request.

The request mignt take a little bit longer than without logging (hello Mr. Obvious) but the profiling tries to cut out it's own time spent.

## Installation

Clone this library (e.g. to your theme folder) or use Composer:

```json
{
  "name": "your/project",
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/HackingWP/wp-profiler"
    }
  ],
  "require": {
    "hacking-wp/wp-profiler": "dev-main"
  }
}
```

> Don't forget to require `vendor/autoload.php` or the `src/Log.php` directly.

## Usage

Add this to your `functions.php`:

```php
if (wp_get_environment_type() !== 'production' && !defined('DOING_CRON') && !is_admin()) {
  HackingWP\WPProfiler\Log::init(ABSPATH.'measurements.%s.tsv');
}
```

Add any other conditions when to skip logging:

1. Production server requests (unless you want to)
2. WP Cron jobs;
3. WP Admin.


Use `WP_PROFILER_TRESHHOLD_MILISECONDS` constant to override pre-defined treshold of 30 ms.
## Output file path pattern for `init()`

Use `%s` placeholder where to put requst time float.

## Results

Example of the processed output in Google Spreadsheet:

![Results](results.png)

Enjoy,

[Martin Adamko](https://github.com/attitude)
