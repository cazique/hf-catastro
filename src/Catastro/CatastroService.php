<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Catastro;

use HogarFamiliar\SistemaCatastral\Cache\CacheService;

/**
 * Orquesta las operaciones de consulta al Catastro.
 *
 * Combina el cliente HTTP y el parser, gestiona la lógica de fallback
 * entre servicios (REST -> ASMX) y el enriquecimiento de datos (coordenadas).
 */
class CatastroService
{
    private const URL_REST_DNPRC = 'https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCallejero.svc/rest/Consulta_DNPRC?RC=%s';
    private const URL_ASMX_DNPRC = 'https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCallejero.asmx/Consulta_DNPRC?ReferenciaCatastral=%s';
    private const URL_ASMX_RCCOOR = 'https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCoordenadas.asmx/Consulta_RCCOOR?SRS=EPSG:4326&RC=%s';

    private HttpClient $httpClient;
    private CatastroParser $parser;
    private ?CacheService $cache;

    public function __construct(HttpClient $httpClient, CatastroParser $parser, ?CacheService $cache)
    {
        $this->httpClient = $httpClient;
        $this->parser = $parser;
        $this->cache = $cache;
    }

    /**
     * Consulta una Referencia Catastral.
     *
     * @param string $rc La Referencia Catastral a consultar.
     * @return array{success: bool, data: array, error?: string, source?: string}
     */
    public function consultarPorRC(string $rc): array
    {
        $rc = $this->sanitizeRC($rc);
        if (empty($rc)) {
            return ['success' => false, 'data' => [], 'error' => 'La Referencia Catastral no puede estar vacía.'];
        }

        // 1. Intentar obtener de la caché
        if ($this->cache && ($cached = $this->cache->get($rc)) !== null) {
            $cached['source'] = 'cache';
            return $cached;
        }

        // 2. Camino principal: Petición al servicio REST
        [$httpCode, $xmlResponse] = $this->httpClient->getWithRetries(sprintf(self::URL_REST_DNPRC, $rc));

        // 3. Fallback: Si REST falla (no 200), intentar con ASMX
        if ($httpCode !== 200) {
            [$httpCode, $xmlResponse] = $this->httpClient->getWithRetries(sprintf(self::URL_ASMX_DNPRC, $rc));
        }

        // Si después del fallback seguimos sin una respuesta válida, retornamos error.
        if ($httpCode !== 200) {
            $errorMsg = "El servicio del Catastro no está disponible en este momento (Código: {$httpCode}). Por favor, inténtelo de nuevo más tarde.";
            $parsedError = $this->parser->parse($xmlResponse);
            if (!$parsedError['success'] && !empty($parsedError['error'])) {
                 $errorMsg = $parsedError['error'];
            }
            return ['success' => false, 'data' => [], 'error' => $errorMsg];
        }

        // 4. Parsear la respuesta XML obtenida
        $result = $this->parser->parse($xmlResponse);

        if (!$result['success']) {
            return $result; // El parser ya nos da el mensaje de error
        }

        // 5. Enriquecer con coordenadas si es necesario
        $result['data'] = $this->enrichWithCoordinates($result['data'], $rc);

        // 6. Guardar en caché si está habilitada y el resultado es exitoso
        if ($this->cache && $result['success']) {
            $this->cache->save($rc, $result, $xmlResponse);
        }

        $result['source'] = 'api';
        return $result;
    }

    /**
     * Si los datos no tienen coordenadas, intenta obtenerlas del servicio RCCOOR.
     *
     * @param array $data Array de inmuebles.
     * @param string $rc La Referencia Catastral.
     * @return array Los datos enriquecidos.
     */
    private function enrichWithCoordinates(array $data, string $rc): array
    {
        // Solo enriquecemos si hay un único resultado y no tiene ya coordenadas
        if (count($data) !== 1 || (!empty($data[0]['lat']) && !empty($data[0]['lon']))) {
            return $data;
        }

        [$httpCode, $xmlCoordResponse] = $this->httpClient->getWithRetries(sprintf(self::URL_ASMX_RCCOOR, $rc));

        if ($httpCode === 200) {
            $coords = $this->parser->parseCoordenadas($xmlCoordResponse);
            if ($coords) {
                $data[0]['lat'] = $coords['lat'];
                $data[0]['lon'] = $coords['lon'];
            }
        }

        return $data;
    }

    /**
     * Sanitiza la Referencia Catastral.
     */
    private function sanitizeRC(string $rc): string
    {
        return trim(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $rc)));
    }
}