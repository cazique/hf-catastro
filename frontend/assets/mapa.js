
const map = L.map('map').setView([40.4168,-3.7038],6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);
L.tileLayer.wms('https://ovc.catastro.meh.es/Cartografia/WMS/ServidorWMS.aspx',{layers:'Catastro',format:'image/png',transparent:true}).addTo(map);
let marker;

document.getElementById('consultaForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  const load=document.getElementById('loading');load.classList.remove('d-none');
  try{
    const res=await fetch('../api/consulta.php',{method:'POST',body:fd});
    const j=await res.json();
    if(!j.success) throw new Error(j.error||'Error en la consulta');
    render(j.data);
    const coords=j.data?.datos_geograficos?.coordenadas_etrs89?.valor;
    if(coords&&coords.lat){ if(marker) map.removeLayer(marker); marker=L.marker([coords.lat,coords.lon]).addTo(map); map.setView([coords.lat,coords.lon],18); }
    const rc=j.data?.datos_principales?.referencia_catastral?.valor;
    if(rc){ const btn=document.getElementById('btnPDF'); btn.classList.remove('d-none'); btn.href=`../api/pdf.php?rc=${encodeURIComponent(rc)}`; }
  }catch(err){ alert(err.message); }
  finally{ load.classList.add('d-none'); }
});

function render(data){
  const c=document.getElementById('resultados'); c.innerHTML='';
  const row=(k,v)=>`<div class="result-card"><span class="label">${k}:</span> ${v||'N/D'}</div>`;
  c.innerHTML+=row('Referencia', data?.datos_principales?.referencia_catastral?.valor);
  c.innerHTML+=row('Dirección', data?.datos_principales?.direccion?.valor);
  c.innerHTML+=row('Uso', data?.datos_constructivos?.uso_principal?.valor);
  c.innerHTML+=row('Superficie', data?.datos_constructivos?.superficie?.valor);
}
