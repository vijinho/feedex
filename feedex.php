<?php

/**
 * feedex.php - feed finder for multiple URLs
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright (c) Copyright 2018 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @url https://github.com/vijinho/feedex
 */

date_default_timezone_set('UTC');
ini_set('default_charset', 'utf-8');
ini_set('mbstring.encoding_translation', 'On');
ini_set('mbstring.func_overload', 6);
ini_set('auto_detect_line_endings', TRUE);

//-----------------------------------------------------------------------------
// required commands check

$requirements = [
    'curl'    => 'tool: curl - https://curl.haxx.se',
    'wget'    => 'tool: wget - https://www.gnu.org/software/wget/',
];

$commands = get_commands($requirements);

if (empty($commands)) {
    verbose("Error: Missing commands.", $commands);
    exit;
}


//require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/vendor/imelgrat/feed-finder/src/feed-finder.php';

//-----------------------------------------------------------------------------
// detect if run in web mode or cli

switch (php_sapi_name()) {
    case 'cli':
        break;
    default:
    case 'cli-server': // run as web-service
        define('DEBUG', 0);
        $save_data = 0;
        $params    = [
            'refresh', 'url', 'format', 'echo'
        ];

        // filter input variables
        $_REQUEST = array_change_key_case($_REQUEST);
        $keys     = array_intersect($params, array_keys($_REQUEST));
        $params   = [];
        foreach ($_REQUEST as $k => $v) {
            if (!in_array($k, $keys)) {
                unset($_REQUEST[$k]);
                continue;
            }
            $v = trim(strip_tags(filter_var(urldecode($v),
                        FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)));
            if (!empty($v)) {
                $_REQUEST[$k]      = $v;
                // params to command line
                $params['--' . $k] = escapeshellarg($v);
            } else {
                $params['--' . $k] = '';
            }
        }

        // build command line
        $php = cmd_execute('which php');
        $cmd = $php[0] . ' ' . $_SERVER['SCRIPT_FILENAME'] . ' --echo ';
        foreach ($params as $k => $v) {
            $cmd .= (empty($v)) ? " $k" : " $k=$v";
        }

        // exexute command line and quit
        $data = shell_execute($cmd);
        header('Content-Type: application/json');
        echo $data['stdout'];
        exit;
}


//-----------------------------------------------------------------------------
// define command-line options
// see https://secure.php.net/manual/en/function.getopt.php
// : - required, :: - optional

$options = getopt("hvdu:f:d:ei:", [
    'help', 'verbose', 'debug', 'echo', 'url:', 'format:', 'dir:', 'filename:', 'input:'
]);

$do = [];
foreach ([
 'help'    => ['h', 'help'],
 'verbose' => ['v', 'verbose'],
 'debug'   => ['d', 'debug'],
 'echo'    => ['e', 'echo'],
 'url'     => ['u', 'url'],
 'input'   => ['i', 'input'],
] as $i => $opts) {
    $do[$i] = (int) (array_key_exists($opts[0], $options) || array_key_exists($opts[1],
            $options));
}

if (array_key_exists('debug', $do) && !empty($do['debug'])) {
    $do['verbose']      = $options['verbose'] = 1;
}

ksort($do);

//-----------------------------------------------------------------------------
// defines (int) - forces 0 or 1 value

define('DEBUG', (int) $do['debug']);
define('VERBOSE', (int) $do['verbose']);
debug('OPTIONS:', $do);

