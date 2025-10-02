<?php
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hogar Familiar — Catastro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="css/style.css">
</head><body>
<nav class="navbar navbar-expand-lg" style="background:var(--hf-blue)">
  <div class="container"><span class="navbar-brand">🏠 Hogar Familiar — Catastro</span></div>
</nav>
<div class="container-fluid mt-3">
  <div class="row g-3">
    <div class="col-lg-3">
      <div class="sidebar">
        <h5 class="section-title">Consulta</h5>
        <form id="consultaForm">
          <div class="mb-2">
            <label class="form-label">Referencia catastral</label>
            <input class="form-control" name="referencia" placeholder="0395809VK6709N0035KW">
          </div>
          <div class="text-center mb-2">— o —</div>
          <div class="mb-2">
            <label class="form-label">Dirección</label>
            <input class="form-control" name="direccion" placeholder="Calle, número, municipio, provincia">
          </div>
          <button class="btn btn-hf w-100" type="submit">
            <span class="spinner-border spinner-border-sm d-none" id="loading"></span> Consultar
          </button>
        </form>
        <hr>
        <h6 class="section-title">Resultados</h6>
        <div id="resultados"></div>
        <a id="btnPDF" href="#" target="_blank" class="btn btn-success btn-sm mt-2 d-none">Descargar PDF</a>
      </div>
    </div>
    <div class="col-lg-9">
      <div id="map" style="height:75vh;border:1px solid #dee2e6;border-radius:8px"></div>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/mapa.js"></script>
</body></html>
