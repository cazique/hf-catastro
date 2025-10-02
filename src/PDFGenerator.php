
<?php
class PDFGenerator{
  public function generarFicha(array $datos){
    $rc  = $datos['datos_principales']['referencia_catastral']['valor'] ?? '';
    $dir = $datos['datos_principales']['direccion']['valor'] ?? '';
    $uso = $datos['datos_constructivos']['uso_principal']['valor'] ?? '';
    $sup = $datos['datos_constructivos']['superficie']['valor'] ?? '';

    $json = htmlspecialchars(json_encode($datos, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    $css = 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#222;margin:24px}
            .brand{font-size:18px;font-weight:700;color:#123F60}
            .bar{height:4px;background:#123F60;margin:8px 0 16px;border-radius:2px}
            table{width:100%;border-collapse:collapse} td{border:1px solid #e6e6e6;padding:8px}
            td.h{background:#f7f9fc;color:#123F60;font-weight:600;width:25%}
            pre{background:#f7f7f7;border:1px solid #eee;padding:10px;border-radius:6px;white-space:pre-wrap}';

    $html = "<!doctype html><meta charset='utf-8'><style>$css</style>
      <div class='brand'>Hogar Familiar — Ficha Catastral</div>
      <div class='bar'></div>
      <table>
        <tr><td class='h'>Referencia catastral</td><td>$rc</td><td class='h'>Uso principal</td><td>$uso</td></tr>
        <tr><td class='h'>Superficie (m²)</td><td>$sup</td><td class='h'>Dirección</td><td>$dir</td></tr>
      </table>
      <h3 style='color:#123F60'>Datos completos (JSON)</h3>
      <pre>$json</pre>";

    $autoload = __DIR__.'/../vendor/autoload.php';
    if(file_exists($autoload)){
      require $autoload;
      $dompdf = new Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>true]);
      $dompdf->loadHtml($html,'UTF-8'); $dompdf->setPaper('A4','portrait'); $dompdf->render();
      return ['type'=>'pdf','bytes'=>$dompdf->output(),'filename'=>'ficha_catastral.pdf'];
    }else{
      return ['type'=>'html','bytes'=>$html,'filename'=>'ficha_catastral.html'];
    }
  }
}
?>
