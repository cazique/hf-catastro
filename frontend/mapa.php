<?php
require_once __DIR__ . '/../src/bootstrap.php';
use HogarFamiliar\SistemaCatastral\Core\Config;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Catastral - Hogar Familiar</title>

    <!-- Dependencias CSS -->
    <link rel="stylesheet" href="https://unpkg.com/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/style.css">

    <link rel="icon" href="../img/logo-hf.png" type="image/png">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading">
                <img src="../img/logo-hf.png" alt="Logo Hogar Familiar" class="logo">
                <strong>Sistema Catastral</strong>
            </div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action bg-dark active"><i class="fas fa-search-location me-2"></i>Consulta</a>
                <div class="sidebar-footer">
                    <small>&copy; <?= date('Y') ?> Hogar Familiar.
                    <br>Información de la D.G. del Catastro.</small>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary-outline" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="ms-auto">
                         <span id="last-rc-info" class="navbar-text me-2" style="display: none;">
                            RC: <strong id="last-rc-text"></strong>
                        </span>
                        <button class="btn btn-danger" id="btn-download-pdf" disabled>
                            <i class="fas fa-file-pdf me-1"></i> Descargar PDF
                        </button>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <!-- Formulario de Búsqueda -->
                <div class="row">
                    <div class="col-12">
                        <form id="consulta-form" class="card card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6 col-lg-8">
                                    <label for="rc-input" class="form-label">Referencia Catastral (RC)</label>
                                    <input type="text" class="form-control" id="rc-input" name="rc"
                                           placeholder="Ej: 0395809VK6709N0035KW" required
                                           pattern="[A-Z0-9]{14,20}"
                                           title="Introduce una RC válida de 14 o 20 caracteres alfanuméricos."
                                           aria-describedby="rc-help">
                                    <div id="rc-help" class="form-text">Debe contener 14 o 20 caracteres alfanuméricos.</div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                                        <i class="fas fa-search"></i> Consultar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Alertas y Mensajes -->
                <div id="alert-container" class="mt-3"></div>

                <!-- Mapa y Resultados -->
                <div class="row mt-3">
                    <!-- Columna del Mapa -->
                    <div class="col-lg-7 mb-3 mb-lg-0">
                        <div class="card h-100">
                            <div class="card-header">Mapa de Ubicación</div>
                            <div class="card-body p-0">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Columna de Resultados -->
                    <div class="col-lg-5">
                        <div class="card h-100">
                            <div class="card-header">Resultados de la Consulta</div>
                            <div class="card-body" id="results-container">
                                <div class="text-center text-muted p-5">
                                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                                    <p>Los resultados de la consulta aparecerán aquí.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependencias JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Lógica del Frontend -->
    <script src="js/main.js"></script>
</body>
</html>