//-----------------------------------------------------------------------------
// help
if (empty($options) || $do['help'] || !($do['url'] || $do['input'])) {
    options:

    $readme_file = dirname(__FILE__) . '/README.md';
    if (file_exists($readme_file)) {
        $readme = file_get_contents('README.md');
        if (!empty($readme)) {
            output($readme . "\n");
        }
    }

    print join("\n",
    [
        "Usage: php feedex.php",
        "Extract and save feeds from URL(s)",
        "(Specifying any other unknown argument options will be ignored.)\n",
        "\t-h,  --help                   Display this help and exit",
        "\t-v,  --verbose                Run in verbose mode",
        "\t-d,  --debug                  Run in debug mode (implies also -v, --verbose)",
        "\t-e,  --echo                   (Optional) Echo/output the result to stdout if successful",
        "\t-u,  --url=<url>              URL to check for feeds)",
        "\t-d,  --dir=                   (Optional) Directory for storing files (sys_get_temp_dir() if not specified)",
        "\t-i   --input={filename}       (Optional) Text file of URLs, one-per-line to read in and process.",
        "\t     --filename={output}      (Optional) Filename for output data from operation",
        "\t-f   --format={txt|json|php}  (Optional) Output format for screen and filename: txt (default)|json|php(serialized)",
    ]);

    // goto jump here if there's a problem
    errors:
    if (!empty($errors)) {
        if (is_array($errors)) {
            if ('json' === OUTPUT_FORMAT) {
                echo json_encode(['errors' => $errors], JSON_PRETTY_PRINT);
            } else {
                $errors = to_charset($errors);
                foreach ($errors as $error) {
                    verbose($error);
                }
            }
        }
    } else {
        output("\nNo errors occurred.\n");
    }

    goto end;
    exit;
}

//-----------------------------------------------------------------------------
// initialise variables

$errors = []; // errors to be output if a problem occurred
$output = []; // data to be output at the end

//-----------------------------------------------------------------------------
// output format

$format = '';
if (!empty($options['format'])) {
    $format = $options['format'];
}
switch ($format) {
    case 'php':
        $format = 'php';
        break;
    case 'json':
        $format = 'json';
        break;
    default:
    case 'txt':
        $format = 'txt';
}
define('OUTPUT_FORMAT', $format);
verbose("OUTPUT_FORMAT: $format");

//-----------------------------------------------------------------------------
// get dir and file for output

$dir = sys_get_temp_dir();
if (!empty($options['dir'])) {
    $dir = $options['dir'];
}
$dircheck = realpath($dir);
if (empty($dircheck) || !is_dir($dircheck)) {
    $errors[] = "You must specify a valid directory!";
    goto errors;
}
debug("Using dir: $dir");

// read in URLs from file
$input_filename = !empty($options['input']) ? $options['input'] : '';
$input_filename = !empty($options['i']) ? $options['i'] : $input_filename;
if (!empty($input_filename)) {
    if (!file_exists($input_filename)) {
        $errors[] = "URL input file does not exist: $input_filename";
    } else {
        // check URLs
        $urls = to_charset(file($input_filename));
        foreach ($urls as $i => $url) {
            $parts = parse_url($url);
            if (false === $parts || !array_key_exists('host', $parts)) {
                debug("Invalid URL:\n\t$url");
                unset($urls[$i]);
            }
        }
        if (empty($urls)) {
            $errors[] = "No URLs not found in input file:\n\t$input_filename";
        }
        verbose(sprintf("Found %d valid URL(s) in input file:\n\t%s", count($urls), $input_filename), $urls);
    }
    sort($urls);
    $urls = array_unique($urls);
}

// if no URLs found in file, check if single URL fed in
if (empty($urls)) {
    $url = array_key_exists('url', $options) ? $options['url'] : '';
    if (empty($url)) {
        $errors[] = "Invalid URL specified: $url";
        goto errors;
    }
    $urls = [$url];
}

$output_filename = !empty($options['filename']) ? $options['filename'] : '';

//-----------------------------------------------------------------------------
// MAIN

$data = [];
$ff = new FeedFinder();
$total_urls = count($urls);
$i = 0;
foreach ($urls as $u) {
    $i++;
    debug("Checking URL ($i/$total_urls):\n\t$u");
    $target_url = url_resolve($u);
    if (empty($target_url) || is_numeric($target_url)) {
        $errors[] = "Bad URL for:\n\t$u\n\t$target_url";
        continue;
    }
    if ($u !== $target_url) {
        $u = $target_url;
    }
    $ff->setURL($u);
    $feeds = $ff->getFeeds();
    if (empty($feeds)) {
        $data[$u] = null;
        debug("No feeds found for URL:\n\t$u");
        $errors[] = "No feeds found for:\n\t$u";
    } else {
        $data[$u] = $feeds;
        debug("Feeds found for URL:\n\t$u", $feeds);
    }
}

//-----------------------------------------------------------------------------
// final output of data

output:

// set data to write to file
if (is_array($data) && !empty($data)) {
    $output = $data;
}

