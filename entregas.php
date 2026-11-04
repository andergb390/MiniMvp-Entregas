<?php if (isset($_GET['r'])) { goto api; } ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entregas MVP</title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--tx:#1b1f24;--mut:#667085;--bd:#e2e5ea;--ac:#0f766e;--actx:#fff;--dg:#b42318;--ok:#067647;--wa:#b54708}
@media (prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#0f1316;--card:#171c21;--tx:#e8ebee;--mut:#98a2b3;--bd:#2a313a;--ac:#2dd4bf;--actx:#06201c;--dg:#f97066;--ok:#47cd89;--wa:#fdb022}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:960px;margin:0 auto;padding:16px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:16px}
h1{font-size:20px;margin:0}h2{font-size:16px;margin:0}
header{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.mut{color:var(--mut);font-size:13px}
button{font:inherit;cursor:pointer;border:1px solid var(--bd);background:var(--card);color:var(--tx);border-radius:8px;padding:6px 12px}
button.p{background:var(--ac);color:var(--actx);border-color:var(--ac)}
button.d{color:var(--dg)}
input,select{font:inherit;width:100%;padding:8px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--tx)}
label{display:block;font-size:13px;color:var(--mut);margin:8px 0 2px}
.tabs{display:flex;gap:6px;margin-bottom:12px}
.tabs button.on{background:var(--ac);color:var(--actx);border-color:var(--ac)}
.sc{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:620px}
th,td{text-align:left;padding:8px;border-bottom:1px solid var(--bd);vertical-align:middle}
th{font-size:12px;color:var(--mut);text-transform:uppercase}
.b{padding:2px 8px;border-radius:99px;font-size:12px;border:1px solid var(--bd)}
.pendente{color:var(--wa)}.em_rota{color:var(--ac)}.entregue{color:var(--ok)}.cancelada{color:var(--dg)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 12px}
.row{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
#toast{position:fixed;left:50%;transform:translateX(-50%);bottom:16px;background:var(--tx);color:var(--bg);padding:10px 16px;border-radius:8px;display:none;max-width:90%;z-index:9}
.hid{display:none}
</style>
</head>
<body>
<div class="wrap">

  <div id="login" class="card hid" style="max-width:380px;margin:8vh auto 0">
    <h1>📦 Entregas MVP</h1>
    <p class="mut">Entre para gerenciar entregas e entregadores.</p>
    <label>E-mail</label><input id="em" type="email" value="admin@demo.com">
    <label>Senha</label><input id="pw" type="password" value="admin123" onkeydown="if(event.key==='Enter')doLogin()">
    <div class="row"><button class="p" onclick="doLogin()">Entrar</button></div>
    <p class="mut" style="margin-top:12px">Demo: admin@demo.com / admin123 (admin) ou op@demo.com / op123 (operador).</p>
    <p id="err" style="color:var(--dg)"></p>
  </div>

  <div id="app" class="hid">
    <header>
      <h1>📦 Entregas MVP</h1>
      <div><span class="mut" id="who"></span> <button onclick="logout()">Sair</button></div>
    </header>
    <div class="tabs">
      <button id="t-entregas" onclick="tab('entregas')">Entregas</button>
      <button id="t-entregadores" onclick="tab('entregadores')">Entregadores</button>
    </div>
    <div id="view"></div>
  </div>
</div>
<div id="toast"></div>

<script>
let me = null, ents = [], dels = [];
const LBL = { pendente: 'Pendente', em_rota: 'Em rota', entregue: 'Entregue', cancelada: 'Cancelada' };
const FLUXO = {
  pendente: ['pendente', 'em_rota', 'cancelada'],
  em_rota: ['em_rota', 'entregue', 'cancelada'],
  entregue: ['entregue'],
  cancelada: ['cancelada']
};
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const isAdmin = () => me && me.papel === 'admin';

function toast(m) {
  const t = $('toast'); t.textContent = m; t.style.display = 'block';
  clearTimeout(toast.h); toast.h = setTimeout(() => t.style.display = 'none', 3500);
}

// ===== Chamadas à API (?r=...) =====
async function api(r, method = 'GET', body = null, id = 0) {
  const res = await fetch('?r=' + r + (id ? '&id=' + id : ''), {
    method, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined
  });
  const d = await res.json().catch(() => ({ erro: 'Resposta inválida do servidor (confira se o PHP está rodando).' }));
  if (!res.ok) {
    if (res.status === 401 && r !== 'login') showLogin();
    throw new Error(d.erro || 'Erro inesperado.');
  }
  return d;
}
async function run(fn) { try { await fn(); } catch (e) { toast(e.message); } }

// ===== Login =====
function showLogin() { me = null; $('app').classList.add('hid'); $('login').classList.remove('hid'); }
function doLogin() {
  run(async () => {
    me = await api('login', 'POST', { email: $('em').value, senha: $('pw').value });
    showApp();
  });
}
async function logout() { try { await api('logout'); } catch (e) {} showLogin(); }
function showApp() {
  $('login').classList.add('hid'); $('app').classList.remove('hid');
  $('who').textContent = me.nome + ' (' + me.papel + ')';
  tab('entregas');
}

// ===== Telas =====
function tab(t) {
  ['entregas', 'entregadores'].forEach(x => $('t-' + x).className = x === t ? 'on' : '');
  run(async () => { t === 'entregas' ? await listEntregas() : await listEntregadores(); });
}

async function listEntregas() {
  ents = await api('entregas');
  const rows = ents.map(e => `<tr>
    <td>${esc(e.codigo)}</td>
    <td>${esc(e.cliente)}<div class="mut">${esc(e.endereco)}</div></td>
    <td>${e.entregador_nome ? esc(e.entregador_nome) : '—'}</td>
    <td>R$ ${Number(e.valor).toFixed(2)}</td>
    <td><span class="b ${e.status}">${LBL[e.status]}</span></td>
    <td style="white-space:nowrap"><button onclick="formEntrega(${e.id})">Editar</button>
      ${isAdmin() ? `<button class="d" onclick="delEntrega(${e.id})">Excluir</button>` : ''}</td></tr>`).join('');
  $('view').innerHTML = `<div class="card"><header><h2>Entregas</h2><button class="p" onclick="formEntrega()">+ Nova entrega</button></header>
    <div class="sc"><table><tr><th>Código</th><th>Cliente</th><th>Entregador</th><th>Valor</th><th>Status</th><th></th></tr>
    ${rows || '<tr><td colspan="6" class="mut">Nenhuma entrega.</td></tr>'}</table></div></div>`;
}

function formEntrega(id) {
  run(async () => {
    dels = await api('entregadores');
    const prox = 'ENT-' + String((ents.reduce((m, x) => Math.max(m, x.id), 0)) + 1).padStart(3, '0');
    const e = id ? ents.find(x => x.id === id) : { codigo: prox, cliente: '', endereco: '', entregador_id: null, status: 'pendente', valor: 0 };
    const opts = dels.map(x => `<option value="${x.id}" ${x.id == e.entregador_id ? 'selected' : ''}>${esc(x.nome)}${x.ativo == 1 ? '' : ' (inativo)'}</option>`).join('');
    const sts = FLUXO[e.status].map(s => `<option value="${s}" ${s === e.status ? 'selected' : ''}>${LBL[s]}</option>`).join('');
    $('view').innerHTML = `<div class="card"><h2>${id ? 'Editar entrega' : 'Nova entrega'}</h2>
      <div class="grid">
        <div><label>Código</label><input id="f-cod" value="${esc(e.codigo)}"></div>
        <div><label>Cliente</label><input id="f-cli" value="${esc(e.cliente)}"></div>
        <div><label>Endereço</label><input id="f-end" value="${esc(e.endereco)}"></div>
        <div><label>Valor (R$)</label><input id="f-val" type="number" step="0.01" value="${e.valor}"></div>
        <div><label>Entregador</label><select id="f-ent"><option value="">— nenhum —</option>${opts}</select></div>
        <div><label>Status</label><select id="f-sta">${sts}</select></div>
      </div>
      <div class="row"><button class="p" onclick="saveEntrega(${id || 0})">Salvar</button><button onclick="tab('entregas')">Cancelar</button></div></div>`;
  });
}
function saveEntrega(id) {
  run(async () => {
    const d = {
      codigo: $('f-cod').value, cliente: $('f-cli').value, endereco: $('f-end').value,
      valor: parseFloat($('f-val').value), entregador_id: $('f-ent').value || null, status: $('f-sta').value
    };
    id ? await api('entregas', 'PUT', d, id) : await api('entregas', 'POST', d);
    toast('Entrega salva.'); tab('entregas');
  });
}
function delEntrega(id) {
  if (!confirm('Excluir esta entrega?')) return;
  run(async () => { await api('entregas', 'DELETE', null, id); listEntregas(); });
}

async function listEntregadores() {
  dels = await api('entregadores');
  const rows = dels.map(e => `<tr>
    <td>${esc(e.nome)}</td><td>${esc(e.veiculo)}</td><td>${e.ativo == 1 ? 'Ativo' : 'Inativo'}</td>
    <td>${e.em_rota}/5 em rota</td>
    <td style="white-space:nowrap"><button onclick="formEntregador(${e.id})">Editar</button>
      ${isAdmin() ? `<button class="d" onclick="delEntregador(${e.id})">Excluir</button>` : ''}</td></tr>`).join('');
  $('view').innerHTML = `<div class="card"><header><h2>Entregadores</h2><button class="p" onclick="formEntregador()">+ Novo entregador</button></header>
    <div class="sc"><table><tr><th>Nome</th><th>Veículo</th><th>Situação</th><th>Carga</th><th></th></tr>${rows}</table></div></div>`;
}
function formEntregador(id) {
  const e = id ? dels.find(x => x.id === id) : { nome: '', veiculo: 'Moto', ativo: 1 };
  const veic = ['Moto', 'Carro', 'Bicicleta', 'Van'].map(v => `<option ${v === e.veiculo ? 'selected' : ''}>${v}</option>`).join('');
  $('view').innerHTML = `<div class="card"><h2>${id ? 'Editar entregador' : 'Novo entregador'}</h2>
    <div class="grid">
      <div><label>Nome</label><input id="g-nom" value="${esc(e.nome)}"></div>
      <div><label>Veículo</label><select id="g-vei">${veic}</select></div>
      <div><label>Situação</label><select id="g-ati"><option value="1" ${e.ativo == 1 ? 'selected' : ''}>Ativo</option><option value="0" ${e.ativo == 1 ? '' : 'selected'}>Inativo</option></select></div>
    </div>
    <div class="row"><button class="p" onclick="saveEntregador(${id || 0})">Salvar</button><button onclick="tab('entregadores')">Cancelar</button></div></div>`;
}
function saveEntregador(id) {
  run(async () => {
    const d = { nome: $('g-nom').value, veiculo: $('g-vei').value, ativo: $('g-ati').value === '1' };
    id ? await api('entregadores', 'PUT', d, id) : await api('entregadores', 'POST', d);
    toast('Entregador salvo.'); tab('entregadores');
  });
}
function delEntregador(id) {
  if (!confirm('Excluir este entregador?')) return;
  run(async () => { await api('entregadores', 'DELETE', null, id); listEntregadores(); });
}

// ===== Início: já está logado? =====
(async () => {
  try { me = await api('me'); showApp(); } catch (e) { showLogin(); if (e.message !== 'Não autenticado.') $('err').textContent = e.message + ' (confira DB_USER, DB_PASS e DB_PORT no fim deste arquivo)'; }
})();
</script>
</body>
</html>
<?php
// =====================================================================
//  PHP — configuração, conexão com o banco e API
//  (tudo abaixo roda só nas chamadas ?r=..., o HTML acima é a tela)
// =====================================================================
exit;
api:

// ----- CONFIGURAÇÃO (padrão do Laragon: root, sem senha) -----
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'entregas_mvp';
const DB_USER = 'root';
const DB_PASS = '';

session_start();
$r = $_GET['r'] ?? '';

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }
function fail($m, $c = 400) { out(['erro' => $m], $c); }
function erroFatal($msg) { header('Content-Type: application/json; charset=utf-8'); out(['erro' => $msg], 500); }

// ----- CONEXÃO COM O BANCO (as tabelas vêm do arquivo modelo_dados.sql) -----
if (!extension_loaded('pdo_mysql')) erroFatal('Extensão pdo_mysql desativada. No Laragon: Menu > PHP > Extensões > pdo_mysql.');
try {
  $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Exception $e) {
  erroFatal('Não conectou no banco "' . DB_NAME . '". Rode antes o modelo_dados.sql no DBeaver. Detalhe: ' . $e->getMessage());
}

// ----- API (com ?r=) -----
header('Content-Type: application/json; charset=utf-8');
$id = (int)($_GET['id'] ?? 0);
$m  = $_SERVER['REQUEST_METHOD'];
$in = json_decode(file_get_contents('php://input'), true) ?: [];

// ===== API: login / sessão =====
if ($r === 'login' && $m === 'POST') {
  $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
  $st->execute([strtolower(trim($in['email'] ?? ''))]);
  $u = $st->fetch();
  $pw = $in['senha'] ?? '';
  $ok = false;
  if ($u) {
    if (password_verify($pw, $u['senha_hash'])) {
      $ok = true;
    } elseif (hash_equals($u['senha_hash'], hash('sha256', $pw))) {
      // senha inicial do script SQL (SHA2): migra para bcrypt no primeiro login
      $ok = true;
      $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
          ->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
    }
  }
  if (!$ok) fail('E-mail ou senha inválidos.', 401);
  session_regenerate_id(true);
  $_SESSION['u'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'papel' => $u['papel']];
  out($_SESSION['u']);
}
if ($r === 'logout') { session_destroy(); out(['ok' => true]); }

// ===== Tudo abaixo exige login =====
if (empty($_SESSION['u'])) fail('Não autenticado.', 401);
$me = $_SESSION['u'];
if ($r === 'me') out($me);

function soAdmin() {
  global $me;
  if ($me['papel'] !== 'admin') fail('Apenas admin pode excluir.', 403);
}

// ===== Regras de negócio das entregas =====
// 1) Fluxo: pendente -> em_rota -> entregue (cancelar até entregar)
// 2) Para ir em rota/entregue precisa de entregador ativo
// 3) Máximo de 5 entregas em rota por entregador
const FLUXO = [
  'pendente'  => ['pendente', 'em_rota', 'cancelada'],
  'em_rota'   => ['em_rota', 'entregue', 'cancelada'],
  'entregue'  => ['entregue'],
  'cancelada' => ['cancelada'],
];

function validarEntrega($pdo, $d, $idAtual = 0) {
  if (!trim($d['cliente'] ?? '') || !trim($d['endereco'] ?? '')) fail('Cliente e endereço são obrigatórios.');
  if (!isset($d['valor']) || !is_numeric($d['valor']) || $d['valor'] < 0) fail('Valor inválido.');
  if (!isset(FLUXO[$d['status'] ?? ''])) fail('Status inválido.');

  if ($idAtual) {
    $st = $pdo->prepare('SELECT status FROM entregas WHERE id = ?');
    $st->execute([$idAtual]);
    $atual = $st->fetchColumn();
    if (!$atual) fail('Entrega não encontrada.', 404);
    if (!in_array($d['status'], FLUXO[$atual])) fail("Não é possível mudar de '$atual' para '{$d['status']}'.");
  }

  if (in_array($d['status'], ['em_rota', 'entregue'])) {
    $eid = (int)($d['entregador_id'] ?? 0);
    $st = $pdo->prepare('SELECT ativo FROM entregadores WHERE id = ?');
    $st->execute([$eid]);
    $ativo = $st->fetchColumn();
    if ($ativo === false) fail('Defina um entregador para colocar a entrega em rota.');
    if (!$ativo) fail('Entregador inativo não pode receber entregas.');
  }
  if ($d['status'] === 'em_rota') {
    $st = $pdo->prepare("SELECT COUNT(*) FROM entregas WHERE entregador_id = ? AND status = 'em_rota' AND id <> ?");
    $st->execute([(int)$d['entregador_id'], $idAtual]);
    if ($st->fetchColumn() >= 5) fail('Este entregador já tem 5 entregas em rota (limite).');
  }
}

// ===== CRUD: entregas =====
if ($r === 'entregas') {
  if ($m === 'GET') {
    out($pdo->query('SELECT e.*, d.nome AS entregador_nome FROM entregas e
                     LEFT JOIN entregadores d ON d.id = e.entregador_id ORDER BY e.id DESC')->fetchAll());
  }
  if ($m === 'POST' || $m === 'PUT') {
    $eid = $in['entregador_id'] ?? null;
    $d = [
      'codigo' => trim($in['codigo'] ?? ''), 'cliente' => trim($in['cliente'] ?? ''),
      'endereco' => trim($in['endereco'] ?? ''), 'valor' => $in['valor'] ?? null,
      'status' => $in['status'] ?? 'pendente', 'entregador_id' => $eid ? (int)$eid : null,
    ];
    if (!$d['codigo']) fail('Informe o código.');
    validarEntrega($pdo, $d, $m === 'PUT' ? $id : 0);
    try {
      if ($m === 'POST') {
        $pdo->prepare('INSERT INTO entregas (codigo, cliente, endereco, entregador_id, status, valor) VALUES (?,?,?,?,?,?)')
            ->execute([$d['codigo'], $d['cliente'], $d['endereco'], $d['entregador_id'], $d['status'], $d['valor']]);
        out(['id' => (int)$pdo->lastInsertId()], 201);
      } else {
        $pdo->prepare('UPDATE entregas SET codigo=?, cliente=?, endereco=?, entregador_id=?, status=?, valor=? WHERE id=?')
            ->execute([$d['codigo'], $d['cliente'], $d['endereco'], $d['entregador_id'], $d['status'], $d['valor'], $id]);
        out(['ok' => true]);
      }
    } catch (PDOException $e) {
      fail($e->getCode() === '23000' ? 'Já existe uma entrega com esse código.' : 'Erro ao salvar.');
    }
  }
  if ($m === 'DELETE') {
    soAdmin();
    $pdo->prepare('DELETE FROM entregas WHERE id = ?')->execute([$id]);
    out(['ok' => true]);
  }
}

// ===== CRUD: entregadores =====
if ($r === 'entregadores') {
  if ($m === 'GET') {
    out($pdo->query("SELECT d.*, (SELECT COUNT(*) FROM entregas e WHERE e.entregador_id = d.id AND e.status = 'em_rota') AS em_rota
                     FROM entregadores d ORDER BY d.nome")->fetchAll());
  }
  if ($m === 'POST' || $m === 'PUT') {
    $nome = trim($in['nome'] ?? '');
    if (!$nome) fail('Informe o nome.');
    $vei = $in['veiculo'] ?? 'Moto';
    $ati = !empty($in['ativo']) ? 1 : 0;
    if ($m === 'POST') {
      $pdo->prepare('INSERT INTO entregadores (nome, veiculo, ativo) VALUES (?,?,?)')->execute([$nome, $vei, $ati]);
      out(['id' => (int)$pdo->lastInsertId()], 201);
    }
    $pdo->prepare('UPDATE entregadores SET nome=?, veiculo=?, ativo=? WHERE id=?')->execute([$nome, $vei, $ati, $id]);
    out(['ok' => true]);
  }
  if ($m === 'DELETE') {
    soAdmin();
    $st = $pdo->prepare("SELECT COUNT(*) FROM entregas WHERE entregador_id = ? AND status IN ('pendente','em_rota')");
    $st->execute([$id]);
    if ($st->fetchColumn() > 0) fail('Entregador com entregas abertas não pode ser excluído.');
    try {
      $pdo->prepare('DELETE FROM entregadores WHERE id = ?')->execute([$id]);
      out(['ok' => true]);
    } catch (PDOException $e) {
      fail('Entregador tem histórico de entregas. Marque-o como inativo em vez de excluir.');
    }
  }
}

fail('Rota não encontrada.', 404);