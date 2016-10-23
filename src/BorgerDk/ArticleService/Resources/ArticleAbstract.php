<?php

namespace BorgerDk\ArticleService\Resources;

use BorgerDk\ArticleService;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Abstract class for all endpoints returning full articles.
 */
abstract class ArticleAbstract extends ResourceAbstract
{
    /**
     * Return the raw result from the service endpoint.
     *
     * @return object
     */
    public function getResultFormatted()
    {
        $items = array();

        if (is_array($this->resourceResult->Article)) {
            foreach ($this->resourceResult->Article as $id => $article) {
                $article_item = $this->formatSingleArticle($article);
                $items[$article_item->id] = $article_item;
            }
        }
        else {
            $article_item = $this->formatSingleArticle($this->resourceResult);
            $items[$article_item->id] = $article_item;
        }

        return $items;
    }

    /**
     * Prepare an object for a single article with the raw data extracted from article HTML.
     *
     * @param object $article
     *
     * @return \stdClass
     */
    private function formatSingleArticle($article)
    {
        $data = new \stdClass();
        $html = utf8_decode($article->Content);
        $crawler = new Crawler($html);

        // Basic article data
        $data->id = $article->ArticleID;
        $data->title = html_entity_decode($article->ArticleTitle, ENT_NOQUOTES, 'UTF-8');
        $data->header = html_entity_decode($article->ArticleHeader, ENT_NOQUOTES, 'UTF-8');
        $data->url = $article->ArticleUrl;
        $data->lastUpdated = $article->LastUpdated;
        $data->publishDate = $article->PublishingDate;

        // Extract self service links
        $data->selfServiceLinks = $crawler->filter('#selvbetjeningslinks > ul > li')->each(function (Crawler $node, $i) {
            $link = new \stdClass();
            $link->id = $this->getAttributeId($node);
            $link->url = $node->filter('a')->attr('href');
            $link->label = trim($node->filter('a')->text());
            $link->title = trim($node->filter('a')->attr('title'));
            return $link;
        });

        // Micro articles
        $data->microArticles = $crawler->filter('#kernetekst > div')->each(function (Crawler $node, $i) {
            $link = new \stdClass();
            $link->headline = trim($node->filter('h2')->text());
            $link->content = trim($node->filter('div > div')->html());
            return $link;
        });

        // Legislation
        $node = $crawler->filter('#lovgivning');
        if ($node->count()) {
            $data->legislation = new \stdClass();
            $data->legislation->headline = trim($node->filter('h3')->text());
            $data->legislation->content = trim($node->filter('div > div')->html());
        }

        // Recommendation
        $node = $crawler->filter('#anbefaler');
        if ($node->count()) {
            $data->recommendation = new \stdClass();
            $data->recommendation->headline = trim($node->filter('h3')->text());
            $data->recommendation->content = trim($node->filter('div > div')->html());
        }

        // Byline
        $node = $crawler->filter('#byline');
        if ($node->count()) {
            $data->byline = trim($node->text());
        }

        return $data;
    }

    /**
     * Extract guid for a specific node id attribute.
     *
     * @param Crawler $node
     *
     * @return string
     */
    private function getAttributeId(Crawler $node)
    {
        return join('', array_slice(explode('_', $node->attr('id')), -1));
    }
}