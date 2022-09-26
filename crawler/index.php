<?php

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/helpers.php";
require __DIR__ . "/vendor.php";

use Smalot\PdfParser\Parser;
use LanguageDetection\Language;

ini_set("memory_limit", "10240M");
ini_set("error_reporting", E_ALL);
ini_set("display_errors", true);
ini_set("log_errors", true);
ini_set("error_log", __DIR__ . "/errors.log");

$crawlerPath = __DIR__ . "/crawler.log";
$starttimeLogPath = __DIR__ . "/starttime.log";

exec("(sleep 14400  && kill -9 ".getmypid().") > /dev/null 2>&1 &");

if (!file_exists($crawlerPath)) {
    touch($crawlerPath);
}
if (!file_exists($starttimeLogPath)) {
    touch($starttimeLogPath);
}

file_put_contents($starttimeLogPath, date("Y-m-d H:i:s") . " crawler started\n\n", FILE_APPEND);

$GLOBALS["outdir"] = "/public/upload/publications";

class Crawler {
    // if the difference between top lang score and en score is small, then it's probably english
    // e.g.: in one case, norwegian got a score with less than 0.01 more than english, but the document was english
    const TOP_LANG_SCORE_DIFF_FOR_EN = 0.015;
    const VALID_LANGS = ['nl', 'en', 'fr', 'de', 'it'];

    protected $dbCredentials;
    protected $parser;
    protected $pdf_list;
    protected $visited_url_list;
    protected $metadata;

    public function __construct() {
        $this->dbCredentials = (object) [
            "dsn" => "mysql:host=localhost;dbname=name",
            "username" => '',
            "password" => '',
            "options" => [],
        ];

        $this->parser = new Parser();
        $this->pdf_list = [];
        $this->visited_url_list = [];
        $this->metadata = $this->get_empty_metadata();
    }

    public function get_empty_metadata() {
        return (object) [
            "Author" => null,
            "CreationDate" => null,
            "ModDate" => null,
            "Creator" => null,
            "Producer" => null,
            "Title" => null,
            "Description" => null,
            "Language" => null,
            "Keywords" => null,
            "Subject" => null,
            "Success" => 0,
            "Reason" => null,
        ];
    }

