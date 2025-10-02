
<?php
require_once __DIR__.'/../src/CatastroXMLParser.php';
require_once __DIR__.'/../src/PDFGenerator.php';

function http_get($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'HF-Catastro/1.0']);
  $out=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($out===false||$code>=400){ throw new Exception("Servicio remoto devolvió HTTP $code"); }
  return $out;
}

try{
  $rc = isset($_GET['rc']) ? preg_replace('/\s+/','',$_GET['rc']) : '';
  if(!$rc) throw new Exception('Parámetro rc obligatorio');

  try{
    $xml=http_get('https://ovc.catastro.meh.es/OVCServWeb/OVCWcfCallejero/COVCCallejero.svc/rest/Consulta_DNPRC?RC='.urlencode($rc));
  }catch(Throwable $e){
    $xml=http_get('https://ovc.catastro.meh.es/ovcservweb/OVCSWLocalizacionRC/OVCCallejero.asmx/Consulta_DNPRC?Provincia=&Municipio=&RC='.urlencode($rc));
  }

  $parser=new CatastroXMLParser();
  $data=$parser->parseSimple($xml,$rc);

  $gen=new PDFGenerator();
  $out=$gen->generarFicha($data);

  if($out['type']==='pdf'){
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$out['filename'].'"');
    echo $out['bytes'];
  }else{
    header('Content-Type: text/html; charset=utf-8'); echo $out['bytes'];
  }
}catch(Throwable $e){
  header('Content-Type: text/plain; charset=utf-8'); http_response_code(400); echo 'Error: '.$e->getMessage();
}
