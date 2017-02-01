<?php
namespace aivanouski;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class GrbjScrapper
 */
class GrbjScrapper
{
    public $startDate;
    public $endDate;
    public $maxResultsPerAuthor = 40;
    public $concurrency = 2;
    public $wait = 5;

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

        $client = new Client([
            'base_uri' => 'http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/',
        ]);

        # Request / or root
        $response = $client->request('GET', '/authors.html');

        $crawler = new Crawler((string) $response->getBody());

        $filter = $crawler->filter('.featured .record .author-bio .author-info');
        foreach ($filter as $content) {
            $crawlerAuthor = new Crawler($content);
            $this->result[] = array(
                'authorName' => $crawlerAuthor->filter('.headline > a')->text(),
                'authorTwitterHandle' => ($crawlerAuthor->filter('.abstract > a')->count()) ? $crawlerAuthor->filter('.abstract > a')->attr('href') : null,
                'authorBio' => trim($crawlerAuthor->filter('.abstract')->text()),
                'authorUrl' => $crawlerAuthor->filter('.headline > a')->attr('href'),
                //'articles' => $crawler->filter('headline > a')->text(),
            );
        }

        return $this->result;
    }

}