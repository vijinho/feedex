# feedex

PHP CLI and WWW tool to extract and save feeds from URL(s)

- Full-resolves URL before attemptin to extract feed URLs
- Extracts the URLs of RSS (1.0 and 2.0) and ATOM feeds associated to a page, as well as OPML outline documents with [imelgrat/feed-finder](https://github.com/imelgrat/feed-finder)
- Runs on the command-line
- Can be called as a stand-alone webservice using the php command line built-in server
- All messages when running with `--debug` or `--verbose` are to *stderr* to avoid interference with *stdout*
- Can output the result if successful to *stdout*
- Errors are output in JSON as 'errors' with just a bunch of strings

```
{
    "errors": [
        "Unable to parse --date: next sunsaday"
    ]
}
```

## Installation

- `composer require imelgrat/feed-finder`
- `composer dump`

## Command-line options

```
Usage: php feedex.php
Extract and save feeds from URL(s)
(Specifying any other unknown argument options will be ignored.)

        -h,  --help                   Display this help and exit
        -v,  --verbose                Run in verbose mode
        -d,  --debug                  Run in debug mode (implies also -v, --verbose)
        -e,  --echo                   (Optional) Echo/output the result to stdout if successful
        -u,  --url=<url>              URL to check for feeds)
        -d,  --dir=                   (Optional) Directory for storing files (sys_get_temp_dir() if not specified)
        -i   --input={filename}       (Optional) Text file of URLs, one-per-line to read in and process.
             --filename={output}      (Optional) Filename for output data from operation
        -f   --format={txt|json|php}  (Optional) Output format for screen and filename: txt (default)|json|php(serialized)
```

## Output Format

### txt

```
URL
[TAB]FEED-URL-1
[TAB]FEED-URL-2
...
```

### json

```
{
    "URL": [
        "FEED-URL-1",
        "FEED-URL-2"
    ]
}
```

# Examples

Find feeds for URL http://example.com/blog/public outputting as 'txt'.

`php feedex.php --url=http://example.com/blog/public --echo --format=txt --debug`

output:

```
http://example.com/blog/public
        http://example.com/feed/
        http://example.com/comments/feed/
```

output as json, saving to a filename 'urls.json' and debugging enabled:

`php feedex.php --url=http://example.com/blog/public --filename=urls.json --format=json`

```

```

output with full-debugging:

```
[D 1/1] OPTIONS:
Array
(
    [debug] => 1
    [echo] => 1
    [help] => 0
    [input] => 0
    [url] => 1
    [verbose] => 1
)
[V 1/1] OUTPUT_FORMAT: txt
[D 1/1] Using dir: /Users/vijay/tmp
[D 1/1] Using input filename:
[D 1/1] Checking URL:
        http://example.com/blog/public
[D 1/2] Feeds found for URL:
        http://example.com/blog/public/
Array
(
    [0] => http://example.com/feed/
    [1] => http://example.com/comments/feed/
)
http://example.com/blog/public
        http://example.com/feed/
        http://example.com/comments/feed/
[D 1/2] Memory used (1/2) MB (current/peak).
```


## Example of reading in file of URLs

Read in file of URLs and process, outputting txt:

`php feedex.php --echo --format=txt --input=urls.txt --verbose`

```
[V] OUTPUT_FORMAT: txt
[V] Found 1 valid URL(s) in input file:
        urls.txt
Array
(
    [0] => http://example.com/blog/public/

)
http://example.com/blog/public/
        http://example.com/feed/
        http://example.com/comments/feed/
```

## Running as a webservice

### Starting the service

1. Start the PHP webserver with `php -S 127.0.0.1:12312`
2. Browse the URL: http://127.0.0.1:12312/feedex.php with GET/POST parameters

Accepted request input parameters: 'url=', 'format='

### Webservice Example


`http://127.0.0.1:12312/feedex.php?url=http://example.com/blog/public&format=json`

Result:

```
{
    "http:\/\/example.com\/blog\/public": [
        "http:\/\/example.com\/feed\/",
        "http:\/\/example.com\/comments\/feed\/"
    ]
}
```

----
vijay@yoyo.org
