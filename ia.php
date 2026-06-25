<?php
/**
 * ia.php — Assistant IA (via proxy serveur sécurisé).
 * La clé API n'apparaît JAMAIS dans cette page.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_login();
$bout = active_boutique();
?>
<?php layout_header('Assistant IA', 'ia'); ?>

<div style="max-width:800px;margin:0 auto">
  <div class="card" style="height:75vh;display:flex;flex-direction:column;padding:0;overflow:hidden">
    <div class="card-h" style="padding:1.2rem 1.4rem;border-bottom:1px solid var(--bd)">
      <h3><i class="fas fa-robot" style="color:var(--gold)"></i> Assistant IA — <?= e($bout['nom'] ?? 'ALT STORE') ?></h3>
      <span class="badge bg-green"><i class="fas fa-shield-halved"></i> Sécurisé</span>
    </div>

    <div id="chat" style="flex:1;overflow-y:auto;padding:1.4rem;display:flex;flex-direction:column;gap:1rem">
      <div class="msg msg-bot">
        <div class="msg-av bot"><i class="fas fa-robot"></i></div>
        <div class="msg-bubble">
          👋 Bonjour ! Je suis votre assistant IA ALT STORE. Posez-moi vos questions sur la gestion :
          stocks, ventes, clients, facturation... <br><br>
          <em style="color:var(--muted);font-size:12px">Ex : « Comment réduire mes ruptures de stock ? », « Quel est le meilleur mode de paiement ? »</em>
        </div>
      </div>
    </div>

    <form id="chatForm" style="padding:1rem 1.4rem;border-top:1px solid var(--bd);display:flex;gap:.6rem">
      <input id="msgInput" placeholder="Écrivez votre message..." autocomplete="off"
             style="flex:1;padding:12px 16px;background:var(--bg);border:1px solid var(--bd);border-radius:24px;color:var(--text);font-size:14px">
      <button class="btn btn-primary" type="submit" style="border-radius:50%;width:46px;height:46px;padding:0;justify-content:center"><i class="fas fa-paper-plane"></i></button>
    </form>
  </div>
</div>

<style>
.msg{display:flex;gap:10px;align-items:flex-start}
.msg.user{flex-direction:row-reverse}
.msg-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px}
.msg-av.bot{background:linear-gradient(135deg,var(--ember),var(--gold));color:#fff}
.msg-av.usr{background:var(--blue);color:#fff}
.msg-bubble{max-width:75%;padding:11px 15px;border-radius:14px;font-size:13.5px;line-height:1.55}
.msg-bot .msg-bubble{background:var(--surf2);border:1px solid var(--bd);border-top-left-radius:4px}
.msg.user .msg-bubble{background:var(--ember);color:#fff;border-top-right-radius:4px}
.typing span{display:inline-block;width:7px;height:7px;background:var(--muted);border-radius:50%;margin:0 1px;animation:bounce 1.2s infinite}
.typing span:nth-child(2){animation-delay:.2s}.typing span:nth-child(3){animation-delay:.4s}
@keyframes bounce{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-6px);opacity:1}}
</style>
<script>
const chatEl = document.getElementById('chat');
const form = document.getElementById('chatForm');
const input = document.getElementById('msgInput');
let history = [];

function addMsg(role, text) {
  const isUser = role === 'user';
  const div = document.createElement('div');
  div.className = 'msg ' + (isUser ? 'user' : 'bot');
  div.innerHTML = `<div class="msg-av ${isUser?'usr':'bot'}"><i class="fas ${isUser?'fa-user':'fa-robot'}"></i></div>
    <div class="msg-bubble">${text.replace(/</g,'&lt;')}</div>`;
  chatEl.appendChild(div);
  chatEl.scrollTop = chatEl.scrollHeight;
  return div;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = input.value.trim();
  if (!msg) return;
  addMsg('user', msg);
  input.value = '';
  history.push({ role: 'user', content: msg });

  // Typing indicator
  const typing = addMsg('bot', '<div class="typing"><span></span><span></span><span></span></div>');

  try {
    const fd = new FormData();
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fd.append('message', msg);
    fd.append('history', JSON.stringify(history));
    const r = await fetch(APP_URL + '/api/ia_proxy.php', { method: 'POST', body: fd });
    const data = await r.json();
    typing.querySelector('.msg-bubble').innerHTML = data.success
      ? data.reply.replace(/</g,'&lt;').replace(/\n/g,'<br>')
      : '⚠️ ' + (data.message || 'Erreur');
    if (data.success) history.push({ role: 'assistant', content: data.reply });
  } catch (err) {
    typing.querySelector('.msg-bubble').innerHTML = '⚠️ Erreur réseau.';
  }
  chatEl.scrollTop = chatEl.scrollHeight;
});
</script>
<?php layout_footer(); ?>
