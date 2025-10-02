
<?php
class CatastroXMLParser{
  public function parseSimple($xml,$rc){
    $xml = $this->toUtf8($xml);
    $sx = @simplexml_load_string($xml);
    if(!$sx){ return ['datos_principales'=>['referencia_catastral'=>['valor'=>$rc]],'datos_constructivos'=>[],'datos_geograficos'=>[]]; }
    $ns = $sx->getNamespaces(true);
    if(isset($ns[''])) $sx->registerXPathNamespace('x', $ns['']);
    $val = fn($xp)=>($r=$sx->xpath($xp)) ? trim((string)$r[0]) : null;

    $ref = ($val('//x:pc1').$val('//x:pc2').$val('//x:cc1').$val('//x:cc2')) ?: $rc;
    $dir = $val('//*[local-name()="ldt"]') ?: null;
    $uso = $val('//*[local-name()="luso"]') ?: null;
    $sup = $val('//*[local-name()="stl"] | //*[local-name()="stc"]') ?: null;

    $lon = $val('//*[local-name()="geo"]/*[local-name()="x"]');
    $lat = $val('//*[local-name()="geo"]/*[local-name()="y"]');
    $coords = ($lat && $lon) ? ['lat'=>(float)$lat,'lon'=>(float)$lon] : null;

    return [
      'datos_principales'=>[
        'referencia_catastral'=>['valor'=>$ref,'tipo'=>'string','descripcion'=>'Referencia catastral'],
        'direccion'=>['valor'=>$dir,'tipo'=>'string','descripcion'=>'Dirección formateada']
      ],
      'datos_constructivos'=>[
        'uso_principal'=>['valor'=>$uso,'tipo'=>'string','descripcion'=>'Uso principal'],
        'superficie'=>['valor'=>$sup,'tipo'=>'float','descripcion'=>'Superficie (m²)']
      ],
      'datos_geograficos'=>[
        'coordenadas_etrs89'=>['valor'=>$coords,'tipo'=>'coord','descripcion'=>'Coordenadas ETRS89 si disponibles']
      ]
    ];
  }
  private function toUtf8($s){
    if(!mb_detect_encoding($s,'UTF-8',true)){
      $s = mb_convert_encoding($s,'UTF-8','ISO-8859-1, Windows-1252, UTF-8');
    }
    return $s;
  }
}
?>
