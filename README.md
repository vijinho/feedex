# feedex

PHP CLI and WWW tool to extract and save feeds from URL(s)

- Full-resolves URL before attempting to extract feed URLs
- Extracts the URLs of RSS (1.0 and 2.0) and ATOM feeds associated to a page, as well as OPML outline documents with [nicolus/picofeed](https://github.com/nicolus/picoFeed)
- Runs on the command-line
- Can be called as a stand-alone webservice using the php command line built-in server
- All messages when running with `--debug` or `--verbose` are to *stderr* to avoid interference with *stdout*
- Can output the result if successful to *stdout*
- Output file can be used as input when using options 'txt' and '[json](https://json.org/)', 'opml' and URLs can be re-checked with --force option.
- Can output found feeds as an [opml](http://dev.opml.org/) file.
- Can output data to markdown .md for easy sharing of podcast information (output-only --format)
- Errors are output in JSON as 'errors' with just a bunch of strings

```
{
    "errors": [
        "Unable to parse --date: next sunsaday"
    ]
}
```

## Installation

- `composer require nicolus/picofeed`
- `composer dump`

## Command-line options

```
Usage: php feedex.php
Extract and save feeds from URL(s)
(Specifying any other unknown argument options will be ignored.)

        -h,  --help                        Display this help and exit
        -v,  --verbose                     Run in verbose mode
        -d,  --debug                       Run in debug mode (implies also -v, --verbose)
        -u,  --url=<url>                   (Required or -i) URL to check for feeds)
        -i   --input={filename}            (Required or -u) Text file of URLs, one-per-line to read in and process.
        -c,  --clear                       (Optional) Clear-out URLs which have no feeds before writing output file.
        -e,  --echo                        (Optional) Echo/output the result to stdout if successful
        -f   --format={txt|json|php|opml}  (Optional) Output format for screen and filename: txt (default)|json|php(serialized)|opml
             --filename={output}           (Optional) Filename for output data from operation
             --force-check                 (Optional) Forcibly check URLs, even for those which already have feeds in the input file.
 ```

## Example output Format

The script can take-in previous txt format output and re-use it as input, checking only urls where there are no existing feeds.

### txt

Only stand-alone URL lines without following <TAB> feed lines are searched for feeds, unless `--force-check` is used which forces all URLs to be checked in the text file.


```
http://campaigntoabolishthebbc.blogspot.com/
        http://campaigntoabolishthebbc.blogspot.com/feeds/posts/default
        http://campaigntoabolishthebbc.blogspot.com/feeds/posts/default?alt=rss
        https://www.blogger.com/feeds/7418166530762317285/posts/default

http://cash-is-cool.com/
        http://cash-is-cool.com/rss/articles.php
        http://cash-is-cool.com/rss/twitter.php

http://datastori.es/
        http://datastori.es/comments/feed/
        http://datastori.es/feed/
        http://datastori.es/feed/m4a/
        http://datastori.es/feed/mp3/
        http://datastori.es/feed/podcast/

http://eurofolkradio.com/
        http://eurofolkradio.com/comments/feed/
        http://eurofolkradio.com/feed/
        http://eurofolkradio.com/feed/podcast

http://grahamhancock.com/blog/
        https://grahamhancock.com/blog/feed/
```

### json

```
{
    "http:\/\/campaigntoabolishthebbc.blogspot.com\/": [
        "http:\/\/campaigntoabolishthebbc.blogspot.com\/feeds\/posts\/default",
        "http:\/\/campaigntoabolishthebbc.blogspot.com\/feeds\/posts\/default?alt=rss",
        "https:\/\/www.blogger.com\/feeds\/7418166530762317285\/posts\/default"
    ],
    "http:\/\/cash-is-cool.com\/": [
        "http:\/\/cash-is-cool.com\/rss\/articles.php",
        "http:\/\/cash-is-cool.com\/rss\/twitter.php"
    ],
    "http:\/\/datastori.es\/": [
        "http:\/\/datastori.es\/comments\/feed\/",
        "http:\/\/datastori.es\/feed\/",
        "http:\/\/datastori.es\/feed\/m4a\/",
        "http:\/\/datastori.es\/feed\/mp3\/",
        "http:\/\/datastori.es\/feed\/podcast\/"
    ],
    "http:\/\/eurofolkradio.com\/": [
        "http:\/\/eurofolkradio.com\/comments\/feed\/",
        "http:\/\/eurofolkradio.com\/feed\/",
        "http:\/\/eurofolkradio.com\/feed\/podcast"
    ],
    "http:\/\/grahamhancock.com\/blog\/": [
        "https:\/\/grahamhancock.com\/blog\/feed\/"
    ],
    "http:\/\/greatgameindia.com\/": [
        "http:\/\/greatgameindia.com\/comments\/feed\/",
        "http:\/\/greatgameindia.com\/feed\/",
        "http:\/\/greatgameindia.com\/homepage\/feed\/"
    ],
}
```

### OPML file to Markdown

`php feedex.php --input=audio.opml -e -fmd --filename=podcasts.md`

Example output, slightly modified: [urunu.com/blog/2018-10-18-podcasts](http://www.urunu.com/blog/2018-10-18-podcasts)

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

Read in file of URLs and process, outputting txt, piping screen output (stderr and stdout) to less

`php feedex.php --input=urls.txt --filename=results.txt --verbose --echo --clear 2>&1 | less`

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

## Example of creating OPML file from extracted subscriptions

Reads in 'urls.txt', extracts feeds and writes to 'results.opml', debug mode piping all output including *stderr* to *stdout* into *less* viewer

`php feedex.php --input=urls.txt --filename=results.opml --echo --format=opml --debug 2>&1 | less`

Contents of 'results.opml':

```
<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
  <head>
    <title>FeedEx</title>
  </head>
  <body>
    <outline type="rss" text="Stories by RT Digital on Medium" xmlUrl="https://medium.com/feed/@rtdublindigital" title="Stories by RT Digital on Medium" description="Stories by RT Digital on Medium" htmlUrl="https://medium.com/@rtdublindigital?source=rss-dbca3f85c5a3------2"/>
    <outline type="rss" text="ChinaPower Project" xmlUrl="https://chinapower.csis.org/feed/" title="ChinaPower Project" description="Unpacking the complexity of China's rise" htmlUrl="https://chinapower.csis.org/"/>
<!-- SNIP! -->
  </body>
</opml>    
```

## Running as a webservice

### Starting the service

1. Start the PHP webserver with `php -S 127.0.0.1:12312`
2. Browse the URL: http://127.0.0.1:12312/feedex.php with GET/POST parameters

Accepted request input parameters: 'url=', 'format='

### Webservice Example

This will only check a single URL.

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

Using `format=text`

`http://127.0.0.1:12312/feedex.php?url=http://example.com/blog/public&format=txt`

```
http://example.com.com/
	http://example.com.com/comments/feed/
	http://example.com.com/feed/
	http://example.com.com/home/feed/
```

----
vijay@yoyo.org