    public function get_pdf_metadata($fname, $slug = null) {
        echo "getting metadata for filename '$fname' with slug '$slug'...\n";

        $retval = $this->get_empty_metadata();

        try {
            $pdf = $this->parser->parseFile($fname);
            $info = $pdf->getDetails();

            $lang = (function() use ($pdf) {
                $pages = $pdf->getPages();
                if (isset($pages) && isset($pages[0])) {
                    $textSample = $pages[0]->getText();
                    $textSample = str_replace("\r\n", " ", $textSample);
                    $textSample = str_replace("\n", " ", $textSample);
                    $textSample = str_replace("\t", " ", $textSample);
                    $textSample = preg_replace('!\s+!', ' ', $textSample);
                    $ld = new Language();
                    $rawLangDetection = $ld->detect($textSample)->close();
                    $langDetection = [];
                    foreach ($rawLangDetection as $lang => $score) {
                        if (!in_array($lang, Crawler::VALID_LANGS)) {
                            continue;
                        }
                        $langDetection[$lang] = $score;
                    }
                    if (!count($langDetection) || array_values($langDetection)[0] < 0.38) {
                        if (count($langDetection)) {
                            dump("failed score: " . array_values($langDetection)[0]);
                        }
                        return null;
                    }
                    if (!isset($langDetection['en']) || array_keys($langDetection)[0] === 'en') {
                        dump("score: " . array_values($langDetection)[0]);
                        return array_keys($langDetection)[0];
                    }
                    if (array_values($langDetection)[0] - $langDetection['en'] <= Crawler::TOP_LANG_SCORE_DIFF_FOR_EN) {
                        dump("en score: " . $langDetection['en']);
                        dump("max score: " . array_values($langDetection)[0]);
                        return 'en';
                    }
                    dump("max score: " . array_values($langDetection)[0]);
                    return array_keys($langDetection)[0];
                }
            })();
            $lang = $lang ? "\"$lang\"" : null;
            dump($lang);

            $retval->Author = isset($info['Author']) ? $info['Author'] : null;
            $retval->CreationDate = isset($info['CreationDate']) ? $info['CreationDate'] : null;

            // fail if no creation date
            if (!isset($retval->CreationDate) || !$retval->CreationDate || !is_string($retval->CreationDate)) {
                $this->metadata = $this->get_empty_metadata();
                return null;
            }

            try {
                if (isset($retval->CreationDate) && is_array($retval->CreationDate) && count($retval->CreationDate)) {
                    $retval->CreationDate = $retval->CreationDate[0];
                }
                // fail if no creation date
                if (!isset($retval->CreationDate) || !$retval->CreationDate) {
                    $this->metadata = $this->get_empty_metadata();
                    return null;
                }
            } catch (Exception $ex) {
                dump("metadata failed: " . $ex->getMessage() . ".\n");
                $this->metadata = $this->get_empty_metadata();
                return null;
            }

            $retval->ModDate = isset($info['ModDate']) ? $info['ModDate'] : null;
            $retval->Creator = isset($info['Creator']) ? $info['Creator'] : null;
            $retval->Producer = isset($info['Producer']) ? $info['Producer'] : null;
            $retval->Title = isset($info['Title']) ? $info['Title'] : null;

            // attempt to get unslugify slug as title if we have creation date
            try {
                if (isset($retval->Title) && is_array($retval->Title) && count($retval->Title)) {
                    $retval->Title = $retval->Title[0];
                }
                if (!isset($retval->Title) || !$retval->Title) {
                    // unslugify here
                    $retval->Title = $this->unslugify($slug);
                }
            } catch (Exception $ex) {
                dump("metadata failed: " . $ex->getMessage() . ".\n");
                $this->metadata = $this->get_empty_metadata();
                return null;
            }

            $retval->Description = isset($info['Description']) ? $info['Description'] : null;
            $retval->Keywords = isset($info['Keywords']) ? $info['Keywords'] : null;
            $retval->Subject = isset($info['Subject']) ? $info['Subject'] : null;

            try {
                $retval->Success = $retval->CreationDate && is_string($retval->CreationDate)
                && $retval->Title && is_string($retval->Title)
                    ? 1 : 0;
                $this->metadata = $retval;
                $this->metadata->Language = $lang;
                return null;
            } catch (Exception $ex) {
                dump("metadata failed: " . $ex->getMessage() . ".\n");
                $this->metadata = $this->get_empty_metadata();
                return null;
            }

        } catch (Exception $ex) {
            dump("metadata failed: " . $ex->getMessage() . ".\n");
            $this->metadata = $this->get_empty_metadata();
            return null;
        }
    }

