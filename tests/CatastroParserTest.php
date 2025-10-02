<?php

declare(strict_types=1);

namespace HogarFamiliar\Tests;

use HogarFamiliar\SistemaCatastral\Catastro\CatastroParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HogarFamiliar\SistemaCatastral\Catastro\CatastroParser
 */
class CatastroParserTest extends TestCase
{
    private CatastroParser $parser;
    private static string $validResponseXml;
    private static string $errorResponseXml;
    private static string $coordResponseXml;

    public static function setUpBeforeClass(): void
    {
        self::$validResponseXml = file_get_contents(__DIR__ . '/fixtures/0395809VK6709N0035KW_response.xml');
        self::$errorResponseXml = file_get_contents(__DIR__ . '/fixtures/invalid_rc_response.xml');
        self::$coordResponseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coordenadas_salida xmlns:coor="http://www.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCoordenadas" xmlns:geo="http://www.catastro.meh.es/xsd/georef">
    <coor:coordenadas>
        <coor:coord>
            <coor:pc>
                <coor:pc1>0395809</coor:pc1>
                <coor:pc2>VK6709N</coor:pc2>
                <coor:geo>
                    <coor:xcen>0.4900000</coor:xcen>
                    <coor:ycen>38.3500000</coor:ycen>
                    <coor:srs>EPSG:4326</coor:srs>
                </coor:geo>
            </coor:pc>
        </coor:coord>
    </coor:coordenadas>
</coordenadas_salida>
XML;
    }

    protected function setUp(): void
    {
        $this->parser = new CatastroParser();
    }

    public function testParseValidResponse(): void
    {
        $result = $this->parser->parse(self::$validResponseXml);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']);

        $inmueble = $result['data'][0];
        $this->assertSame('0395809VK6709N0035KW', $inmueble['referencia_catastral']);
        $this->assertSame('AV BENIDORM, 11 Es:1 Pl:08 Pt:C 03540 ALACANT D\'ALACANT/ALICANTE (ALICANTE)', $inmueble['direccion']);
        $this->assertSame('Vivienda', $inmueble['uso_principal']);
        $this->assertSame(120, $inmueble['superficie_construida']);
        $this->assertSame(1980, $inmueble['ano_construccion']);
        $this->assertNull($inmueble['lat']);
        $this->assertNull($inmueble['lon']);
        $this->assertCount(2, $inmueble['datos_adicionales']['elementos']);
        $this->assertSame('VIVIENDA', $inmueble['datos_adicionales']['elementos'][0]['uso']);
        $this->assertSame(105, $inmueble['datos_adicionales']['elementos'][0]['superficie']);
    }

    public function testParseErrorResponse(): void
    {
        $result = $this->parser->parse(self::$errorResponseXml);

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['data']);
        $this->assertSame('Referencia catastral inválida', $result['error']);
    }

    public function testParseMalformedXml(): void
    {
        $malformedXml = '<root><unclosed-tag></root>';
        $result = $this->parser->parse($malformedXml);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('XML mal formado', $result['error']);
    }

    public function testParseEmptyXml(): void
    {
        $result = $this->parser->parse('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('XML mal formado', $result['error']);
    }

    public function testParseCoordenadas(): void
    {
        $coords = $this->parser->parseCoordenadas(self::$coordResponseXml);

        $this->assertNotNull($coords);
        $this->assertSame(38.35, $coords['lat']);
        $this->assertSame(0.49, $coords['lon']);
    }

    public function testParseInvalidCoordenadas(): void
    {
        $coords = $this->parser->parseCoordenadas('<invalid></xml>');
        $this->assertNull($coords);
    }
}