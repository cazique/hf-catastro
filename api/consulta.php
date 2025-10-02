
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/CatastroXMLParser.php';

function http_get($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'HF-Catastro/1.0']);
  $out=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($out===false||$code>=400){ throw new Exception("Servicio remoto devolvió HTTP $code"); }
  return $out;
}

try{
  $rc = isset($_POST['referencia']) ? preg_replace('/\s+/','',$_POST['referencia']) : '';
  $dir= isset($_POST['direccion']) ? trim($_POST['direccion']) : '';
  if(!$rc && !$dir) throw new Exception('Proporcione referencia catastral o dirección.');

  $parser = new CatastroXMLParser();

  if($rc){
    try{
      $xml=http_get('https://ovc.catastro.meh.es/OVCServWeb/OVCWcfCallejero/COVCCallejero.svc/rest/Consulta_DNPRC?RC='.urlencode($rc));
    }catch(Throwable $e){
      $xml=http_get('https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCallejero.asmx/Consulta_DNPRC?Provincia=&Municipio=&RC='.urlencode($rc));
    }
    $data=$parser->parseSimple($xml,$rc);
    echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE); exit;
  }

  $parts=array_map('trim', explode(',', $dir));
  if(count($parts)<3){ throw new Exception('Dirección ambigua. Usa: "Calle, número, municipio, provincia".'); }
  $via=$parts[0]; $num=preg_replace('/\D+/','',$parts[1]); $mun=$parts[2]; $prov=$parts[3] ?? '';
  $url='https://ovc.catastro.meh.es/OVCServWeb/OVCWcfCallejero/COVCCallejero.svc/rest/Consulta_DNPLOC?Provincia='.urlencode($prov).'&Municipio='.urlencode($mun)+'&TipoVia=&NombreVia='.urlencode($via)+'&Numero='.urlencode($num);
  $xml=http_get($url);
  $data=$parser->parseSimple($xml,'');
  echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  echo json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