    public function get_pdf($url, $outdir, $source_id, $policy_id = null) {
        $mech = new Mechanize();
        $mech->agent("Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36");
        $url = preg_replace("/hhttp/", "http", $url);

        echo "Getting pdf for $url\n";

        try {

            if ($this->url_exists($url)) {
                echo "URL $url already exists\n";
                return null;
            }

            echo "file url: $url\n";
            $filename = $this->normalize_filename(basename($url));

            $result = $mech->get($url, $filename);
            if (!$result->success()) {
                echo $result->status() . "\n";
                return null;
            }

            try {
                if (!file_exists($filename)) {
                    "\nfile '$filename' does not exist\n";
                    $this->reject_url($url, $source_id, null, "Failed to get metadata");
                    unlink("/tmp/$filename");
                    return null;
                }
                echo "file '$filename' has " . filesize($filename) . " bytes\n";
            } catch (Exception $ex) {
                dump($ex->getMessage());
                $this->reject_url($url, $source_id, null, "Failed to get metadata");
                unlink("/tmp/$filename");
                return null;
            }
            $slug = $this->normalize_string($this->slug_replacement($filename));
            $this->get_pdf_metadata($filename, $slug);

            try {
                if ($this->metadata->Success !== 1) {
                    $this->reject_url($url, $source_id, $slug, "Failed to get metadata");
                    unlink("/tmp/$filename");
                    return null;
                }
            } catch (Exception $ex) {
                dump($ex->getMessage());
                $this->reject_url($url, $source_id, $slug, "Failed to get metadata");
                unlink("/tmp/$filename");
                return null;
            }

            if (file_exists("$outdir/$slug.pdf")) {
                echo "file '$outdir/$slug.pdf' exists, although it should not, rejecting...\n";
                $this->reject_url($url, $source_id, $slug, "Failed to get metadata");
                unlink("/tmp/$filename");
                return null;
            } else {
                echo "slug $slug\n";
                echo "renaming '$filename' to '$outdir/$slug.pdf'\n";
                rename($filename, "$outdir/$slug.pdf");
                chmod("$outdir/$slug.pdf", 0777);
                if ($this->metadata->Success === 0) {
                    echo "$url is not a valid PDF file\n";

                    if (!isset($slug) || !$slug) {
                        $this->reject_url($url, $source_id, null, "Failed to get metadata");
                        unlink("$outdir/$slug.pdf");
                        return null;
                    }
                }

                if ($this->metadata->Success === 0 || !$this->metadata->Title) {
                    $this->reject_url($url, $source_id, $slug, "Failed to get metadata");
                    unlink("$outdir/$slug.pdf");
                    return null;
                }

                $currentYear = (int) date("Y");
                if ($this->metadata->CreationDate) {
                    $year = (int) mb_substr($this->metadata->CreationDate, 0, 4);
                    echo "detected year is $year\n";
                    if ($currentYear - $year > 1) {
                        echo "Older than 1 year, reject it\n";
                        $this->reject_url($url, $source_id, $slug, "Too old (older than 1 year)");
                        unlink("$outdir/$slug.pdf");
                        return null;
                    }
                } else {
                    echo "year not detected\n";
                }

                $dbh = new Database($this->dbCredentials);
                $url = $this->escape_quotes($url);
                $this->metadata->Title = $this->escape_quotes($this->metadata->Title);
                $orgId = $this->get_org($source_id);

                $title = $this->metadata->Title;
                if ( !$title ) {
                    $title = ucwords(str_replace('-', ' ', str_replace('_', ' ', str_replace('.pdf', '', $slug))));
                }

                $description = $this->metadata->Description;
                $creationDate = $this->mkdatetime($this->metadata->CreationDate);
                $sourceId = $source_id ?: 'null';
                $policyId = $policy_id ? (int)$policy_id : 0;
                $lang = $this->metadata->Language;

                echo "Org ID: $orgId\n";
                $query = "INSERT INTO `api_publications` SET
                    `user_id` = 0,
                    `organisation_id` = $orgId,
                    `title` = \"$title\",
                    `slug` = \"$slug\",
                    `description` = \"$description\",
                    `language` = $lang,
                    `body` = '',
                    `type_id` = null,
                    `policy_id` = $policyId,
                    `thumb` = null,
                    `file` = '$slug.pdf',
                    `source_is_file` = 1,
                    `views` = null,
                    `source_id` = $sourceId,
                    `source` = \"$url\",
                    `premium` = null,
                    `created` = '$creationDate',
                    `updated` = null,
                    `deleted` = null,
                    `published` = NOW(),
                    `shared_tw` = 0,
                    `shared_fb` = 0,
                    `reject_reason` = 'OK'";

                $statement = $dbh->prepare($query);
                $statement->execute();
                return null;
            }
        } catch (Exception $ex) {
            dump($ex->getMessage());
            return null;
        }
    }

