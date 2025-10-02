<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera el informe PDF de la consulta catastral usando Dompdf.
 */
class PdfGenerator
{
    private array $data;
    private ?string $rc;

    public function __construct(array $data, ?string $rc = null)
    {
        $this->data = $data;
        $this->rc = $rc ?: ($data['referencia_catastral'] ?? 'N/D');
    }

    /**
     * Genera y envía el PDF al navegador para su descarga.
     */
    public function streamPdf(): void
    {
        try {
            $options = new Options();
            // Habilitar la carga de imágenes remotas (para el mapa WMS) y CSS.
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            // Definir un directorio temporal para Dompdf si es necesario
            // $options->set('tempDir', sys_get_temp_dir());

            $dompdf = new Dompdf($options);

            $html = $this->getHtmlContent();
            $dompdf->loadHtml($html, 'UTF-8');

            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "Informe_Catastral_{$this->rc}.pdf";

            header('Content-Type: application/pdf');
            $dompdf->stream($filename, ['Attachment' => 1]);

        } catch (\Exception $e) {
            error_log("Error al generar el PDF: " . $e->getMessage());
            http_response_code(500);
            echo "Error al generar el PDF. Revise los logs del servidor.";
        }
    }

    /**
     * Construye el contenido HTML del PDF.
     */
    private function getHtmlContent(): string
    {
        $logoPath = APP_ROOT . '/img/logo-hf.png';
        // Convertir el logo a base64 para embeberlo directamente y evitar problemas de rutas.
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));

        $mapHtml = $this->getMapHtml();
        $mainData = $this->data;
        $jsonData = json_encode($mainData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Limpiar los datos para la tabla principal
        $direccion = htmlspecialchars($mainData['direccion'] ?? 'N/D');
        $usoPrincipal = htmlspecialchars($mainData['uso_principal'] ?? 'N/D');
        $superficie = htmlspecialchars((string)($mainData['superficie_construida'] ?? 'N/D'));
        $ano = htmlspecialchars((string)($mainData['ano_construccion'] ?? 'N/D'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Informe Catastral - {$this->rc}</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
                .header { background-color: #123F60; color: white; padding: 15px; text-align: left; }
                .header img { height: 40px; vertical-align: middle; }
                .header h1 { display: inline; vertical-align: middle; font-size: 20px; margin-left: 15px; }
                .content { margin: 20px; }
                h2 { color: #123F60; border-bottom: 2px solid #C7A578; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .map-container { text-align: center; margin-top: 20px; border: 1px solid #ddd; padding: 10px; }
                .map-container img { max-width: 100%; height: auto; }
                .json-block { background-color: #f8f8f8; border: 1px solid #eee; padding: 10px; margin-top: 20px; font-family: monospace; white-space: pre-wrap; word-wrap: break-word; font-size: 9px; }
                .footer { text-align: center; font-size: 10px; color: #777; position: fixed; bottom: 0; width: 100%; }
            </style>
        </head>
        <body>
            <div class="header">
                <img src="{$logoBase64}" alt="Logo Hogar Familiar">
                <h1>Informe de Consulta Catastral</h1>
            </div>

            <div class="content">
                <h2>Datos Principales del Inmueble</h2>
                <table>
                    <tr><th>Referencia Catastral</th><td>{$this->rc}</td></tr>
                    <tr><th>Dirección</th><td>{$direccion}</td></tr>
                    <tr><th>Uso Principal</th><td>{$usoPrincipal}</td></tr>
                    <tr><th>Superficie Construida</th><td>{$superficie} m²</td></tr>
                    <tr><th>Año de Construcción</th><td>{$ano}</td></tr>
                </table>

                {$mapHtml}

                <h2>Datos Completos (JSON)</h2>
                <div class="json-block">{$jsonData}</div>
            </div>

            <div class="footer">
                Generado por Sistema Catastral Hogar Familiar | Información obtenida de la Dirección General del Catastro
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Genera el HTML para el mapa estático WMS.
     */
    private function getMapHtml(): string
    {
        $lat = $this->data['lat'] ?? null;
        $lon = $this->data['lon'] ?? null;

        if (!$lat || !$lon) {
            return '<h2>Mapa de Ubicación</h2><p>No se dispone de coordenadas para generar el mapa.</p>';
        }

        // Calcular el BBOX. Un delta pequeño para una vista cercana.
        $delta = 0.0015;
        $bbox = sprintf('%f,%f,%f,%f', $lon - $delta, $lat - $delta, $lon + $delta, $lat + $delta);

        $wmsUrl = 'https://ovc.catastro.meh.es/Cartografia/WMS/ServidorWMS.aspx?' . http_build_query([
            'SERVICE' => 'WMS',
            'VERSION' => '1.1.1',
            'REQUEST' => 'GetMap',
            'LAYERS' => 'Catastro,PARCELA', // Capas a mostrar
            'STYLES' => '',
            'FORMAT' => 'image/png',
            'SRS' => 'EPSG:4326',
            'BBOX' => $bbox,
            'WIDTH' => '600',
            'HEIGHT' => '450',
            'TRANSPARENT' => 'true'
        ]);

        return <<<HTML
        <h2>Mapa de Ubicación</h2>
        <div class="map-container">
            <img src="{$wmsUrl}" alt="Mapa de ubicación del inmueble">
        </div>
        HTML;
    }
}