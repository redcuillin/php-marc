<?php

namespace Tests;

use Scriptotek\Marc\BibliographicRecord;
use Scriptotek\Marc\Collection;
use Scriptotek\Marc\Exceptions\XmlException;

class CollectionTest extends TestCase
{
    /**
     * Test that an empty Collection is created if no MARC records were found in the input.
     */
    public function testEmptyCollection()
    {
        $source = '<?xml version="1.0" encoding="UTF-8" ?><test></test>';

        $collection = Collection::fromString($source);
        $this->assertCount(0, $collection->toArray());
    }

    /**
     * Test that it XmlException is thrown when the specified encoding (UTF-16)
     * differs from the actual encoding (UTF-8).
     */
    public function testExceptionOnInvalidEncoding()
    {
        $this->expectException(XmlException::class);
        $this->getTestCollection('alma-bibs-api-invalid.xml');
    }

    /**
     * Define a list of sample binary MARC files that we can test with,
     * and the expected number of records in each.
     *
     * @return array
     */
    public static function mrcFiles()
    {
        return [
            ['sandburg.mrc', 1],        // Single binary MARC file
        ];
    }

    /**
     * Define a list of sample XML files from different sources that we can test with,
     * and the expected number of records in each.
     *
     * @return array
     */
    public static function xmlFiles()
    {
        return [
            ['oaipmh-bibsys.xml', 89],  // Records encapsulated in OAI-PMH response
            ['sru-loc.xml', 10],        // Records encapsulated in SRU response
            ['sru-bibsys.xml', 117],    // (Another one)
            ['sru-zdb.xml', 8],         // (Another one)
            ['sru-kth.xml', 10],        // (Another one)
            ['sru-alma.xml', 3],        // (Another one)
        ];
    }

    /**
     * Test that the sample files can be loaded using Collection::fromFile
     *
     * @dataProvider mrcFiles
     * @dataProvider xmlFiles
     * @param string $filename
     * @param int $expected
     */
    public function testCollectionFromFile($filename, $expected)
    {
        $records = $this->getTestCollection($filename)->toArray();

        $this->assertCount($expected, $records);
        $this->assertInstanceOf(BibliographicRecord::class, $records[0]);
    }


    /**
     * Test that the sample files can be loaded using Collection::fromSimpleXMLElement.
     *
     * @dataProvider xmlFiles
     * @param string $filename
     * @param int $expected
     */
    public function testInitializeFromSimpleXmlElement($filename, $expected)
    {
        $el = simplexml_load_file(self::pathTo($filename));

        $collection = Collection::fromSimpleXMLElement($el);

        $this->assertCount($expected, $collection->toArray());
    }

    public function testSorterOrdersRecordsByControlField001()
    {
        $collection = Collection::fromFile(self::pathTo('sort-three-records.xml'));
        $collection->sorter();

        $ids = array_map(function (BibliographicRecord $r) {
            return (string) $r->getId();
        }, $collection->toArray());

        $this->assertSame(['100', '200', '300'], $ids);
    }

    public function testSorterPutsRecordsWithoutFieldLast()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<collection xmlns="http://www.loc.gov/MARC21/slim">'
            . '<record><leader>00778cam a22002531u 4500</leader>'
            . '<controlfield tag="008">110607s1964    xx#||||||    |000|u|eng|d</controlfield></record>'
            . '<record><leader>00778cam a22002531u 4500</leader>'
            . '<controlfield tag="001">50</controlfield>'
            . '<controlfield tag="008">110607s1964    xx#||||||    |000|u|eng|d</controlfield></record>'
            . '</collection>';
        $collection = Collection::fromString($xml)->sorter();

        $ids = array_map(function (BibliographicRecord $r) {
            $f = $r->getRecord()->getField('001');
            if ($f === false || !$f->isControlField()) {
                return '';
            }

            return trim($f->getData());
        }, $collection->toArray());

        $this->assertSame(['50', ''], $ids);
    }
}