    public function get_org($source_id) {
        try {
            $dbh = new Database($this->dbCredentials);
            $query = "SELECT `organisation_id` FROM `api_publication_sources` WHERE `id` = '$source_id'";
            $statement = $dbh->prepare($query);
            $statement->execute();
            $data = $statement->fetch();
            return isset($data[0]) ? $data[0] : 0;
        } catch (Exception $ex) {
            dump($ex->getMessage());
            return "null";
        }
    }

    public function reject_url($url, $source_id, $slug, $reason) {
        $oldDate = date("Y-m-d H:i:s", 1262304000);

        $source_id = isset($source_id) && $source_id ? $source_id : 'null';
        echo "rejecting <<url: '$url', source id: '$source_id', slug: '$slug', reason: '$reason'>>\n";
        $slug = preg_replace("/\%/", "\\\%", $slug);
        $url = preg_replace("/\%/", "\\\%", $url);
        $url = preg_replace("/\'/", "\\\'", $url);
        $dbh = new Database($this->dbCredentials);
        $query = "INSERT INTO `api_publications` (id, user_id, organisation_id, title, slug, description, body, type_id, policy_id, thumb, file, source_is_file, views, source_id, source, premium, created, updated, deleted, published, shared_tw, shared_fb, reject_reason) VALUES (null, null, null, '', '$slug','', '',null, 0, null,  '',1,null, $source_id, '$url', null, '$oldDate', null, NOW(), NOW() ,0 ,0, '$reason')";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }

    public function escape_quotes($str) {
        $str = preg_replace("/'/", "\'", $str);
        $str = preg_replace("/'/", "'", $str);
        $str = preg_replace('/"/', '\\"', $str);
        return $str;
    }

    public function mkdatetime($datetimestr) {
        $retval = date("Y-m-d H:i:s", 1262304000);
        $datetimestr = mb_substr($datetimestr, 0, 19);
        $datetimestr = str_replace("T", " ", $datetimestr);
        $matches = null;
        if (preg_match("/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/", $datetimestr, $matches)) {
            $retval = $matches[1] . "-" . $matches[2] . "-" . $matches[3] . " " . $matches[4] . ":" . $matches[5] . ":" . $matches[6];
        }
        return $retval;
    }

    public function url_exists($url) {
        $retval = 0;
        if ($url) {
            $dbh = new Database($this->dbCredentials);
            $url = $this->escape_quotes($url);
            $query = "SELECT `source` from `api_publications` WHERE source = '$url'";
            $statement = $dbh->prepare($query);
            $statement->execute();
            $retval = $statement->rowCount() > 0;
        }
        return $retval;
    }

    public function slugify($filename) {
        if (!isset($filename) || !$filename) {
            $filename = "untitled";
        }

        echo "slugifying $filename\n";

        do {
            if (!isset($str)) {
                $str = $filename;
            }
            echo "checking '$str' slug availability...\n";
            $dbh = new Database($this->dbCredentials);
            $query = "SELECT `slug` FROM `api_publications` WHERE slug = '$str'";
            $statement = $dbh->prepare($query);
            $statement->execute();
            if (!$statement->rowCount()) {
                return $str;
            }
            $str = $filename . "-" . $this->random_string();
        } while ($statement->rowCount());

        exit("critical failure.");
    }

    public function slug_replacement($filename) {
        $result = $filename;
        if (!isset($result) || !$result) {
            $result = "untitled";
        }
        echo "slug replacement with '$result'\n";
        return $result;
    }