// only write/display output if we have some!
if (!empty($output)) {

    if (!empty($output_filename)) {
        $file = $output_filename;
        switch (OUTPUT_FORMAT) {
            case 'php':
                $save = serialize_save($file, $output);
                if (true !== $save) {
                    $errors[] = "\nFailed encoding serialized PHP output file:\n\t$file\n";
                    goto errors;
                } else {
                    verbose(sprintf("Serialized PHP written to output file:\n\t%s (%d bytes)\n",
                            $file, filesize($file)));
                }
                break;

            case 'json':
                $save = json_save($file, $output);
                if (true !== $save) {
                    $errors[] = "\nFailed encoding JSON output file:\n\t$file\n";
                    $errors[] = "\nJSON Error: $save\n";
                    goto errors;
                } else {
                    verbose(sprintf("JSON written to output file:\n\t%s (%d bytes)\n",
                            $file, filesize($file)));
                }
                break;

            default:
            case 'txt':
                $txt = '';
                foreach ($output as $url => $feeds) {
                    $txt .= "\n$url\n";
                    if (null !== $feeds) {
                        foreach ($feeds as $url) {
                            $txt .= "\t$url\n";
                        }
                    }
                }
                file_put_contents($file, trim($txt));
                break;
        }

    }

    // output data if --echo
    if ($do['echo']) {
        switch (OUTPUT_FORMAT) {
            default:
            case 'json':
                echo json_encode(to_charset($output), JSON_PRETTY_PRINT);
                break;
            case 'php':
                echo serialize(to_charset($output));
                break;
            case 'txt':
                $txt = '';
                foreach ($output as $url => $feeds) {
                    $txt .= "\n$url\n";
                    if (null !== $feeds) {
                        foreach ($feeds as $url) {
                            $txt .= "\t$url\n";
                        }
                    }
                }
                echo to_charset(trim($txt));
                break;
        }
    }
}

// display any errors
if (!empty($errors)) {
    goto errors;
}


end:

debug(sprintf("Memory used (%s) MB (current/peak).", get_memory_used()));
output("\n");

exit;

//-----------------------------------------------------------------------------
// functions used above

/**
 * Output string, to STDERR if available
 *
 * @param  string { string to output
 * @param  boolean $STDERR write to stderr if it is available
 */
function output($text, $STDERR = true)
{
    if (!empty($STDERR) && defined('STDERR')) {
        fwrite(STDERR, $text);
    } else {
        echo $text;
    }
}


/**
 * Dump debug data if DEBUG constant is set
 *
 * @param  optional string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function debug($string = '', $data = [])
{
    if (DEBUG) {
        output(trim('[D ' . get_memory_used() . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Output string if VERBOSE constant is set
 *
 * @param  string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function verbose($string, $data = [])
{
    if (VERBOSE && !empty($string)) {
        output(trim('[V' . ((DEBUG) ? ' ' . get_memory_used() : '') . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Return the memory used by the script, (current/peak)
 *
 * @return string memory used
 */
function get_memory_used()
{
    return(
        ceil(memory_get_usage() / 1024 / 1024) . '/' .
        ceil(memory_get_peak_usage() / 1024 / 1024));
}


/**
 * check required commands installed and get path
 *
 * @param  array $requirements [][command -> description]
 * @return mixed array [command -> path] or string errors
 */
function get_commands($requirements = [])
{
    static $commands = []; // cli command paths

    $found = true;
    foreach ($requirements as $tool => $description) {
        if (!array_key_exists($tool, $commands)) {
            $found = false;
            break;
        }
    }
    if ($found) {
        return $commands;
    }

    $errors = [];
    foreach ($requirements as $tool => $description) {
        $cmd = cmd_execute("which $tool");
        if (empty($cmd)) {
            $errors[] = "Error: Missing requirement: $tool - " . $description;
        } else {
            $commands[$tool] = $cmd[0];
        }
    }

    if (!empty($errors)) {
        output(join("\n", $errors) . "\n");
    }

    return $commands;
}


/**
 * Execute a command and return streams as an array of
 * stdin, stdout, stderr
 *
 * @param  string $cmd command to execute
 * @return array|false array $streams | boolean false if failure
 * @see    https://secure.php.net/manual/en/function.proc-open.php
 */
