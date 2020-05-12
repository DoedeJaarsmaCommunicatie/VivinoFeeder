<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Automattic\WooCommerce\Client;
use Illuminate\Support\Facades\Storage;

class GenerateXMLCommand extends Command
{
    const yearID = 6;
    const grapeID = 3;
    const domainID = 1;
    const classID = 2;

    /**
     * @var null|Client
     */
    protected static $client = null;

    protected $signature = 'make:xml';

    protected $description = 'Generates an XML file to use in the Vivino feed';

    public function handle()
    {
        $root = $this->generateXml();

        $this->loopProducts($root, 10);
        $lastResponse = static::$client->http->getResponse();
        $pages = (int) $lastResponse->getHeaders()['x-wp-totalpages'];

        for ($i = 2; $i <= $pages; $i++) {
            $this->line('Starting on page: ' . $i);
            $this->loopProducts($root, 10, $i);
            $this->line('Finished with page: ' . $i);
        }

        Storage::put('vivinofeed.xml', $root->asXML());
    }

    /**
     * @return \SimpleXMLElement
     */
    private function generateXml(): \SimpleXMLElement
    {
        return new \SimpleXMLElement('<vivino-product-list />');
    }

    /**
     * @param \SimpleXMLElement $root
     * @param int $limit
     * @param int $page
     */
    private function loopProducts(&$root, int $limit = 10, int $page = 1): void
    {
        foreach ($this->getProducts($limit, $page) as $product) {
            if ($product->catalog_visibility !== 'visible' ||
                Str::contains($product->name, ['Wijnkistje', 'Wijnglas', 'wijnzak', 'Fijnproeverspakket', 'proef', 'wijnglas', 'wijnglazen'])) {
                continue;
            }

            $productLine = $root->addChild('product');
            $productLine->addChild('product-name', static::getProductName($product));
            $productLine->addChild('price', $product->regular_price);
            $productLine->addChild('bottles', '1')->addAttribute('size', '750ml');
            $productLine->addChild('link', $product->permalink);
            $productLine->addChild('inventory-count', $product->stock_quantity ?? 200);
        }
    }

    private function getProducts(int $limit = 10, int $page = 1)
    {
        $client = static::getClient();

        $res = $client->get('products', ['per_page' => $limit, 'page' => $page]);

        return $res;
    }

    /**
     * @return Client
     */
    protected static function getClient(): Client
    {
        if (null !== static::$client) {
            return static::$client;
        }

        return static::$client = new Client(
            config('app.store.url'),
            config('app.store.key'),
            config('app.store.secret'),
            [
                'version'   => config('app.store.version')
            ]
        );
    }

    /**
     * @param \stdClass $product
     *
     * @return string
     */
    protected static function getProductName($product)
    {
        $name = str_replace('-', '', $product->name);
        $attributes = collect($product->attributes);
        $domain = $attributes->filter(static function ($item) {
            return $item->id === GenerateXMLCommand::domainID;
        });
        $classification = $attributes->filter(static function ($item) {
            return $item->id === GenerateXMLCommand::classID;
        });
        $year = $attributes->filter(static function ($item) {
            return $item->id === GenerateXMLCommand::yearID;
        });

        if ($year->isNotEmpty()) {
            if (Str::contains($name, $year->first()->options[0])) {
                $name = trim(str_replace($year->first()->options[0], '', $name));
            }
        }

        if ($domain->isNotEmpty()) {
            if (Str::contains($name, $domain->first()->options[0])) {
                $name = trim(str_replace($domain->first()->options[0], '', $name));
                $name = Str::start($name, $domain->first()->options[0] . ' ');
            }
        }

        if ($classification->isNotEmpty()) {
            $classification = $classification->first()->options[0];
            if (!Str::contains($name, $classification)) {
                $name = trim(str_replace($classification, '', $name));
                $name .= ' ' . $classification;
            }
        }

        if ($year->isNotEmpty()) {
            if ($year->first()->options[0] === 'N.V.') {
                $name .= ' NV';
            } else {
                $name .= ' ' . $year->first()->options[0];
            }
        } else {
            $name .= ' NV';
        }

        return $name;
    }
}