    public function crawl($url, $topurl, $outdir, $source_id, $prevurl, $policy_id = null) {
        $topurl = trim(mb_strtolower($topurl), " /");
        $topurl = str_replace("www.", "", $topurl);
        $topurl = str_replace("http://", "", $topurl);
        $topurl = str_replace("https://", "", $topurl);

        $url2 = $url;
        $url2 = preg_replace("/\&/", "\\\&", $url2);
        $url2 = preg_replace("/\[/", "\\\[", $url2);

        $url2Quote = $this->preg_quote($url2);
        if (preg_grep("/$url2Quote/", $this->visited_url_list)) {
            return null;
        }

        if (mb_strstr($url, $topurl) === -1) {
            echo date("Y-m-d H:i:s") . " external domain. url: '$url'; top url: '$topurl'.\n";
            return null;
        }
        if ($url === $prevurl) {
            echo date("Y-m-d H:i:s") . " url '$url' already parsed.\n";
            return null;
        }

        $this->visited_url_list[] = $url;

        $mech = new Mechanize();
        // $mech->agent("Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_4; en-us) AppleWebKit/533.17.8 (KHTML, like Gecko) Version/5.0.1 Safari/533.17.8");
        $mech->agent("Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36");
        $result = $mech->get($url);
        $resultCode = $result->code;

        echo date("Y-m-d H:i:s") . " $url $resultCode\n";

        if (!$resultCode || $resultCode[0] !== "2") {
            echo date("Y-m-d H:i:s") . " failed to get the url, remote responded with code '$resultCode'\n";
        }

        $urlQuote = $this->preg_quote($url);
        $urlQuote2 = $this->preg_quote(str_replace("www.", "", $url));
        $urlQuote3 = $this->preg_quote(str_replace("http://", "https://", $url));
        $urlQuote4 = $this->preg_quote(str_replace("https://", "http://", $url));
        $links = $result->find_all_links("/^($urlQuote)|($urlQuote2)|($urlQuote3)|($urlQuote4)/");

        $links2 = $result->find_all_links("/\.pdf$/");

        foreach ($links2 as $link) {
            $this->pdf_list[$link->url_abs] = true;
        }

        foreach ($links as $link) {
            if ($link->url_abs !== $link->base) {
                try {
                    $this->crawl($link->url_abs, $topurl, $outdir, $source_id, $url, $policy_id);
                } catch (Exception $ex) {
                    dump(date("Y-m-d H:i:s") . "crawl failed: " . $link->url_abs . ": " . $ex->getMessage() . ".\n");
                }
            }
        }
    }

    public function normalize_string($str) {
        $str = normalizer_normalize($str, Normalizer::FORM_KD); // normalize the unicode string
        $str = remove_accents($str); // remove accents from characters
        $str = preg_replace("//", "", $str); // strip non-ASCII characters (>127)
        $str = preg_replace("/[^\.\w\s-]/", "", $str); // remove all non-word characters
        $str = mb_strtolower($str); // lowercase
        $str = preg_replace("/[-\s]+/", "-", $str); // replace spaces and hyphens with a single hyphen
        $str = preg_replace("/^-|-$/", "", $str); // trim hyphens from both ends
        $str = str_replace('_', '-', $str); // replace '_' with '-'
        $str = str_replace('.pdf', '', $str); // remove .pdf from string
        $str = str_replace('.', '-', $str); // replace '.' with '-'
        return $str;
    }

    public function normalize_filename($str) {
        $result = $str;
        $result = preg_replace('/[^\w\._\-]/', '-', $result);
        $result = preg_replace('/-{2,}/','-', $result);
        $result = preg_replace('/_{2,}/','_', $result);
        $result = preg_replace('/\.{2,}/','.', $result);
        $result = trim($result, " ._-");
        echo "normalizing filename '$str' to '$result'\n";
        return $result;
    }

    public function unslugify($str) {
        $str = preg_replace('/[^\w]/', ' ', $str);
        $str = str_replace("_", " ", $str);
        $str = ucwords($str);
        $str = preg_replace('/ Pdf$/', '', $str);
        return $str;
    }

    public function preg_quote($str) {
        return preg_quote($str, "/");
    }

