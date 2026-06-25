/* ============================================================
   ALT STORE ERP v2 — JS applicatif
   ============================================================ */

// Jeton CSRF accessible aux appels fetch
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content
  || sessionStorage.getItem('csrf_token') || '';

// ---- Sélecteur de boutique ----
function toggleBoutiqueDropdown() {
  document.getElementById('bsDropdown')?.classList.toggle('open');
}
document.addEventListener('click', (e) => {
  const dd = document.getElementById('bsDropdown');
  const cur = document.getElementById('bsCurrent');
  if (dd && cur && !dd.contains(e.target) && !cur.contains(e.target)) {
    dd.classList.remove('open');
  }
});

async function switchBoutique(boutiqueId) {
  if (!confirm('Changer de boutique de travail ? Les données affichées seront celles de la nouvelle boutique.')) return;
  try {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('boutique_id', boutiqueId);
    const r = await fetch(APP_URL + '/api/switch_boutique.php', { method: 'POST', body: fd });
    const data = await r.json();
    if (data.success) {
      // Recharger pour rafraîchir toutes les données contextuelles
      location.reload();
    } else {
      alert(data.message || 'Échec du changement de boutique');
    }
  } catch (e) {
    alert('Erreur réseau : ' + e.message);
  }
}

// ---- Helper fetch avec CSRF automatique ----
async function api(url, opts = {}) {
  opts.headers = opts.headers || {};
  if (opts.body instanceof FormData) {
    opts.body.append('csrf_token', CSRF_TOKEN);
  } else if (opts.method && opts.method !== 'GET') {
    opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
    opts.headers['Content-Type'] = 'application/json';
  }
  const r = await fetch(url, opts);
  return r.json();
}

// ---- Confirmation de suppression (helper) ----
function confirmDelete(msg = 'Confirmer la suppression ?') {
  return confirm(msg);
}

// Auto-dismiss des messages flash
setTimeout(() => {
  document.querySelectorAll('.flash').forEach(el => {
    el.style.transition = 'opacity .4s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 5000);