function shell_execute($cmd)
{
    $process = proc_open(
        $cmd,
        [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w']
        ], $pipes
    );
    if (is_resource($process)) {
        $streams = [];
        foreach ($pipes as $p => $v) {
            $streams[] = stream_get_contents($pipes[$p]);
        }
        proc_close($process);
        return [
            'stdin'  => $streams[0],
            'stdout' => $streams[1],
            'stderr' => $streams[2]
        ];
    }
    return false;
}


/**
 * Execute a command and return output of stdout or throw exception of stderr
 *
 * @param  string $cmd command to execute
 * @param  boolean $split split returned results? default on newline
 * @param  string $exp regular expression to preg_split to split on
 * @return mixed string $stdout | Exception if failure
 * @see    shell_execute($cmd)
 */
function cmd_execute($cmd, $split = true, $exp = "/\n/")
{
    $result = shell_execute($cmd);
    if (!empty($result['stderr'])) {
        throw new Exception($result['stderr']);
    }
    $data = $result['stdout'];
    if (empty($split) || empty($exp) || empty($data)) {
        return $data;
    }
    return preg_split($exp, $data);
}


/**
 * Encode array character encoding recursively
 *
 * @param mixed $data
 * @param string $to_charset convert to encoding
 * @param string $from_charset convert from encoding
 * @return mixed
 */
function to_charset($data, $to_charset = 'UTF-8', $from_charset = 'auto')
{
    if (is_numeric($data)) {
        $float = (string) (float) $data;
        if (is_int($data)) {
            return (int) $data;
        } else if (is_float($data) || $data === $float) {
            return (float) $data;
        } else {
            return (int) $data;
        }
    } else if (is_string($data)) {
        return mb_convert_encoding($data, $to_charset, $from_charset);
    } else if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = to_charset($value, $to_charset, $from_charset);
        }
    } else if (is_object($data)) {
        foreach ($data as $key => $value) {
            $data->$key = to_charset($value, $to_charset, $from_charset);
        }
    }
    return $data;
}


/**
 * Load a json file and return a php array of the content
 *
 * @param  string $file the json filename
 * @return string|array error string or data array
 */
