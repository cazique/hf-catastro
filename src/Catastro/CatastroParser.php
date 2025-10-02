<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Catastro;

use SimpleXMLElement;

/**
 * Parsea las respuestas XML de los servicios del Catastro.
 * Utiliza XPath para una extracción de datos robusta y compatible con namespaces.
 */
class CatastroParser
{
    /**
     * Namespaces comunes encontrados en las respuestas del Catastro.
     * @var array<string, string>
     */
    private const NAMESPACES = [
        'cat' => 'http://www.catastro.meh.es/',
        'atom' => 'http://www.w3.org/2005/Atom',
        'gml' => 'http://www.opengis.net/gml',
        'coor' => 'http://www.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCoordenadas',
    ];

    /**
     * Parsea una respuesta XML y devuelve un array de inmuebles o un error.
     *
     * @param string $xmlString El contenido XML de la respuesta.
     * @return array{success: bool, data: array, error?: string}
     */
    public function parse(string $xmlString): array
    {
        // Silenciar errores de libxml y manejarlos internamente
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            return [
                'success' => false,
                'data' => [],
                'error' => 'XML mal formado o inválido. Detalles: ' . implode('; ', $errors)
            ];
        }

        $this->registerNamespaces($xml);

        // Detectar XML de error del Catastro
        $errorNode = $this->xpathQuery($xml, '//cat:err/cat:des | //lerr/err/des');
        if (!empty($errorNode)) {
            return ['success' => false, 'data' => [], 'error' => $this->normalizeString((string)$errorNode[0])];
        }

        // Determinar si es una respuesta de múltiples inmuebles o de uno solo
        $inmueblesNodes = $this->xpathQuery($xml, '//cat:bico/cat:bi | //cat:control/cat:bico/cat:bi');
        if (empty($inmueblesNodes)) {
            // Fallback para un único inmueble en la raíz
            $inmueblesNodes = $this->xpathQuery($xml, '/cat:consulta_dnp/cat:bico');
             if (empty($inmueblesNodes)) {
                return ['success' => false, 'data' => [], 'error' => 'No se encontraron datos de bienes inmuebles en la respuesta.'];
            }
        }

        $resultados = [];
        foreach ($inmueblesNodes as $node) {
            $resultados[] = $this->parseInmueble($node);
        }

        return ['success' => true, 'data' => $resultados];
    }

    /**
     * Parsea las coordenadas de una respuesta del servicio RCCOOR.
     *
     * @param string $xmlString
     * @return array{lat: ?float, lon: ?float}|null
     */
    public function parseCoordenadas(string $xmlString): ?array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) return null;

        $this->registerNamespaces($xml);

        $x = $this->xpathQuery($xml, '//coor:pc/coor:geo/coor:xcen');
        $y = $this->xpathQuery($xml, '//coor:pc/coor:geo/coor:ycen');

        if (!empty($x) && !empty($y)) {
            return [
                'lat' => (float)(string)$y[0],
                'lon' => (float)(string)$x[0],
            ];
        }

        return null;
    }

    /**
     * Extrae los datos de un único nodo de bien inmueble.
     *
     * @param SimpleXMLElement $inmuebleNode
     * @return array<string, mixed>
     */
    private function parseInmueble(SimpleXMLElement $inmuebleNode): array
    {
        // Referencia Catastral
        $rcParts = $this->xpathQuery($inmuebleNode, './/cat:rc/cat:pc1 | .//cat:rc/cat:pc2 | .//cat:rc/cat:car | .//cat:rc/cat:cc1 | .//cat:rc/cat:cc2');
        $rc = implode('', array_map('strval', $rcParts));

        // Dirección
        $ldt = $this->xpathQuery($inmuebleNode, './/cat:ldt');
        $dir = $this->xpathQuery($inmuebleNode, './/cat:dir/cat:tv | .//cat:dir/cat:nv | .//cat:dir/cat:pnp | .//cat:dir/cat:plp');
        $direccion = !empty($ldt) ? (string)$ldt[0] : implode(' ', array_map('strval', $dir));

        // Coordenadas
        $lat = $this->xpathQuery($inmuebleNode, './/cat:geo/cat:ycen');
        $lon = $this->xpathQuery($inmuebleNode, './/cat:geo/cat:xcen');

        // Datos del local
        $local = $this->xpathQuery($inmuebleNode, './/cat:loine/cat:lcd');

        // Datos de construcción
        $cons = $this->xpathQuery($inmuebleNode, './/cat:lcons/cat:cons');

        return [
            'referencia_catastral' => $this->normalizeString($rc),
            'direccion' => $this->normalizeString($direccion),
            'uso_principal' => $this->normalizeString((string)($this->xpathQuery($inmuebleNode, './/cat:bi/cat:debi/cat:luso')[0] ?? '')),
            'superficie_construida' => (int)($this->xpathQuery($inmuebleNode, './/cat:bi/cat:debi/cat:sfc')[0] ?? 0),
            'ano_construccion' => (int)($this->xpathQuery($inmuebleNode, './/cat:bi/cat:debi/cat:ant')[0] ?? 0),
            'lat' => !empty($lat) ? (float)$lat[0] : null,
            'lon' => !empty($lon) ? (float)$lon[0] : null,
            'datos_adicionales' => [
                'uso_destino' => $this->normalizeString((string)($local[0] ?? '')),
                'superficie_total_parcela' => (int)($this->xpathQuery($inmuebleNode, './/cat:dfp/cat:sfc')[0] ?? 0),
                'numero_plantas' => $this->normalizeString((string)($this->xpathQuery($inmuebleNode, './/cat:cons/cat:plb')[0] ?? 'N/D')),
                'elementos' => $this->parseElementosConstruidos($cons)
            ]
        ];
    }

    /**
     * Extrae los datos de los elementos constructivos.
     * @param SimpleXMLElement[] $consNodes
     * @return array
     */
    private function parseElementosConstruidos(array $consNodes): array
    {
        $elementos = [];
        foreach($consNodes as $cons) {
            $elementos[] = [
                'puerta' => $this->normalizeString((string)($this->xpathQuery($cons, './/cat:lourb/cat:lo/cat:pu')[0] ?? '')),
                'planta' => $this->normalizeString((string)($this->xpathQuery($cons, './/cat:lourb/cat:lo/cat:pt')[0] ?? '')),
                'uso' => $this->normalizeString((string)($this->xpathQuery($cons, './/cat:lourb/cat:lo/cat:lour/cat:cd')[0] ?? '')),
                'superficie' => (int)($this->xpathQuery($cons, './/cat:lourb/cat:lo/cat:lour/cat:sfc')[0] ?? 0)
            ];
        }
        return $elementos;
    }


    /**
     * Normaliza un string: elimina espacios extra y asegura codificación UTF-8.
     */
    private function normalizeString(string $value): string
    {
        $value = trim($value);
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Registra los namespaces del documento en el objeto XML.
     */
    private function registerNamespaces(SimpleXMLElement $xml): void
    {
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xml->registerXPathNamespace($prefix, $uri);
        }
    }

    /**
     * Ejecuta una consulta XPath y devuelve el resultado.
     * @return SimpleXMLElement[]|null
     */
    private function xpathQuery(SimpleXMLElement $xml, string $query): ?array
    {
        $result = $xml->xpath($query);
        return $result === false ? null : $result;
    }
}