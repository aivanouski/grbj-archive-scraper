<?php
/**
 * Disclaimer
 * It's my first time using Guzzle and Symfony Crawler
 * But it's very interesting tools
 */
namespace aivanouski;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class GrbjScrapper
 */
class GrbjScrapper
{

    public $url = 'http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/';
    public $startDate;
    public $endDate;
    public $maxResultsPerAuthor = 40;
    public $concurrency = 2;
    public $wait = 0;

    private $result = [];

    /**
     * @param array $options
     * @return array $result
     */
    public function parse($options = [])
    {
        foreach ($options as $attribute => $value) {
            $this->$attribute = $value;
        }

        $concurrency = function ($pending) {
            if ($pending == 0) {
                sleep($this->wait);
                return $this->concurrency;
            } else {
                return 0;
            }
        };

        $client = new Client([
            'base_uri' => $this->url,
            'timeout' => 0,
        ]);


        # Request / or root
        $response = $client->request('GET', '/authors.html');

        $crawler = new Crawler((string)$response->getBody());

        $filter = $crawler->filter('.featured .record .author-bio .author-info');
        foreach ($filter as $content) {
            $crawlerAuthor = new Crawler($content);
            $name = $crawlerAuthor->filter('.headline > a')->text();
            $this->result[$name] = [
                'authorName' => $name,
                'authorTwitterHandle' => ($crawlerAuthor->filter('.abstract > a[href*="https://twitter.com/"]')->count()) ? $crawlerAuthor->filter('.abstract > a[href*="https://twitter.com/"]')->attr('href') : null,
                'authorBio' => trim($crawlerAuthor->filter('.abstract')->text()),
                'authorUrl' => $crawlerAuthor->filter('.headline > a')->attr('href'),
                'articles' => [],
            ];
            break;
        }

        $filter = $crawler->filter('.authors .record > a[href*="authors"]');
        foreach ($filter as $content) {
            $crawlerAuthor = new Crawler($content);
            $name = $crawlerAuthor->text();
            $this->result[$name] = [
                'authorName' => $name,
                'authorTwitterHandle' => null,
                'authorBio' => null,
                'authorUrl' => $crawlerAuthor->attr('href'),
                'articles' => [],
            ];
        }

        $authors = $this->result;
        $promises = call_user_func(function () use ($authors, $client) {
            foreach ($authors as $author) {
                yield $client->requestAsync('GET', $author['authorUrl']);
            }
        });

        $eachPromise = new EachPromise($promises, [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response) {
                $crawlerAuthor = new Crawler((string)$response->getBody());
                if ($crawlerAuthor->filter('.author-bio')->count()) {
                    $name = $crawlerAuthor->filter('.author-bio .headline > a')->text();
                    $this->result[$name] = [
                        'authorName' => $name,
                        'authorTwitterHandle' => ($crawlerAuthor->filter('.author-bio .abstract > a[href*="https://twitter.com/"]')->count()) ? $crawlerAuthor->filter('.abstract > a[href*="https://twitter.com/"]')->attr('href') : null,
                        'authorBio' => trim($crawlerAuthor->filter('.author-bio .abstract')->text()),
                        'authorUrl' => $this->url . $crawlerAuthor->filter('.author-bio .headline > a')->attr('href'),
                        'articles' => [],
                    ];
                } else {
                    $name = $crawlerAuthor->filter('#breadcrumbs a:nth-child(3)')->text();
                }

                $articlesUrl = $crawlerAuthor->filter('.author-bio .articles > a')->attr('href');

                if ($crawlerAuthor->filter('.records')->count()) {
                    $filter = $crawlerAuthor->filter('.records .record > .info');
                    foreach ($filter as $content) {

                        $currentArticlesCount = count($this->result[$name]['articles']);
                        if ($this->maxResultsPerAuthor && ($this->maxResultsPerAuthor <= $currentArticlesCount)) {
                            break;
                        }
                        $crawlerArticle = new Crawler($content);
                        $date = trim($crawlerArticle->filter('.meta .date')->text());
                        if ($this->isDatePassRules($date)) {
                            $date = date('Y-m-d', strtotime($date));
                        } else {
                            continue;
                        }
                        $this->result[$name]['articles'][] = [
                            'articleTitle' => trim($crawlerArticle->filter('.headline > a')->text()),
                            'articleDate' => $date,
                            'articleUrl' => trim($this->url, '/') . trim($crawlerArticle->filter('.headline > a')->attr('href'), '.'),
                        ];
                    }
                }
            },
        ]);

        $eachPromise->promise()->wait();

        return $this->result;
    }

    /**
     * @param $date string
     * @return bool
     */
    private function isDatePassRules($date)
    {

        $unixDate = strtotime($date);
        if ($this->startDate) {
            $unixStartDate = strtotime($this->startDate);
            if ($unixStartDate > $unixDate) {
                return false;
            }
        }

        if ($this->endDate) {
            $unixEndDate = strtotime($this->endDate);
            if ($unixEndDate < $unixDate) {
                return false;
            }
        }

        return true;
    }

}