    public function random_string($length = 5) {
        $length = (int) $length;
        if ($length < 1) {
            $length = 1;
        }
        $seed = explode(' ' , 'a b c d e f g h i j k l m n o p q r s t u v w x y z 0 1 2 3 4 5 6 7 8 9');
        $rand = '';
        foreach (array_rand($seed, (int) $length) as $k) {
            $rand .= $seed[$k];
        }
        return $rand;
    }

    //

    public function last_publication_source_id() {
        $filepath = __DIR__ . "/last-publication-source-id.txt";

        if (!file_exists($filepath)) {
            file_put_contents($filepath, "0");
            return 0;
        }

        return (int) file_get_contents($filepath);
    }

    public function runtime($argv) {
        echo "\n";

        $outdir = $GLOBALS["outdir"];

        chdir("/tmp");

        dump("outdir is '$outdir'");
        dump("tmp folder is '/tmp'");

        if (file_exists(__DIR__ . "/cookie.txt")) {
            unlink(__DIR__ . "/cookie.txt");
        }

        touch(__DIR__ . "/cookie.txt");
        touch(__DIR__ . "/errors.log");

        if (empty($argv)) {
            $lastPublicationSourceId = $this->last_publication_source_id();

            $dbh = new Database($this->dbCredentials);
            $query = "SELECT `website`, `id`, `policy_id` FROM `api_publication_sources`
                WHERE `id` > $lastPublicationSourceId AND `status` = 1
                UNION SELECT `website`, `id`, `policy_id` FROM `api_publication_sources`
                WHERE `id` <= $lastPublicationSourceId AND `status` = 1";
            $statement = $dbh->prepare($query);
            $statement->execute();
            $sources = $statement->fetchAll();

            foreach ($sources as $data) {
                echo date("Y-m-d H:i:s") . " crawling url " . $data[0] . "\n";
                file_put_contents(__DIR__ . "/last-publication-source-id.txt", (string) $data[1]);
                try {
                    $this->crawl($data[0], "output.csv", $data[0], $outdir, $data[1], $data[2]);
                } catch (Exception $ex) {
                    dump(date("Y-m-d H:i:s") . "crawl failed: " . $data[0] . ": " . $ex->getMessage() . ".\n");
                }

                $pdfCount = count($this->pdf_list);
                echo "found $pdfCount pdf files.\n";

                foreach (array_keys($this->pdf_list) as $url) {

                    try {
                        $targetUrl = str_ireplace('www.', '', parse_url($data[0], PHP_URL_HOST));
                        if (!str_contains($url, $targetUrl)) {
                            echo "skipped: PDF URL $url out of target URL $targetUrl\n";
                            continue;
                        }
                        $this->get_pdf($url, $outdir, $data[1], $data[2]);
                    } catch (Exception $ex) {
                        dump(date("Y-m-d H:i:s") . "get_pdf failed: " . $url . ": " . $ex->getMessage() . ".\n");
                    }

                }

                $this->pdf_list = [];
            }
            return null;
        }

        echo date("Y-m-d H:i:s") . " crawling " . $argv[0] . "\n";
        $this->crawl($argv[0], "output.csv", $argv[0], $outdir, isset($argv[1]) ? $argv[1] : null, isset($argv[2]) ? $argv[2] : null);
        foreach (array_keys($this->pdf_list) as $url) {
            try {
                $targetUrl = str_ireplace('www.', '', parse_url($argv[0], PHP_URL_HOST));
                if (!str_contains($url, $targetUrl)) {
                    echo "skipped: PDF URL $url out of target URL $targetUrl\n";
                    continue;
                }
                $this->get_pdf($url, $outdir, isset($argv[1]) ? $argv[1] : null, isset($argv[2]) ? $argv[2] : null);
            } catch (Exception $ex) {
                dump(date("Y-m-d H:i:s") . "get_pdf failed: " . $url . ": " . $ex->getMessage() . ".\n");
            }

        }

        echo "\n";
    }
}

array_shift($argv);
(new Crawler())->runtime($argv);