function json_load($file)
{
    $data = [];
    if (file_exists($file)) {
        $data = to_charset(file_get_contents($file));
        $data = json_decode(
            mb_convert_encoding($data, 'UTF-8', "auto"), true, 512,
            JSON_OBJECT_AS_ARRAY || JSON_BIGINT_AS_STRING
        );
    }
    if (null === $data) {
        return json_last_error_msg();
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    return $data;
}


/**
 * Save data array to a json
 *
 * @param  string $file the json filename
 * @param  array $data data to save
 * @param  string optional $prepend string to prepend in the file
 * @param  string optional $append string to append to the file
 * @return boolean true|string TRUE if success or string error message
 */
function json_save($file, $data, $prepend = '', $append = '')
{
    if (empty($data)) {
        return 'No data to write to file.';
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    if (!file_put_contents($file,
            $prepend . json_encode($data, JSON_PRETTY_PRINT) . $append)) {
        $error = json_last_error_msg();
        if (empty($error)) {
            $error = sprintf("Unknown Error writing file: '%s' (Prepend: '%s', Append: '%s')",
                $file, $prepend, $append);
        }
        return $error;
    }
    return true;
}


/**
 * Load a serialized php data file and return it
 *
 * @param  string $file the json filename
 * @return array $data
 */
function serialize_load($file)
{
    if (0 === filesize($file)) {
        return 'File is empty.';
    }
    $data = [];
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
    }
    if (false === $data) {
        return 'Unserialize failed.';
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    return $data;
}


/**
 * Save data array to a php serialized data
 *
 * @param  string $file the filename
 * @param  array $data data to save
 * @return boolean true|string TRUE if success or string error message
 */
function serialize_save($file, $data)
{
    if (empty($data)) {
        return 'No data to write to file.';
    }

    $data = to_charset($data);
    $data = serialize($data);
    if (empty($data)) {
        return 'Error serializing data.';
    } else {
        if (!file_put_contents($file, $data)) {
            $error = sprintf("Unknown Error writing file: '%s' (Prepend: '%s', Append: '%s')",
                $file, $prepend, $append);
        }
        return $error;
    }
    return true;
}


/**
 * resolve a URL/find the target of a URL
 *
 * @param  string  $url     the url to url_resolve
 * @param  array   $options options
 * @return string|int actual string URL of destination url OR curl status code
 * @see    https://ec.haxx.se/usingcurl-returns.html
 */
function url_resolve($url, $options = [])
{
    $commands = get_commands();
    $wget     = $commands['wget'];
    $curl     = $commands['curl'];

    // retry getting a url if the curl exit code is in this list
    // https://ec.haxx.se/usingcurl-returns.html
    // 6 - Couldn't resolve$ host
    $cmds['curl']['retry_exit_codes'] = [
        4, 5, 16, 23, 26, 27, 33, 42, 43,
        45, 48, 55, 59, 60, 61, 75, 76, 77, 78, 80
    ];

    // return codes from curl (url_resolve() function below) which indiciate we should not try to resolve a url
    // -22 signifies a wget failure, the rest are from curl
    $cmds['curl']['dead_exit_codes'] = [3, 6, 7, 18, 28, 47, 52, 56, -22];

    static $urls = []; // remember previous urls
    static $i;
    url_resolve_recheck: // re-check from here
    if (array_key_exists($url, $urls)) {
        if (!in_array($urls[$url], $cmds['curl']['retry_exit_codes'])) {
            return $urls[$url];
        } else if (!in_array($urls[$url], $cmds['curl']['dead_exit_codes'])) {
            return $urls[$url];
        }
        unset($urls[$url]);
    }
    $i++;
    $timeout          = !empty($options['timeout']) ? (int) $options['timeout'] : 3;
    $max_time         = !empty($options['max_time']) ? (int) $options['max_time']
            : $timeout * 10;
    $timeout          = "--connect-timeout $timeout --max-time $max_time";
    $user_agent       = ''; //'-A "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36" ';
    $curl_options     = "$user_agent $timeout --ciphers ALL -k";
    $curl_url_resolve = "curl $curl_options -I -i -Ls -w %{url_effective} -o /dev/null " . escapeshellarg($url);
    $output           = [];
    $target_url       = exec($curl_url_resolve, $output, $status);
    if ($status !== 0) {
        if (!is_numeric($target_url) && $target_url !== $url) {
            $url = $target_url;
            goto url_resolve_recheck;
        }
    }
    // same URl, loop!
    if ($target_url == $url) {
        $cmd_wget_spider = sprintf(
            "$wget --user-agent='' -t 2 -T 5 -v --spider %s",
            escapeshellarg($url)
        );
        // try wget instead
        $output          = shell_execute($cmd_wget_spider);
        if (!empty($output) && is_array($output)) {
            if (empty($output['stdin']) && empty($output['stdout']) && !empty($output['stderr'])) {
                if (false !== stristr($output['stderr'], 'broken link')) {
                    return -22;
                } else {
                    if (false !== stristr(
                            $output['stderr'],
                            'Remote file exists and could contain further links,'
                        )
                    ) {
                        return $target_url;
                    } else if (preg_match_all(
                            '/(?P<url>http[s]?:\/\/[^\s]+[^\.\s]+)/i',
                            $output['stderr'], $matches
                        )
                    ) {
                        // no URLs found
                        if (!empty($matches['url'])) {
                            foreach ($matches['url'] as $url) {
                                $found_urls[$url] = $url;
                            }
                        }
                        if (!empty($found_urls)) {
                            if ($target_url !== $url) {
                                $target_url = array_pop($found_urls);
                                $url        = $target_url;
                                goto url_resolve_recheck;
                            }
                        }
                    }
                }
            }
        }
    }
    if ($status === 0 || ($status == 6 && !is_numeric($target_url)) && !empty($target_url)) {
        $curl_http_status = "$curl $curl_options -s -o /dev/null -w %{http_code} " . escapeshellarg($target_url);
        $output           = [];
        exec($curl_http_status, $output, $status);
    }
    $return     = ($status === 0) ? $target_url : $status;
    $urls[$url] = $return; // cache in static var
    return $return;
}
