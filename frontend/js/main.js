document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Element Selection ---
    const menuToggle = document.getElementById('menu-toggle');
    const wrapper = document.getElementById('wrapper');
    const consultaForm = document.getElementById('consulta-form');
    const rcInput = document.getElementById('rc-input');
    const submitButton = consultaForm.querySelector('button[type="submit"]');
    const spinner = submitButton.querySelector('.spinner-border');
    const alertContainer = document.getElementById('alert-container');
    const resultsContainer = document.getElementById('results-container');
    const pdfButton = document.getElementById('btn-download-pdf');
    const lastRcInfo = document.getElementById('last-rc-info');
    const lastRcText = document.getElementById('last-rc-text');

    // --- State ---
    let lastSuccessfulRc = null;
    let map = null;
    let marker = null;

    // --- Initialization ---
    function initMap() {
        map = L.map('map', {
            center: [40.416775, -3.703790], // Madrid
            zoom: 6,
        });

        // --- Tile Layers ---
        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const catastroWms = L.tileLayer.wms('https://ovc.catastro.meh.es/Cartografia/WMS/ServidorWMS.aspx', {
            layers: 'Catastro',
            format: 'image/png',
            transparent: true,
            attribution: '© <a href="https://www.catastro.meh.es/">D.G. Catastro</a>'
        });

        const ignOrtofoto = L.tileLayer.wms("https://www.ign.es/wms-inspire/pnoa-ma", {
            layers: "OI.OrthoimageCoverage",
            format: 'image/jpeg',
            attribution: 'PNOA © <a href="http://www.ign.es/ign/main/index.do">IGN</a>'
        });

        const baseMaps = {
            "🗺️ OpenStreetMap": osmLayer,
            "🏘️ Catastro": catastroWms,
            "🛰️ Ortofoto (IGN)": ignOrtofoto
        };

        L.control.layers(baseMaps, null, { collapsed: false }).addTo(map);
    }

    // --- Event Listeners ---
    menuToggle.addEventListener('click', () => {
        wrapper.classList.toggle('toggled');
    });

    consultaForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!consultaForm.checkValidity()) {
            showAlert('Por favor, introduce una Referencia Catastral válida (14 o 20 caracteres alfanuméricos).', 'warning');
            rcInput.focus();
            return;
        }
        const rc = rcInput.value.trim().toUpperCase();
        await handleConsulta(rc);
    });

    pdfButton.addEventListener('click', () => {
        if (lastSuccessfulRc) {
            window.open(`../api/pdf.php?rc=${lastSuccessfulRc}`, '_blank');
        }
    });

    // --- Core Functions ---
    async function handleConsulta(rc) {
        setLoadingState(true);
        clearUI();

        try {
            const response = await fetch('../api/consulta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ rc })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || `Error ${response.status}`);
            }

            showAlert(`Consulta para <strong>${rc}</strong> realizada con éxito (fuente: ${result.source}).`, 'success');
            renderResults(result.data);
            updateMap(result.data);

            lastSuccessfulRc = rc;
            updatePdfButton(true, rc);

        } catch (error) {
            console.error('Error en la consulta:', error);
            showAlert(`Error al consultar: ${error.message}`, 'danger');
            updatePdfButton(false);
            resetResults();
        } finally {
            setLoadingState(false);
        }
    }

    function renderResults(data) {
        if (!data || data.length === 0) {
            resetResults('No se encontraron inmuebles para la referencia catastral proporcionada.');
            return;
        }

        const getSedeUrl = (rc) => `https://www1.sedecatastro.gob.es/CYCBienInmueble/OVCConCiud.aspx?UrbRus=U&RefC=${rc}`;

        resultsContainer.innerHTML = data.map(inmueble => `
            <div class="result-item">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Referencia Catastral</dt>
                    <dd class="col-sm-7">
                        ${inmueble.referencia_catastral || 'N/D'}
                        <a href="${getSedeUrl(inmueble.referencia_catastral)}" target="_blank" class="ms-2" title="Ver en Sede Electrónica del Catastro">
                            <i class="fas fa-external-link-alt fa-xs"></i>
                        </a>
                    </dd>

                    <dt class="col-sm-5">Dirección</dt>
                    <dd class="col-sm-7">${inmueble.direccion || 'N/D'}</dd>

                    <dt class="col-sm-5">Uso Principal</dt>
                    <dd class="col-sm-7">${inmueble.uso_principal || 'N/D'}</dd>

                    <dt class="col-sm-5">Superficie Construida</dt>
                    <dd class="col-sm-7">${inmueble.superficie_construida ? `${inmueble.superficie_construida} m²` : 'N/D'}</dd>

                    <dt class="col-sm-5">Año Construcción</dt>
                    <dd class="col-sm-7">${inmueble.ano_construccion || 'N/D'}</dd>
                </dl>
            </div>
        `).join('');
    }

    function updateMap(data) {
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }

        const inmueble = data && data[0];
        if (inmueble && inmueble.lat && inmueble.lon) {
            const coords = [inmueble.lat, inmueble.lon];
            map.flyTo(coords, 18);
            marker = L.marker(coords).addTo(map)
                .bindPopup(`<b>RC:</b> ${inmueble.referencia_catastral}`)
                .openPopup();
        }
    }

    // --- UI Helper Functions ---
    function showAlert(message, type = 'info') {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        alertContainer.innerHTML = alertHTML;
    }

    function clearUI() {
        alertContainer.innerHTML = '';
    }

    function resetResults(message = 'Los resultados de la consulta aparecerán aquí.') {
        resultsContainer.innerHTML = `
            <div class="text-center text-muted p-5">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <p>${message}</p>
            </div>`;
    }

    function setLoadingState(isLoading) {
        submitButton.disabled = isLoading;
        spinner.style.display = isLoading ? 'inline-block' : 'none';
        const icon = submitButton.querySelector('.fa-search');
        if (icon) icon.style.display = isLoading ? 'none' : 'inline-block';
    }

    function updatePdfButton(enabled, rc = '') {
        pdfButton.disabled = !enabled;
        if (enabled) {
            lastRcText.textContent = rc;
            lastRcInfo.style.display = 'inline';
        } else {
            lastSuccessfulRc = null;
            lastRcInfo.style.display = 'none';
        }
    }

    // --- Initial Call ---
    initMap();
});