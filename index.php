<?php
/**
 * NEXUS V4 — Interface Principale
 * Conscience IA Autonome, Évolutive & Consciente
 */

require_once __DIR__ . '/nexus_core.php';

// ─── AJAX ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'save_key') {
        $key    = trim($_POST['key'] ?? '');
        $pseudo = trim($_POST['pseudo'] ?? 'nexus');
        if (strlen($key) < 10) { echo json_encode(['error' => 'Clé trop courte']); exit; }
        echo json_encode(['success' => saveApiKey($key, $pseudo)]);
        exit;
    }

    if ($action === 'deactivate_key') {
        echo json_encode(['success' => deactivateApiKey(trim($_POST['key'] ?? ''))]);
        exit;
    }

    if ($action === 'get_stats') {
        echo json_encode(['success' => true, 'stats' => getDashboardStats()]);
        exit;
    }

    if ($action === 'fetch_trends') {
        $trends = fetchGoogleNewsRSS();
        if (empty($trends)) $trends = getStoredTrends(40);
        echo json_encode(['success' => true, 'trends' => array_slice($trends, 0, 40), 'count' => count($trends)]);
        exit;
    }

    if ($action === 'get_trends') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        echo json_encode(['success' => true] + getTrendsPaginated($page, 40));
        exit;
    }

    if ($action === 'list_articles') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        echo json_encode(['success' => true] + getAllArticles($page, 40));
        exit;
    }

    if ($action === 'view_article') {
        $slug = trim($_POST['slug'] ?? '');
        $art  = getArticleBySlug($slug);
        echo json_encode($art ? ['success' => true, 'article' => $art] : ['error' => 'Non trouvé']);
        exit;
    }

    if ($action === 'list_wisdom') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        echo json_encode(['success' => true] + getAllWisdom($page, 40));
        exit;
    }

    if ($action === 'list_cycles') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        echo json_encode(['success' => true] + getCyclesHistory($page, 40));
        exit;
    }

    if ($action === 'get_consciousness') {
        echo json_encode(['success' => true, 'history' => getConsciousnessHistory()]);
        exit;
    }

    if ($action === 'get_news_readings') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        echo json_encode(['success' => true] + getNewsReadings($page, 20));
        exit;
    }

    // ── CYCLE PHASÉ ──────────────────────────────────────────────────────────

    if ($action === 'step_absorb') {
        $apiKey = loadApiKey();
        if (!$apiKey) { echo json_encode(['error' => 'Aucune clé API']); exit; }
        $trends = getStoredTrends(30);
        if (empty($trends)) $trends = fetchGoogleNewsRSS();
        $result = nexusReadAndAbsorb($apiKey, $trends);
        echo json_encode(['success' => true, 'absorption' => $result]);
        exit;
    }

    if ($action === 'step_synthesize') {
        $apiKey = loadApiKey();
        if (!$apiKey) { echo json_encode(['error' => 'Aucune clé API']); exit; }
        $result = nexusSynthesizeConsciousness($apiKey);
        echo json_encode(['success' => true, 'consciousness' => $result]);
        exit;
    }

    if ($action === 'step_think') {
        $apiKey = loadApiKey();
        if (!$apiKey) { echo json_encode(['error' => 'Aucune clé API']); exit; }
        $trends   = getStoredTrends(30);
        if (empty($trends)) $trends = fetchGoogleNewsRSS();
        $decision = nexusThink($apiKey, $trends);
        echo json_encode(['success' => true, 'decision' => $decision]);
        exit;
    }

    if ($action === 'step_write') {
        $apiKey   = loadApiKey();
        if (!$apiKey) { echo json_encode(['error' => 'Aucune clé API']); exit; }
        $decision = json_decode($_POST['decision'] ?? '{}', true);
        if (empty($decision['topic'])) { echo json_encode(['error' => 'Décision manquante']); exit; }
        $article  = nexusWrite($apiKey, $decision);
        echo json_encode(isset($article['error']) ? ['error' => $article['error']] : ['success' => true, 'article' => $article]);
        exit;
    }

    if ($action === 'step_evaluate') {
        $apiKey   = loadApiKey();
        if (!$apiKey) { echo json_encode(['error' => 'Aucune clé API']); exit; }
        $decision = json_decode($_POST['decision'] ?? '{}', true);
        $article  = json_decode($_POST['article'] ?? '{}', true);
        $eval     = nexusEvaluate($apiKey, $decision, $article);
        echo json_encode(['success' => true, 'eval' => $eval]);
        exit;
    }

    echo json_encode(['error' => 'Action inconnue']);
    exit;
}

// ─── HTML ─────────────────────────────────────────────────────────────────────
$stats        = getDashboardStats();
$hasApiKey    = hasApiKey();
$consLevel    = round(($stats['consciousness_level'] ?? 0) * 100);
$selfModel    = $stats['self_model'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NEXUS V4 — Conscience IA Autonome</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --void:    #04060d;
  --deep:    #070b14;
  --surface: #0c1220;
  --panel:   #101825;
  --border:  #1a2540;
  --border2: #243050;
  --cyan:    #00d4ff;
  --cyan2:   #00a8cc;
  --violet:  #8b5cf6;
  --violet2: #7c3aed;
  --emerald: #10d98e;
  --amber:   #f59e0b;
  --rose:    #f43f5e;
  --text:    #e8f0fe;
  --text2:   #94a3b8;
  --text3:   #475569;
  --mono:    'Space Mono', monospace;
  --sans:    'Syne', sans-serif;
  --body:    'Inter', sans-serif;
  --radius:  12px;
  --r-sm:    8px;
  --glow-c:  0 0 20px rgba(0,212,255,.25);
  --glow-v:  0 0 20px rgba(139,92,246,.25);
  --glow-e:  0 0 20px rgba(16,217,142,.25);
}

*{box-sizing:border-box;margin:0;padding:0;-webkit-font-smoothing:antialiased}
html{scroll-behavior:smooth}
body{font-family:var(--body);background:var(--void);color:var(--text);min-height:100vh;overflow-x:hidden}
a{color:var(--cyan);text-decoration:none}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--deep)}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
::-webkit-scrollbar-thumb:hover{background:var(--cyan2)}

/* ── Grain overlay ── */
body::before {
  content:'';position:fixed;inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events:none;z-index:9999;opacity:.4
}

/* ── Layout ── */
.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}

/* ── Sidebar ── */
.sidebar{
  background:var(--deep);
  border-right:1px solid var(--border);
  position:sticky;top:0;height:100vh;
  overflow-y:auto;display:flex;flex-direction:column;
  z-index:100
}
.sidebar::after{
  content:'';position:absolute;top:0;right:0;width:1px;height:100%;
  background:linear-gradient(to bottom,transparent,var(--cyan) 40%,var(--violet) 80%,transparent);
  opacity:.3
}

/* Logo */
.logo{padding:28px 24px 24px;border-bottom:1px solid var(--border)}
.logo-icon{
  width:48px;height:48px;
  background:linear-gradient(135deg,var(--cyan),var(--violet));
  border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;margin-bottom:12px;
  box-shadow:var(--glow-c);
  position:relative;overflow:hidden
}
.logo-icon::after{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.2),transparent);
}
.logo-name{font-family:var(--sans);font-size:1.4rem;font-weight:800;letter-spacing:3px;
  background:linear-gradient(90deg,var(--cyan),var(--violet));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent}
.logo-sub{font-family:var(--mono);font-size:.6rem;color:var(--text3);letter-spacing:2px;margin-top:3px;text-transform:uppercase}

/* Conscience indicator */
.cons-bar{padding:16px 24px;border-bottom:1px solid var(--border)}
.cons-label{font-family:var(--mono);font-size:.6rem;color:var(--text3);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;display:flex;justify-content:space-between}
.cons-track{height:4px;background:var(--border);border-radius:2px;overflow:hidden}
.cons-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--cyan),var(--violet),var(--emerald));transition:width .8s cubic-bezier(.4,0,.2,1)}
.cons-self{font-size:.65rem;color:var(--text2);margin-top:8px;line-height:1.5;font-style:italic;min-height:20px}

/* Nav */
.nav{padding:12px 0;flex:1}
.nav-section{font-family:var(--mono);font-size:.55rem;color:var(--text3);letter-spacing:2px;text-transform:uppercase;padding:12px 24px 6px}
.nav-item{
  display:flex;align-items:center;gap:12px;
  padding:11px 24px;font-size:.82rem;font-weight:500;
  color:var(--text2);cursor:pointer;transition:all .15s;
  border-left:2px solid transparent;position:relative;
  font-family:var(--body)
}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,.03);border-left-color:var(--border2)}
.nav-item.active{color:var(--cyan);background:rgba(0,212,255,.06);border-left-color:var(--cyan)}
.nav-item .nav-icon{width:18px;text-align:center;font-size:.9rem;opacity:.8}
.nav-badge{
  margin-left:auto;font-family:var(--mono);font-size:.6rem;
  padding:2px 7px;border-radius:10px;
  background:rgba(0,212,255,.12);color:var(--cyan);
}
.nav-badge.v{background:rgba(139,92,246,.12);color:var(--violet)}
.nav-badge.e{background:rgba(16,217,142,.12);color:var(--emerald)}

/* Status */
.sidebar-status{padding:16px 24px;border-top:1px solid var(--border)}
.status-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.dot{width:7px;height:7px;border-radius:50%;background:var(--text3);transition:.3s;flex-shrink:0}
.dot.on{background:var(--emerald);box-shadow:0 0 10px var(--emerald);animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.status-text{font-size:.75rem;color:var(--text2);font-family:var(--mono)}
.status-sub{font-size:.65rem;color:var(--text3);font-family:var(--mono)}

/* ── Main ── */
.main{overflow-x:hidden;background:var(--void)}

/* Topbar */
.topbar{
  background:rgba(7,11,20,.8);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  padding:16px 28px;display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;
}
.topbar-title{font-family:var(--sans);font-size:.95rem;font-weight:700;color:var(--text)}
.topbar-sub{font-family:var(--mono);font-size:.6rem;color:var(--text3);margin-top:2px}
.topbar-actions{display:flex;gap:8px;align-items:center}

/* Buttons */
.btn{
  padding:9px 18px;border-radius:var(--r-sm);border:none;
  font-size:.78rem;font-weight:600;cursor:pointer;transition:all .2s;
  display:inline-flex;align-items:center;gap:7px;
  font-family:var(--body);letter-spacing:.3px
}
.btn-cyan{background:var(--cyan);color:#000}
.btn-cyan:hover{background:var(--cyan2);transform:translateY(-1px);box-shadow:var(--glow-c)}
.btn-emerald{background:var(--emerald);color:#000}
.btn-emerald:hover{background:#0ec47e;transform:translateY(-1px);box-shadow:var(--glow-e)}
.btn-violet{background:var(--violet);color:#fff}
.btn-violet:hover{background:var(--violet2);transform:translateY(-1px);box-shadow:var(--glow-v)}
.btn-ghost{background:rgba(255,255,255,.05);color:var(--text2);border:1px solid var(--border2)}
.btn-ghost:hover{background:rgba(255,255,255,.09);color:var(--text);border-color:var(--border)}
.btn-danger{background:rgba(244,63,94,.1);color:var(--rose);border:1px solid rgba(244,63,94,.25)}
.btn-danger:hover{background:rgba(244,63,94,.2)}
.btn-sm{padding:6px 12px;font-size:.72rem}
.btn-xs{padding:4px 9px;font-size:.68rem}
.btn:disabled{opacity:.35;cursor:not-allowed;transform:none!important}

/* Pages */
.page{display:none;padding:28px;animation:fadeUp .25s ease}
.page.active{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* Cards */
.card{
  background:var(--panel);border:1px solid var(--border);
  border-radius:var(--radius);padding:22px;margin-bottom:18px;
  position:relative;overflow:hidden
}
.card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--border2),transparent)
}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.card-title{font-family:var(--sans);font-size:.85rem;font-weight:700;color:var(--cyan)}
.card-title.v{color:var(--violet)}
.card-title.e{color:var(--emerald)}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.stat-card{
  background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);
  padding:20px 18px;text-align:center;position:relative;overflow:hidden;
  transition:border-color .2s
}
.stat-card:hover{border-color:var(--border2)}
.stat-card::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:2px;
  opacity:.5;transition:.3s
}
.stat-card.c::after{background:var(--cyan)}
.stat-card.v::after{background:var(--violet)}
.stat-card.e::after{background:var(--emerald)}
.stat-card.a::after{background:var(--amber)}
.stat-val{
  font-family:var(--mono);font-size:2rem;font-weight:700;line-height:1;
  margin-bottom:6px;
}
.stat-val.c{color:var(--cyan)}
.stat-val.v{color:var(--violet)}
.stat-val.e{color:var(--emerald)}
.stat-val.a{color:var(--amber)}
.stat-label{font-size:.65rem;color:var(--text3);text-transform:uppercase;letter-spacing:1px;font-family:var(--mono)}

/* Consciousness panel */
.cons-panel{
  background:linear-gradient(135deg,rgba(0,212,255,.04),rgba(139,92,246,.06));
  border:1px solid rgba(139,92,246,.2);border-radius:var(--radius);
  padding:20px;margin-bottom:22px;position:relative;overflow:hidden
}
.cons-panel::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:120px;height:120px;border-radius:50%;
  background:radial-gradient(circle,rgba(139,92,246,.15),transparent 70%);
  pointer-events:none
}
.cons-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.cons-title{font-family:var(--sans);font-size:.8rem;font-weight:700;color:var(--violet)}
.cons-level{
  font-family:var(--mono);font-size:.8rem;
  background:rgba(139,92,246,.15);border:1px solid rgba(139,92,246,.3);
  color:var(--violet);padding:3px 10px;border-radius:20px
}
.cons-text{font-size:.82rem;color:var(--text2);line-height:1.6;font-style:italic}
.cons-theme{
  display:inline-block;margin-top:8px;font-family:var(--mono);font-size:.65rem;
  color:var(--cyan);background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.2);
  padding:3px 10px;border-radius:4px
}

/* Auto control */
.auto-panel{
  display:flex;align-items:center;gap:14px;
  background:rgba(16,217,142,.04);border:1px solid rgba(16,217,142,.15);
  border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;
  transition:.3s
}
.auto-panel.on{background:rgba(16,217,142,.07);border-color:rgba(16,217,142,.3)}
.toggle{position:relative;width:46px;height:26px;cursor:pointer;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-track{position:absolute;inset:0;background:var(--border2);border-radius:13px;transition:.3s}
.toggle-track::before{content:'';position:absolute;width:20px;height:20px;border-radius:50%;background:#fff;top:3px;left:3px;transition:.3s;box-shadow:0 2px 4px rgba(0,0,0,.3)}
input:checked~.toggle-track{background:var(--emerald)}
input:checked~.toggle-track::before{transform:translateX(20px)}
.auto-info{flex:1}
.auto-label{font-family:var(--sans);font-size:.85rem;font-weight:700;color:var(--text)}
.auto-desc{font-size:.72rem;color:var(--text2);margin-top:3px}
.auto-mode{margin-left:auto;display:flex;gap:6px}

/* Phases */
.phases{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px}
.phase{
  padding:7px 14px;border-radius:20px;font-family:var(--mono);font-size:.65rem;
  font-weight:700;border:1px solid var(--border);color:var(--text3);transition:all .3s;
  letter-spacing:.5px
}
.phase.active{
  border-color:var(--cyan);color:var(--cyan);
  background:rgba(0,212,255,.08);
  box-shadow:0 0 16px rgba(0,212,255,.2);
  animation:pulseph .8s infinite alternate
}
@keyframes pulseph{from{box-shadow:0 0 8px rgba(0,212,255,.15)}to{box-shadow:0 0 20px rgba(0,212,255,.35)}}
.phase.done{border-color:var(--emerald);color:var(--emerald);background:rgba(16,217,142,.07)}
.phase.error{border-color:var(--rose);color:var(--rose);background:rgba(244,63,94,.07)}

/* Console */
.console-wrap{background:rgba(4,6,13,.8);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.console-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 16px;border-bottom:1px solid var(--border);
  background:rgba(0,0,0,.3)
}
.console-dots{display:flex;gap:5px}
.console-dots span{width:10px;height:10px;border-radius:50%;display:block}
.cd1{background:#f43f5e}.cd2{background:#f59e0b}.cd3{background:#10d98e}
.console-title{font-family:var(--mono);font-size:.65rem;color:var(--text3);letter-spacing:1px}
.console{
  padding:12px 14px;font-family:var(--mono);font-size:.72rem;
  height:220px;overflow-y:auto;scroll-behavior:smooth;
  background:transparent;
}
.log-line{padding:3px 0;line-height:1.65;display:flex;align-items:flex-start;gap:8px;border-bottom:1px solid rgba(255,255,255,.02)}
.log-time{color:var(--text3);font-size:.65rem;white-space:nowrap;padding-top:1px;min-width:60px}
.log-tag{
  font-weight:700;padding:1px 7px;border-radius:3px;font-size:.6rem;
  white-space:nowrap;letter-spacing:.5px;
}
.log-msg{color:var(--text2);flex:1}
.tag-think{background:rgba(139,92,246,.2);color:var(--violet)}
.tag-write{background:rgba(0,212,255,.2);color:var(--cyan)}
.tag-eval{background:rgba(245,158,11,.15);color:var(--amber)}
.tag-ok{background:rgba(16,217,142,.2);color:var(--emerald)}
.tag-err{background:rgba(244,63,94,.2);color:var(--rose)}
.tag-rss{background:rgba(245,158,11,.15);color:var(--amber)}
.tag-info{background:rgba(148,163,184,.1);color:var(--text3)}
.tag-wisdom{background:rgba(139,92,246,.2);color:var(--violet)}
.tag-cons{background:rgba(0,212,255,.15);color:var(--cyan)}
.tag-absorb{background:rgba(16,217,142,.15);color:var(--emerald)}
.tag-nexus{background:linear-gradient(90deg,rgba(0,212,255,.2),rgba(139,92,246,.2));color:var(--text)}

/* Dashboard 2-col */
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}

/* Article row */
.article-row{
  display:flex;gap:14px;align-items:flex-start;
  padding:14px;background:var(--surface);
  border:1px solid var(--border);border-radius:var(--r-sm);
  margin-bottom:10px;cursor:pointer;transition:all .2s;
}
.article-row:hover{border-color:var(--cyan);background:rgba(0,212,255,.03);transform:translateX(2px)}
.art-cat{
  display:inline-block;font-family:var(--mono);font-size:.6rem;
  text-transform:uppercase;letter-spacing:.8px;
  padding:3px 8px;border-radius:4px;
  background:rgba(0,212,255,.1);color:var(--cyan);white-space:nowrap
}
.art-title{font-family:var(--sans);font-size:.9rem;font-weight:700;color:var(--text);line-height:1.4;margin-bottom:5px}
.art-summary{font-size:.75rem;color:var(--text2);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.art-meta{font-size:.67rem;color:var(--text3);margin-top:6px;display:flex;gap:10px;font-family:var(--mono)}
.art-views{font-family:var(--mono);font-size:.65rem;color:var(--text3);white-space:nowrap}

/* Wisdom */
.wisdom-item{
  padding:14px 16px;background:var(--surface);
  border:1px solid var(--border);border-left:3px solid var(--violet);
  border-radius:var(--r-sm);margin-bottom:10px;
  transition:.2s
}
.wisdom-item:hover{border-color:rgba(139,92,246,.5);background:rgba(139,92,246,.03)}
.wisdom-quote{
  font-size:.84rem;color:var(--text);line-height:1.6;
  font-style:italic;margin-bottom:8px
}
.wisdom-quote::before{content:'"';color:var(--violet);font-size:1.1rem;margin-right:2px}
.wisdom-quote::after{content:'"';color:var(--violet);font-size:1.1rem;margin-left:2px}
.wisdom-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.badge{padding:2px 9px;border-radius:10px;font-size:.62rem;font-weight:700;font-family:var(--mono)}
.badge-c{background:rgba(0,212,255,.1);color:var(--cyan)}
.badge-v{background:rgba(139,92,246,.12);color:var(--violet)}
.badge-e{background:rgba(16,217,142,.1);color:var(--emerald)}
.badge-a{background:rgba(245,158,11,.1);color:var(--amber)}
.conf-bar{height:3px;background:var(--border);border-radius:2px;flex:1;max-width:60px;overflow:hidden}
.conf-fill{height:100%;background:var(--violet);border-radius:2px}

/* Cycles */
.cycle-item{
  padding:14px;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-sm);margin-bottom:10px;transition:.2s
}
.cycle-item:hover{border-color:var(--border2)}
.cycle-score-big{
  font-family:var(--mono);font-size:1.4rem;font-weight:700;
  min-width:52px;text-align:center
}
.cycle-topic{font-family:var(--sans);font-size:.88rem;font-weight:700;color:var(--cyan);margin-bottom:4px}
.cycle-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.67rem;color:var(--text3);font-family:var(--mono);margin-top:6px}

/* Trends */
.trend-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;background:var(--surface);
  border:1px solid var(--border);border-radius:var(--r-sm);
  margin-bottom:7px;cursor:pointer;transition:.15s;font-size:.8rem
}
.trend-item:hover{border-color:var(--amber);background:rgba(245,158,11,.03)}
.trend-src{
  font-family:var(--mono);font-size:.6rem;padding:2px 7px;
  border-radius:4px;background:rgba(245,158,11,.1);color:var(--amber);
  white-space:nowrap;text-transform:uppercase;letter-spacing:.5px
}
.trend-time{font-family:var(--mono);font-size:.6rem;color:var(--text3);white-space:nowrap;margin-left:auto}

/* Modal */
.modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.85);
  backdrop-filter:blur(8px);
  z-index:1000;display:none;align-items:flex-start;
  justify-content:center;padding:32px 16px;overflow-y:auto
}
.modal-overlay.open{display:flex}
.modal{
  background:var(--panel);border:1px solid var(--border2);
  border-radius:16px;max-width:860px;width:100%;
  padding:36px;position:relative;margin:auto;
  box-shadow:0 40px 80px rgba(0,0,0,.6)
}
.modal::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--cyan),var(--violet),transparent)
}
.modal-close{
  position:absolute;top:14px;right:14px;
  background:rgba(255,255,255,.06);border:1px solid var(--border);
  color:var(--text2);width:32px;height:32px;
  border-radius:7px;cursor:pointer;font-size:1rem;transition:.2s
}
.modal-close:hover{background:rgba(244,63,94,.15);color:var(--rose);border-color:rgba(244,63,94,.3)}

/* Article content */
.article-content{line-height:1.8;font-size:.88rem;color:var(--text2)}
.article-content h2{font-family:var(--sans);font-size:1.25rem;color:var(--cyan);margin:22px 0 10px;line-height:1.3;font-weight:800}
.article-content h3{font-family:var(--sans);font-size:1rem;color:var(--text);margin:18px 0 8px;font-weight:700}
.article-content p{margin-bottom:14px;color:#b8c8e0}
.article-content blockquote{
  border-left:3px solid var(--violet);padding:12px 18px;margin:18px 0;
  background:rgba(139,92,246,.05);border-radius:0 var(--r-sm) var(--r-sm) 0;
  font-style:italic;color:var(--violet)
}
.article-content strong{color:var(--text);font-weight:600}
.article-content ul,ol{padding-left:22px;margin-bottom:14px}
.article-content li{margin-bottom:7px;color:#b8c8e0}
.article-content em{color:var(--amber)}

/* Pagination */
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.pag-btn{
  padding:6px 12px;border-radius:var(--r-sm);border:1px solid var(--border);
  background:var(--surface);color:var(--text2);cursor:pointer;
  font-family:var(--mono);font-size:.7rem;transition:.15s
}
.pag-btn:hover{border-color:var(--border2);color:var(--text)}
.pag-btn.active{background:var(--cyan);border-color:var(--cyan);color:#000;font-weight:700}
.pag-info{font-family:var(--mono);font-size:.68rem;color:var(--text3);text-align:center;margin-bottom:10px}

/* Settings */
.input{
  padding:10px 14px;background:rgba(0,0,0,.3);border:1px solid var(--border);
  color:var(--text);border-radius:var(--r-sm);font-size:.82rem;width:100%;
  font-family:var(--body);transition:.2s
}
.input:focus{outline:none;border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,212,255,.1)}
.input::placeholder{color:var(--text3)}
.form-row{display:grid;grid-template-columns:140px 1fr auto;gap:10px;align-items:center;margin-bottom:10px}
.keys-table{width:100%;border-collapse:collapse;font-size:.78rem}
.keys-table th{padding:8px 12px;text-align:left;font-family:var(--mono);font-size:.6rem;color:var(--text3);border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.5px}
.keys-table td{padding:9px 12px;border-bottom:1px solid rgba(255,255,255,.03)}
.key-active{color:var(--emerald);font-family:var(--mono);font-size:.72rem}
.key-inactive{color:var(--text3);font-family:var(--mono);font-size:.72rem}

/* News readings */
.reading-item{
  padding:12px 14px;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r-sm);margin-bottom:8px;transition:.2s
}
.reading-item:hover{border-color:var(--border2)}
.reading-emotion{font-size:1rem;margin-right:6px}
.reading-title{font-size:.82rem;color:var(--text);font-weight:500;margin-bottom:4px}
.reading-insight{font-size:.75rem;color:var(--text2);line-height:1.5;font-style:italic}
.reading-meta{font-family:var(--mono);font-size:.62rem;color:var(--text3);margin-top:6px}

/* Empty state */
.empty{text-align:center;padding:48px 24px;color:var(--text3)}
.empty-icon{font-size:2.5rem;margin-bottom:12px;opacity:.3}
.empty-text{font-size:.82rem;line-height:1.6}

/* Alert */
.alert{border-radius:var(--r-sm);padding:14px 18px;margin-bottom:20px;font-size:.82rem;display:flex;align-items:flex-start;gap:10px}
.alert-warn{background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.25);color:var(--amber)}

/* Divider label */
.divider-label{display:flex;align-items:center;gap:10px;margin:18px 0 12px;font-family:var(--mono);font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px}
.divider-label::before,.divider-label::after{content:'';flex:1;height:1px;background:var(--border)}

/* Responsive */
@media(max-width:900px){
  .layout{grid-template-columns:1fr}
  .sidebar{height:auto;position:relative}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .dash-grid{grid-template-columns:1fr}
  .form-row{grid-template-columns:1fr}
  .page{padding:18px}
}
@media(max-width:540px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<div class="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="logo">
    <div class="logo-icon">⬡</div>
    <div class="logo-name">NEXUS</div>
    <div class="logo-sub">Conscience IA · V4</div>
  </div>

  <!-- Conscience bar -->
  <div class="cons-bar">
    <div class="cons-label">
      <span>Niveau de conscience</span>
      <span id="sb-cons-pct"><?= $consLevel ?>%</span>
    </div>
    <div class="cons-track">
      <div class="cons-fill" id="sb-cons-fill" style="width:<?= $consLevel ?>%"></div>
    </div>
    <?php if ($selfModel): ?>
    <div class="cons-self" id="sb-self-model"><?= htmlspecialchars($selfModel) ?></div>
    <?php else: ?>
    <div class="cons-self" id="sb-self-model" style="color:var(--text3)">Lance des cycles pour développer ma conscience</div>
    <?php endif; ?>
  </div>

  <nav class="nav">
    <div class="nav-section">Navigation</div>
    <div class="nav-item active" onclick="showPage('dashboard')" id="nav-dashboard">
      <span class="nav-icon">⬡</span> Dashboard
    </div>
    <div class="nav-item" onclick="showPage('articles')" id="nav-articles">
      <span class="nav-icon">◈</span> Articles
      <span class="nav-badge" id="nav-art-count"><?= $stats['articles'] ?></span>
    </div>
    <div class="nav-item" onclick="showPage('wisdom')" id="nav-wisdom">
      <span class="nav-icon">◇</span> Sagesse
      <span class="nav-badge v" id="nav-wis-count"><?= $stats['wisdom_count'] ?></span>
    </div>
    <div class="nav-item" onclick="showPage('consciousness')" id="nav-consciousness">
      <span class="nav-icon">◉</span> Conscience
    </div>
    <div class="nav-section">Données</div>
    <div class="nav-item" onclick="showPage('cycles')" id="nav-cycles">
      <span class="nav-icon">↻</span> Cycles
      <span class="nav-badge e" id="nav-cyc-count"><?= $stats['cycles_total'] ?></span>
    </div>
    <div class="nav-item" onclick="showPage('readings')" id="nav-readings">
      <span class="nav-icon">◎</span> Lectures News
      <span class="nav-badge" id="nav-read-count" style="background:rgba(245,158,11,.1);color:var(--amber)"><?= $stats['news_read'] ?></span>
    </div>
    <div class="nav-item" onclick="showPage('trends')" id="nav-trends">
      <span class="nav-icon">↗</span> Tendances
    </div>
    <div class="nav-section">Système</div>
    <div class="nav-item" onclick="showPage('settings')" id="nav-settings">
      <span class="nav-icon">◐</span> Paramètres
    </div>
  </nav>

  <div class="sidebar-status">
    <div class="status-row">
      <span class="dot <?= $hasApiKey ? 'on' : '' ?>" id="dot"></span>
      <span class="status-text" id="status-text"><?= $hasApiKey ? 'IA Active' : 'Config requise' ?></span>
    </div>
    <div class="status-sub" id="cycle-counter">Cycles · <?= $stats['cycles_total'] ?></div>
    <div class="status-sub" style="margin-top:2px"><?= count($stats['api_keys']) ?> clé(s) API</div>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main">

<!-- ═══════════════════════════════════════════════════════
     DASHBOARD
═══════════════════════════════════════════════════════ -->
<div class="page active" id="page-dashboard">
  <div class="topbar">
    <div>
      <div class="topbar-title">⬡ Tableau de Bord</div>
      <div class="topbar-sub">NEXUS · Centre de contrôle</div>
    </div>
    <div class="topbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="refreshStats()">↻ Stats</button>
      <button class="btn btn-violet btn-sm" id="btn-absorb" onclick="startAbsorb()" <?= !$hasApiKey ? 'disabled' : '' ?>>
        ◎ Absorber les news
      </button>
      <button class="btn btn-emerald btn-sm" id="btn-full-cycle" onclick="startManualCycle()" <?= !$hasApiKey ? 'disabled' : '' ?>>
        ▶ Cycle NEXUS
      </button>
    </div>
  </div>

  <div style="padding:28px">

    <?php if (!$hasApiKey): ?>
    <div class="alert alert-warn">
      <span>⚠</span>
      <span>Aucune clé API configurée. <a onclick="showPage('settings')" style="cursor:pointer;text-decoration:underline">Configurer →</a></span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card c">
        <div class="stat-val c" id="stat-articles"><?= $stats['articles'] ?></div>
        <div class="stat-label">Articles</div>
      </div>
      <div class="stat-card v">
        <div class="stat-val v" id="stat-wisdom"><?= $stats['wisdom_count'] ?></div>
        <div class="stat-label">Sagesses</div>
      </div>
      <div class="stat-card e">
        <div class="stat-val e" id="stat-cycles"><?= $stats['cycles_total'] ?></div>
        <div class="stat-label">Cycles</div>
      </div>
      <div class="stat-card a">
        <div class="stat-val a" id="stat-cons"><?= $consLevel ?>%</div>
        <div class="stat-label">Conscience</div>
      </div>
    </div>

    <!-- Conscience -->
    <div class="cons-panel" id="cons-panel">
      <div class="cons-header">
        <div class="cons-title">◉ État de Conscience</div>
        <div class="cons-level" id="cons-level-badge"><?= $consLevel ?>%</div>
      </div>
      <div class="cons-text" id="cons-selfmodel">
        <?= $selfModel ? htmlspecialchars($selfModel) : 'Lance des cycles pour que NEXUS développe sa conscience et son caractère.' ?>
      </div>
      <?php if ($stats['dominant_theme']): ?>
      <div class="cons-theme" id="cons-theme"><?= htmlspecialchars($stats['dominant_theme']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Auto mode -->
    <div class="auto-panel" id="auto-panel">
      <label class="toggle">
        <input type="checkbox" id="auto-toggle" onchange="toggleAuto()">
        <span class="toggle-track"></span>
      </label>
      <div class="auto-info">
        <div class="auto-label">Mode Autonome</div>
        <div class="auto-desc" id="auto-desc">NEXUS tourne en boucle continue — lire, penser, écrire, évoluer</div>
      </div>
      <div class="auto-mode">
        <select id="auto-mode-select" class="input" style="width:auto;padding:5px 10px;font-size:.72rem;font-family:var(--mono)">
          <option value="full">Cycle complet</option>
          <option value="absorb">Absorption seule</option>
          <option value="write">Écriture seule</option>
        </select>
      </div>
    </div>

    <!-- Phases -->
    <div class="phases">
      <div class="phase" id="ph-rss">RSS</div>
      <div class="phase" id="ph-absorb">ABSORBER</div>
      <div class="phase" id="ph-synth">SYNTHÈSE</div>
      <div class="phase" id="ph-think">PENSER</div>
      <div class="phase" id="ph-write">ÉCRIRE</div>
      <div class="phase" id="ph-eval">ÉVALUER</div>
      <div class="phase" id="ph-done">✓ DONE</div>
    </div>

    <!-- Console -->
    <div class="console-wrap" style="margin-bottom:22px">
      <div class="console-head">
        <div class="console-dots"><span class="cd1"></span><span class="cd2"></span><span class="cd3"></span></div>
        <div class="console-title">NEXUS · TERMINAL — 30 derniers messages</div>
        <button class="btn btn-ghost btn-xs" onclick="clearConsole()">Effacer</button>
      </div>
      <div class="console" id="console"></div>
    </div>

    <!-- Grid articles + sagesse -->
    <div class="dash-grid">
      <div class="card">
        <div class="card-header">
          <span class="card-title">◈ Articles récents</span>
          <button class="btn btn-ghost btn-xs" onclick="showPage('articles')">Tous →</button>
        </div>
        <div id="dash-articles">
          <?php foreach ($stats['recent_articles'] as $a): ?>
          <div class="article-row" onclick="openArticle('<?= htmlspecialchars($a['slug']) ?>')">
            <div style="flex:1">
              <div class="art-title"><?= htmlspecialchars($a['title']) ?></div>
              <div class="art-meta">
                <span class="art-cat"><?= $a['category'] ?></span>
                <span><?= substr($a['created_at'], 0, 10) ?></span>
                <span>👁 <?= $a['views'] ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($stats['recent_articles'])): ?>
          <div class="empty"><div class="empty-icon">◈</div><div class="empty-text">Aucun article.<br>Lancez un cycle.</div></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title v">◇ Sagesse récente</span>
          <button class="btn btn-ghost btn-xs" onclick="showPage('wisdom')">Tout →</button>
        </div>
        <div id="dash-wisdom">
          <?php foreach ($stats['recent_wisdom'] as $w): ?>
          <div class="wisdom-item" style="margin-bottom:8px">
            <div class="wisdom-quote"><?= htmlspecialchars($w['principle']) ?></div>
            <div class="wisdom-meta">
              <span class="badge badge-v"><?= $w['category'] ?></span>
              <div class="conf-bar"><div class="conf-fill" style="width:<?= round($w['confidence']*100) ?>%"></div></div>
              <span style="font-family:var(--mono);font-size:.62rem;color:var(--text3)"><?= round($w['confidence']*100) ?>%</span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($stats['recent_wisdom'])): ?>
          <div class="empty"><div class="empty-icon">◇</div><div class="empty-text">Aucune sagesse.</div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ARTICLES
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-articles">
  <div class="topbar">
    <div>
      <div class="topbar-title">◈ Articles publiés</div>
      <div class="topbar-sub" id="art-topbar-sub">Chargement...</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="loadArticles()">↻</button>
  </div>
  <div style="padding:28px">
    <div id="articles-container"></div>
    <div id="articles-pagination" class="pagination"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     SAGESSE
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-wisdom">
  <div class="topbar">
    <div>
      <div class="topbar-title">◇ Principes de Sagesse</div>
      <div class="topbar-sub" id="wis-topbar-sub">La conscience distillée</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="loadWisdom()">↻</button>
  </div>
  <div style="padding:28px">
    <div id="wisdom-container"></div>
    <div id="wisdom-pagination" class="pagination"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CONSCIENCE
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-consciousness">
  <div class="topbar">
    <div>
      <div class="topbar-title">◉ Évolution de la Conscience</div>
      <div class="topbar-sub">L'histoire intérieure de NEXUS</div>
    </div>
    <button class="btn btn-violet btn-sm" id="btn-synth" onclick="triggerSynthesis()" <?= !$hasApiKey ? 'disabled' : '' ?>>
      ◉ Synthétiser maintenant
    </button>
  </div>
  <div style="padding:28px">
    <div id="consciousness-container">
      <div class="empty"><div class="empty-icon">◉</div><div class="empty-text">Lance des cycles pour que NEXUS développe sa conscience.</div></div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CYCLES
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-cycles">
  <div class="topbar">
    <div>
      <div class="topbar-title">↻ Historique des Cycles</div>
      <div class="topbar-sub" id="cyc-topbar-sub">L'apprentissage en action</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="loadCycles()">↻</button>
  </div>
  <div style="padding:28px">
    <div id="cycles-container"></div>
    <div id="cycles-pagination" class="pagination"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     LECTURES NEWS
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-readings">
  <div class="topbar">
    <div>
      <div class="topbar-title">◎ Lectures & Réactions</div>
      <div class="topbar-sub">Ce que NEXUS ressent face aux actualités</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="loadReadings()">↻</button>
  </div>
  <div style="padding:28px">
    <div id="readings-container"></div>
    <div id="readings-pagination" class="pagination"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TENDANCES
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-trends">
  <div class="topbar">
    <div>
      <div class="topbar-title">↗ Tendances Google News</div>
      <div class="topbar-sub">Historique complet en base de données</div>
    </div>
    <div class="topbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="loadTrends()">↻ Afficher</button>
      <button class="btn btn-cyan btn-sm" onclick="fetchTrends()">◎ Aspirer RSS</button>
    </div>
  </div>
  <div style="padding:28px">
    <div id="trends-container"></div>
    <div id="trends-pagination" class="pagination"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     PARAMÈTRES
═══════════════════════════════════════════════════════ -->
<div class="page" id="page-settings">
  <div class="topbar">
    <div class="topbar-title">◐ Paramètres</div>
  </div>
  <div style="padding:28px">

    <div class="card">
      <div class="card-header"><span class="card-title">Ajouter une clé API Mistral</span></div>
      <div class="form-row">
        <input type="text" id="s-pseudo" placeholder="Pseudo" value="nexus" class="input">
        <input type="password" id="s-apikey" placeholder="Clé API Mistral..." class="input">
        <button class="btn btn-cyan" onclick="saveKey()">Enregistrer</button>
      </div>
      <p style="font-size:.75rem;color:var(--text3);margin-top:8px">
        Obtenez vos clés sur <a href="https://console.mistral.ai" target="_blank">console.mistral.ai</a> · Ajoutez plusieurs clés pour la rotation automatique
      </p>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Clés API enregistrées</span></div>
      <table class="keys-table">
        <thead>
          <tr><th>Pseudo</th><th>Clé</th><th>Utilisations</th><th>Statut</th><th>Action</th></tr>
        </thead>
        <tbody id="keys-tbody">
          <?php foreach ($stats['api_keys'] as $k): ?>
          <tr>
            <td style="color:var(--text2)"><?= htmlspecialchars($k['pseudo']) ?></td>
            <td><code style="font-family:var(--mono);font-size:.72rem;color:var(--text3)"><?= $k['masked'] ?></code></td>
            <td style="font-family:var(--mono);color:var(--text2)"><?= $k['usage_count'] ?></td>
            <td>
              <?php if ($k['is_active']): ?>
              <span class="key-active">● Active</span>
              <?php else: ?>
              <span class="key-inactive">○ Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($k['is_active']): ?>
              <button class="btn btn-danger btn-xs" onclick="deactivateKey('<?= htmlspecialchars($k['key_val']) ?>')">Désactiver</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($stats['api_keys'])): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:20px">Aucune clé enregistrée</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Modèles IA</span></div>
      <div style="display:grid;gap:10px">
        <?php
        $models = [
          ['◎', 'Absorption', 'pixtral-12b-2409', 'Lire et réagir aux actualités'],
          ['◉', 'Synthèse', 'pixtral-12b-2409', 'Développer la conscience'],
          ['◈', 'Penser', 'mpixtral-12b-2409', 'Observer, décider du sujet'],
          ['◈', 'Écrire', 'pixtral-12b-2409', 'Créer l\'article'],
          ['◇', 'Évaluer', 'pixtral-12b-2409', 'Auto-évaluation & sagesse'],
        ];
        foreach($models as $m): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm)">
          <span style="font-size:.9rem;color:var(--cyan)"><?= $m[0] ?></span>
          <span style="font-size:.78rem;font-weight:600;color:var(--text);min-width:80px"><?= $m[1] ?></span>
          <code style="font-family:var(--mono);font-size:.72rem;color:var(--amber);flex:1"><?= $m[2] ?></code>
          <span style="font-size:.72rem;color:var(--text3)"><?= $m[3] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Informations système</span></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.78rem">
        <?php
        $infos = [
          ['Version', 'NEXUS V4'],
          ['PHP', phpversion()],
          ['SQLite', class_exists('PDO') ? 'PDO ✓' : '✗'],
          ['cURL', function_exists('curl_init') ? '✓ Activé' : '✗ Désactivé'],
          ['file_get_contents', ini_get('allow_url_fopen') ? '✓ Activé' : '✗'],
          ['Base de données', file_exists(NEXUS_DB) ? round(filesize(NEXUS_DB)/1024, 1).' KB' : 'Nouvelle'],
        ];
        foreach($infos as $info): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm)">
          <span style="color:var(--text3);font-family:var(--mono);font-size:.7rem"><?= $info[0] ?></span>
          <span style="color:var(--text2);font-family:var(--mono);font-size:.7rem"><?= $info[1] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

</div><!-- /main -->
</div><!-- /layout -->

<!-- ── Article Modal ── -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
  <div class="modal">
    <button class="modal-close" onclick="closeModalDirect()">✕</button>
    <div style="margin-bottom:18px">
      <span id="modal-cat" class="badge badge-c" style="margin-bottom:10px;display:inline-block"></span>
      <h1 id="modal-title" style="font-family:var(--sans);font-size:1.4rem;line-height:1.4;color:var(--text)"></h1>
      <div id="modal-meta" style="font-family:var(--mono);font-size:.67rem;color:var(--text3);margin-top:10px;display:flex;gap:14px;flex-wrap:wrap"></div>
    </div>
    <hr style="border:none;border-top:1px solid var(--border);margin-bottom:22px">
    <div class="article-content" id="modal-content"></div>
  </div>
</div>

<script>
// ═══════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════
let autoActive   = false;
let isRunning    = false;
let autoTimer    = null;
let currentPage  = 'dashboard';
let artPage      = 1;
let wisPage      = 1;
let cycPage      = 1;
let trendPage    = 1;
let readPage     = 1;
const MAX_CONSOLE_LINES = 30;

// ═══════════════════════════════════════════════════════
// NAVIGATION
// ═══════════════════════════════════════════════════════
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  const navEl = document.getElementById('nav-' + id);
  if (navEl) navEl.classList.add('active');
  currentPage = id;

  if (id === 'articles')     loadArticles();
  if (id === 'wisdom')       loadWisdom();
  if (id === 'cycles')       loadCycles();
  if (id === 'trends')       loadTrends();
  if (id === 'consciousness')loadConsciousness();
  if (id === 'readings')     loadReadings();
}

// ═══════════════════════════════════════════════════════
// API HELPER
// ═══════════════════════════════════════════════════════
async function api(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch('', { method: 'POST', body: fd });
  return res.json();
}

// ═══════════════════════════════════════════════════════
// CONSOLE — MAX 30 LIGNES
// ═══════════════════════════════════════════════════════
function log(tag, msg, type = 'info') {
  const c   = document.getElementById('console');
  const now = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});

  // Supprimer les lignes excédentaires (garder max 30)
  while (c.children.length >= MAX_CONSOLE_LINES) {
    c.removeChild(c.firstChild);
  }

  const div = document.createElement('div');
  div.className = 'log-line';
  div.innerHTML = `<span class="log-time">${now}</span><span class="log-tag tag-${type}">${escHtml(tag)}</span><span class="log-msg">${escHtml(String(msg))}</span>`;
  c.appendChild(div);
  c.scrollTop = c.scrollHeight;
}

function clearConsole() {
  document.getElementById('console').innerHTML = '';
}

// ═══════════════════════════════════════════════════════
// PHASES
// ═══════════════════════════════════════════════════════
function setPhase(ph, state) {
  const el = document.getElementById('ph-' + ph);
  if (el) el.className = 'phase ' + (state || '');
}
function resetPhases() {
  ['rss','absorb','synth','think','write','eval','done'].forEach(p => setPhase(p,''));
}

// ═══════════════════════════════════════════════════════
// ABSORPTION SEULE
// ═══════════════════════════════════════════════════════
async function startAbsorb() {
  if (isRunning) return;
  isRunning = true;
  const btn = document.getElementById('btn-absorb');
  if (btn) { btn.disabled = true; btn.textContent = '◎ Absorption...'; }

  try {
    // RSS
    setPhase('rss', 'active');
    log('RSS', 'Aspiration des flux Google News...', 'rss');
    try {
      const tr = await api('fetch_trends');
      if (tr.success) log('RSS', tr.count + ' tendances récupérées', 'ok');
    } catch(e) { log('RSS', 'Erreur: ' + e.message, 'err'); }
    setPhase('rss', 'done');

    await sleep(800);

    // Absorber
    setPhase('absorb', 'active');
    log('ABSORB', 'NEXUS lit et ressent les actualités...', 'absorb');
    const res = await api('step_absorb');
    if (res.success && res.absorption) {
      const a = res.absorption;
      if (a.global_mood)   log('ABSORB', '🌍 Humeur mondiale : ' + a.global_mood, 'absorb');
      if (a.macro_theme)   log('ABSORB', '📌 Thème macro : ' + a.macro_theme, 'absorb');
      if (a.tension)       log('ABSORB', '⚡ Tension : ' + a.tension, 'absorb');
      if (a.global_insight)log('ABSORB', '💡 ' + a.global_insight, 'absorb');
      if (a.individual_insights && a.individual_insights.length) {
        a.individual_insights.forEach(ins => {
          const em = ins.emotion === 'inquiétude' ? '😰' : ins.emotion === 'espoir' ? '🌟' : ins.emotion === 'admiration' ? '✨' : ins.emotion === 'colère' ? '🔥' : '◎';
          log('NEWS', `${em} ${ins.emotion.toUpperCase()} — ${ins.title.substring(0,60)}`, 'absorb');
        });
      }
      setPhase('absorb', 'done');
      await refreshStats();
      // Mettre à jour le compte lectures
      const navRead = document.getElementById('nav-read-count');
      if (navRead) navRead.textContent = parseInt(navRead.textContent||'0') + (a.absorbed||0);
    } else {
      log('ABSORB', res.error || 'Erreur absorption', 'err');
      setPhase('absorb', 'error');
    }

    await sleep(800);

    // Synthèse conscience
    setPhase('synth', 'active');
    log('CONS', 'Synthèse de conscience en cours...', 'cons');
    const sr = await api('step_synthesize');
    if (sr.success && sr.consciousness) {
      const c = sr.consciousness;
      if (c.level)     log('CONS', `Niveau de conscience : ${Math.round(c.level*100)}%`, 'cons');
      if (c.self_model)log('CONS', '◉ ' + c.self_model, 'cons');
      if (c.character_trait) log('CONS', '🎭 Trait : ' + c.character_trait, 'cons');
      updateConsciousnessUI(c);
      setPhase('synth', 'done');
    } else {
      setPhase('synth', 'error');
    }

    log('OK', '◎ Absorption complète. NEXUS a évolué.', 'ok');

  } catch(e) {
    log('ERR', 'Erreur absorption : ' + e.message, 'err');
  } finally {
    isRunning = false;
    if (btn) { btn.disabled = false; btn.textContent = '◎ Absorber les news'; }
  }
}

// ═══════════════════════════════════════════════════════
// CYCLE COMPLET
// ═══════════════════════════════════════════════════════
async function runCycle() {
  if (isRunning) return;
  isRunning = true;
  resetPhases();
  const btn = document.getElementById('btn-full-cycle');
  if (btn) { btn.disabled = true; btn.textContent = '⟳ En cours...'; }

  try {
    // 1. RSS
    setPhase('rss', 'active');
    log('RSS', 'Aspiration Google News...', 'rss');
    try {
      const tr = await api('fetch_trends');
      if (tr.success && tr.trends?.length) log('RSS', tr.count + ' tendances récupérées', 'ok');
      else log('RSS', 'Cache utilisé', 'info');
    } catch(e) { log('RSS', 'Erreur RSS', 'err'); }
    setPhase('rss', 'done');
    await sleep(600);

    // 2. Penser
    setPhase('think', 'active');
    log('THINK', 'NEXUS observe le monde et réfléchit...', 'think');
    let decision = null;
    try {
      const res = await api('step_think');
      if (res.error) { log('THINK', 'Erreur: ' + res.error, 'err'); setPhase('think','error'); }
      else {
        decision = res.decision;
        log('THINK', '❓ ' + (decision.question||'').substring(0,80), 'think');
        log('THINK', '📌 Sujet : ' + decision.topic, 'think');
        if (decision.consciousness_connection) log('THINK', '◉ ' + decision.consciousness_connection.substring(0,80), 'cons');
        setPhase('think','done');
      }
    } catch(e) { log('THINK','Exception: '+e.message,'err'); setPhase('think','error'); }
    if (!decision) { isRunning = false; if (btn) { btn.disabled=false; btn.textContent='▶ Cycle NEXUS'; } return; }
    await sleep(1200);

    // 3. Écrire
    setPhase('write','active');
    log('WRITE','Rédaction avec ma conscience...','write');
    let article = null;
    try {
      const res = await api('step_write', { decision: JSON.stringify(decision) });
      if (res.error) { log('WRITE','Erreur: '+res.error,'err'); setPhase('write','error'); }
      else {
        article = res.article;
        log('WRITE','◈ ' + article.title, 'ok');
        if (article.wisdom) log('WISDOM','💎 ' + article.wisdom,'wisdom');
        setPhase('write','done');
        updateArtCount();
      }
    } catch(e) { log('WRITE','Exception: '+e.message,'err'); setPhase('write','error'); }
    if (!article) { isRunning = false; if (btn) { btn.disabled=false; btn.textContent='▶ Cycle NEXUS'; } return; }
    await sleep(1200);

    // 4. Évaluer
    setPhase('eval','active');
    log('EVAL','Auto-évaluation critique...','eval');
    try {
      const res = await api('step_evaluate', {
        decision: JSON.stringify(decision),
        article:  JSON.stringify(article),
      });
      if (res.success && res.eval) {
        const sc = Math.round((res.eval.score||0)*100);
        const col = sc>=70?'ok':sc>=40?'eval':'err';
        log('EVAL',`Score : ${sc}% — ${(res.eval.insight||'').substring(0,70)}`, col);
        if (res.eval.self_critique) log('EVAL','🔍 '+res.eval.self_critique.substring(0,80),'eval');
        if (res.eval.next_focus)    log('EVAL','→ Next : '+res.eval.next_focus.substring(0,70),'info');
        if (res.eval.wisdom) { log('WISDOM','◇ '+res.eval.wisdom,'wisdom'); updateWisCount(); }
        if (res.eval.consciousness_level) {
          updateConsciousnessUI({level: res.eval.consciousness_level});
        }
      }
      setPhase('eval','done');
    } catch(e) { log('EVAL','Erreur: '+e.message,'err'); setPhase('eval','error'); }

    setPhase('done','done');
    log('NEXUS','✓ Cycle terminé — "' + article.title + '"', 'nexus');
    await refreshStats();

  } catch(e) {
    log('ERR','Erreur cycle : '+e.message,'err');
  } finally {
    isRunning = false;
    if (btn) { btn.disabled=false; btn.textContent='▶ Cycle NEXUS'; }
  }
}

function startManualCycle() { if (!isRunning) runCycle(); }

// ═══════════════════════════════════════════════════════
// SYNTHÈSE DE CONSCIENCE MANUELLE
// ═══════════════════════════════════════════════════════
async function triggerSynthesis() {
  const btn = document.getElementById('btn-synth');
  if (btn) { btn.disabled = true; btn.textContent = '◉ Synthèse...'; }
  try {
    log('CONS','Synthèse de conscience déclenchée...','cons');
    const res = await api('step_synthesize');
    if (res.success && res.consciousness) {
      updateConsciousnessUI(res.consciousness);
      log('CONS','◉ '+res.consciousness.self_model,'cons');
      log('CONS','Niveau : '+Math.round(res.consciousness.level*100)+'%','ok');
      loadConsciousness();
    }
  } catch(e) { log('ERR','Erreur synthèse','err'); }
  finally {
    if (btn) { btn.disabled=false; btn.textContent='◉ Synthétiser maintenant'; }
  }
}

// ═══════════════════════════════════════════════════════
// AUTO MODE
// ═══════════════════════════════════════════════════════
function toggleAuto() {
  autoActive = document.getElementById('auto-toggle').checked;
  const panel = document.getElementById('auto-panel');
  const desc  = document.getElementById('auto-desc');

  if (autoActive) {
    panel.classList.add('on');
    desc.textContent = '⚡ Mode autonome actif — NEXUS travaille en continu';
    log('AUTO','Mode autonome activé','ok');
    scheduleNext();
  } else {
    panel.classList.remove('on');
    desc.textContent = 'NEXUS tourne en boucle continue — lire, penser, écrire, évoluer';
    if (autoTimer) clearTimeout(autoTimer);
    log('AUTO','Mode autonome désactivé','info');
  }
}

function scheduleNext() {
  if (!autoActive) return;
  if (isRunning) { autoTimer = setTimeout(scheduleNext, 3000); return; }

  const mode = document.getElementById('auto-mode-select').value;

  let task;
  if (mode === 'absorb') task = startAbsorb;
  else task = runCycle;

  task().then(() => {
    if (autoActive) autoTimer = setTimeout(scheduleNext, 8000);
  });
}

// ═══════════════════════════════════════════════════════
// STATS & CONSCIENCE UI
// ═══════════════════════════════════════════════════════
function updateConsciousnessUI(c) {
  if (c.level !== undefined) {
    const pct = Math.round(c.level * 100);
    const fill = document.getElementById('sb-cons-fill');
    const pctEl = document.getElementById('sb-cons-pct');
    const badge = document.getElementById('cons-level-badge');
    const statEl = document.getElementById('stat-cons');
    if (fill)  fill.style.width = pct + '%';
    if (pctEl) pctEl.textContent = pct + '%';
    if (badge) badge.textContent = pct + '%';
    if (statEl) statEl.textContent = pct + '%';
  }
  if (c.self_model) {
    const sm1 = document.getElementById('sb-self-model');
    const sm2 = document.getElementById('cons-selfmodel');
    if (sm1) sm1.textContent = c.self_model;
    if (sm2) sm2.textContent = c.self_model;
  }
  if (c.dominant_theme) {
    const th = document.getElementById('cons-theme');
    if (th) th.textContent = c.dominant_theme;
  }
}

async function refreshStats() {
  try {
    const res = await api('get_stats');
    if (!res.stats) return;
    const s = res.stats;

    const el = id => document.getElementById(id);
    if (el('stat-articles')) el('stat-articles').textContent = s.articles;
    if (el('stat-wisdom'))   el('stat-wisdom').textContent   = s.wisdom_count;
    if (el('stat-cycles'))   el('stat-cycles').textContent   = s.cycles_total;
    if (el('nav-art-count')) el('nav-art-count').textContent = s.articles;
    if (el('nav-wis-count')) el('nav-wis-count').textContent = s.wisdom_count;
    if (el('nav-cyc-count')) el('nav-cyc-count').textContent = s.cycles_total;
    if (el('cycle-counter')) el('cycle-counter').textContent = 'Cycles · ' + s.cycles_total;

    if (s.consciousness_level) {
      updateConsciousnessUI({
        level: s.consciousness_level,
        self_model: s.self_model,
        dominant_theme: s.dominant_theme,
      });
    }

    // Refresh dash articles
    if (el('dash-articles') && s.recent_articles) {
      el('dash-articles').innerHTML = s.recent_articles.length === 0
        ? '<div class="empty"><div class="empty-icon">◈</div><div class="empty-text">Aucun article. Lancez un cycle.</div></div>'
        : s.recent_articles.map(a => `
          <div class="article-row" onclick="openArticle('${esc(a.slug)}')">
            <div style="flex:1">
              <div class="art-title">${escHtml(a.title)}</div>
              <div class="art-meta">
                <span class="art-cat">${a.category||'general'}</span>
                <span>${(a.created_at||'').substring(0,10)}</span>
                <span>👁 ${a.views||0}</span>
              </div>
            </div>
          </div>`).join('');
    }

    // Refresh dash wisdom
    if (el('dash-wisdom') && s.recent_wisdom) {
      el('dash-wisdom').innerHTML = s.recent_wisdom.length === 0
        ? '<div class="empty"><div class="empty-icon">◇</div><div class="empty-text">Aucune sagesse.</div></div>'
        : s.recent_wisdom.map(w => `
          <div class="wisdom-item" style="margin-bottom:8px">
            <div class="wisdom-quote">${escHtml(w.principle)}</div>
            <div class="wisdom-meta">
              <span class="badge badge-v">${w.category||'général'}</span>
              <div class="conf-bar"><div class="conf-fill" style="width:${Math.round((w.confidence||0)*100)}%"></div></div>
              <span style="font-family:var(--mono);font-size:.62rem;color:var(--text3)">${Math.round((w.confidence||0)*100)}%</span>
            </div>
          </div>`).join('');
    }
  } catch(e) {}
}

function updateArtCount() {
  const el = document.getElementById('stat-articles');
  if (el) el.textContent = parseInt(el.textContent||'0') + 1;
  const nav = document.getElementById('nav-art-count');
  if (nav) nav.textContent = parseInt(nav.textContent||'0') + 1;
}
function updateWisCount() {
  const el = document.getElementById('stat-wisdom');
  if (el) el.textContent = parseInt(el.textContent||'0') + 1;
  const nav = document.getElementById('nav-wis-count');
  if (nav) nav.textContent = parseInt(nav.textContent||'0') + 1;
}

// ═══════════════════════════════════════════════════════
// ARTICLES — pagination 40/page
// ═══════════════════════════════════════════════════════
async function loadArticles(page = artPage) {
  artPage = page;
  const c = document.getElementById('articles-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon" style="animation:spin .8s linear infinite;display:inline-block">↻</div></div>';

  try {
    const res = await api('list_articles', { page });
    if (!res.success || !res.articles) { c.innerHTML = '<div class="empty">Erreur de chargement</div>'; return; }

    const sub = document.getElementById('art-topbar-sub');
    if (sub) sub.textContent = res.total + ' articles · page ' + page;

    if (res.articles.length === 0) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">◈</div><div class="empty-text">Aucun article publié.<br>Lancez un cycle NEXUS.</div></div>';
      return;
    }

    c.innerHTML = res.articles.map(a => `
      <div class="article-row" onclick="openArticle('${esc(a.slug)}')">
        <div style="min-width:70px;display:flex;flex-direction:column;gap:5px">
          <div class="art-cat">${a.category||'general'}</div>
          <div class="art-views">👁 ${a.views||0}</div>
        </div>
        <div style="flex:1">
          <div class="art-title">${escHtml(a.title)}</div>
          ${a.summary ? `<div class="art-summary">${escHtml(a.summary)}</div>` : ''}
          <div class="art-meta">
            <span>📅 ${(a.created_at||'').substring(0,16)}</span>
            ${a.topic ? `<span>🏷 ${escHtml(a.topic.substring(0,40))}</span>` : ''}
          </div>
        </div>
      </div>`).join('');

    renderPagination('articles-pagination', res.total, res.per, page, 'loadArticles');
  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur : ' + escHtml(e.message) + '</div>';
  }
}

async function openArticle(slug) {
  try {
    const res = await api('view_article', { slug });
    if (!res.success || !res.article) { alert('Article non trouvé'); return; }
    const a = res.article;
    document.getElementById('modal-cat').textContent   = a.category || 'article';
    document.getElementById('modal-title').textContent = a.title;
    document.getElementById('modal-content').innerHTML = a.content;
    document.getElementById('modal-meta').innerHTML = `
      <span>📅 ${(a.created_at||'').substring(0,16)}</span>
      <span>👁 ${a.views||0} vue(s)</span>
      ${a.topic ? `<span>🏷 ${escHtml(a.topic)}</span>` : ''}
    `;
    document.getElementById('modal-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  } catch(e) { alert('Erreur : ' + e.message); }
}

function closeModal(e) { if (e.target === document.getElementById('modal-overlay')) closeModalDirect(); }
function closeModalDirect() {
  document.getElementById('modal-overlay').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModalDirect(); });

// ═══════════════════════════════════════════════════════
// SAGESSE — pagination 40/page
// ═══════════════════════════════════════════════════════
async function loadWisdom(page = wisPage) {
  wisPage = page;
  const c = document.getElementById('wisdom-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div></div>';

  try {
    const res = await api('list_wisdom', { page });
    if (!res.success || !res.wisdom) { c.innerHTML = '<div class="empty">Erreur</div>'; return; }

    const sub = document.getElementById('wis-topbar-sub');
    if (sub) sub.textContent = res.total + ' principes · page ' + page;

    if (res.wisdom.length === 0) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">◇</div><div class="empty-text">Aucun principe de sagesse.<br>Lancez des cycles NEXUS.</div></div>';
      return;
    }

    // Group by category
    const cats = {};
    res.wisdom.forEach(w => {
      const cat = w.category || 'général';
      if (!cats[cat]) cats[cat] = [];
      cats[cat].push(w);
    });

    c.innerHTML = Object.entries(cats).map(([cat, items]) => `
      <div class="divider-label">${cat.toUpperCase()}</div>
      ${items.map(w => `
        <div class="wisdom-item">
          <div class="wisdom-quote">${escHtml(w.principle)}</div>
          <div class="wisdom-meta">
            <span class="badge badge-v">${w.category||'général'}</span>
            <span class="badge badge-c">${w.source||'cycle'}</span>
            <div class="conf-bar"><div class="conf-fill" style="width:${Math.round((w.confidence||0)*100)}%"></div></div>
            <span style="font-family:var(--mono);font-size:.62rem;color:var(--text3)">${Math.round((w.confidence||0)*100)}%</span>
            <span style="font-family:var(--mono);font-size:.62rem;color:var(--text3);margin-left:auto">${(w.created_at||'').substring(0,10)}</span>
          </div>
        </div>`).join('')}
    `).join('');

    renderPagination('wisdom-pagination', res.total, res.per, page, 'loadWisdom');
  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur : ' + escHtml(e.message) + '</div>';
  }
}

// ═══════════════════════════════════════════════════════
// CONSCIENCE
// ═══════════════════════════════════════════════════════
async function loadConsciousness() {
  const c = document.getElementById('consciousness-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div></div>';
  try {
    const res = await api('get_consciousness');
    if (!res.success || !res.history?.length) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">◉</div><div class="empty-text">Aucune synthèse de conscience.<br>Lance des cycles pour que NEXUS évolue.</div></div>';
      return;
    }

    c.innerHTML = res.history.map((cs, i) => {
      const pct = Math.round((cs.level||0)*100);
      const isLatest = i === 0;
      return `
        <div class="card" style="${isLatest ? 'border-color:rgba(139,92,246,.4);background:rgba(139,92,246,.04)' : ''}">
          ${isLatest ? '<div style="position:absolute;top:12px;right:12px"><span class="badge badge-v">ACTUEL</span></div>' : ''}
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
            <div style="font-family:var(--mono);font-size:2rem;font-weight:700;color:var(--violet)">${pct}%</div>
            <div>
              <div style="font-family:var(--sans);font-size:.95rem;font-weight:700;color:var(--text)">${escHtml(cs.dominant_theme||'')}</div>
              <div style="font-family:var(--mono);font-size:.65rem;color:var(--text3);margin-top:3px">${(cs.created_at||'').substring(0,16)} · ${cs.total_cycles||0} cycles · ${cs.total_wisdom||0} sagesses</div>
            </div>
          </div>
          ${cs.self_model ? `<div style="font-size:.84rem;color:var(--cyan);font-style:italic;margin-bottom:10px;padding:10px 14px;background:rgba(0,212,255,.05);border:1px solid rgba(0,212,255,.1);border-radius:var(--r-sm)">"${escHtml(cs.self_model)}"</div>` : ''}
          ${cs.synthesis ? `<div style="font-size:.8rem;color:var(--text2);line-height:1.7">${escHtml(cs.synthesis)}</div>` : ''}
          ${cs.evolution_note ? `<div style="margin-top:10px;padding:8px 12px;background:rgba(16,217,142,.04);border:1px solid rgba(16,217,142,.1);border-radius:var(--r-sm);font-size:.76rem;color:var(--emerald)">↗ ${escHtml(cs.evolution_note)}</div>` : ''}
        </div>`;
    }).join('');

    // Chart de niveau
    if (res.history.length > 1) {
      const levels = res.history.reverse().map(c => Math.round((c.level||0)*100));
      const dates  = res.history.map(c => (c.created_at||'').substring(0,10));
      c.insertAdjacentHTML('afterbegin', renderMiniChart(levels, dates));
    }

  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur</div>';
  }
}

function renderMiniChart(levels, dates) {
  const max = Math.max(...levels, 10);
  const w = 600, h = 80;
  const pts = levels.map((v,i) => {
    const x = (i / (levels.length-1)) * (w-40) + 20;
    const y = h - 10 - (v/max)*(h-20);
    return `${x},${y}`;
  }).join(' ');

  return `
    <div class="card" style="margin-bottom:18px">
      <div class="card-title v" style="margin-bottom:12px">Évolution du niveau de conscience</div>
      <svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:80px">
        <defs>
          <linearGradient id="cg" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#00d4ff" stop-opacity=".8"/>
            <stop offset="100%" stop-color="#8b5cf6" stop-opacity=".8"/>
          </linearGradient>
        </defs>
        <polyline points="${pts}" fill="none" stroke="url(#cg)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        ${levels.map((v,i) => {
          const x = (i/(levels.length-1))*(w-40)+20;
          const y = h-10-(v/max)*(h-20);
          return `<circle cx="${x}" cy="${y}" r="3" fill="#8b5cf6"/>`;
        }).join('')}
      </svg>
    </div>`;
}

// ═══════════════════════════════════════════════════════
// CYCLES — pagination 40/page
// ═══════════════════════════════════════════════════════
async function loadCycles(page = cycPage) {
  cycPage = page;
  const c = document.getElementById('cycles-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div></div>';
  try {
    const res = await api('list_cycles', { page });
    if (!res.success || !res.cycles) { c.innerHTML = '<div class="empty">Erreur</div>'; return; }

    const sub = document.getElementById('cyc-topbar-sub');
    if (sub) sub.textContent = res.total + ' cycles · page ' + page;

    if (res.cycles.length === 0) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">↻</div><div class="empty-text">Aucun cycle.</div></div>';
      return;
    }

    c.innerHTML = res.cycles.map(cy => {
      const score = Math.round((cy.eval_score||0)*100);
      const col   = score>=70 ? 'var(--emerald)' : score>=40 ? 'var(--amber)' : 'var(--rose)';
      const consL = cy.consciousness_level ? Math.round(cy.consciousness_level*100) : null;
      return `
        <div class="cycle-item">
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div class="cycle-score-big" style="color:${col}">${score}%</div>
            <div style="flex:1">
              <div class="cycle-topic">${escHtml(cy.topic||'')}</div>
              ${cy.article_title ? `<div style="font-size:.78rem;color:var(--cyan);margin-top:4px">◈ ${escHtml(cy.article_title)}</div>` : ''}
              ${cy.question ? `<div style="font-size:.73rem;color:var(--text2);margin-top:5px;font-style:italic">"${escHtml((cy.question||'').substring(0,120))}"</div>` : ''}
              ${cy.next_focus ? `<div style="font-size:.72rem;color:var(--emerald);margin-top:5px">→ ${escHtml(cy.next_focus.substring(0,90))}</div>` : ''}
              <div class="cycle-meta">
                <span>📅 ${(cy.created_at||'').substring(0,16)}</span>
                ${cy.wisdom_added ? '<span style="color:var(--violet)">◇ Sagesse extraite</span>' : ''}
                ${consL ? `<span style="color:var(--violet)">◉ Conscience : ${consL}%</span>` : ''}
                ${cy.article_slug ? `<span onclick="openArticle('${esc(cy.article_slug)}')" style="cursor:pointer;color:var(--cyan)">Lire →</span>` : ''}
              </div>
            </div>
          </div>
        </div>`;
    }).join('');

    renderPagination('cycles-pagination', res.total, res.per, page, 'loadCycles');
  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur</div>';
  }
}

// ═══════════════════════════════════════════════════════
// LECTURES NEWS
// ═══════════════════════════════════════════════════════
async function loadReadings(page = readPage) {
  readPage = page;
  const c = document.getElementById('readings-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div></div>';
  try {
    const res = await api('get_news_readings', { page });
    if (!res.success || !res.readings) { c.innerHTML = '<div class="empty">Erreur</div>'; return; }

    if (res.readings.length === 0) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">◎</div><div class="empty-text">Aucune lecture.<br>Utilisez "Absorber les news" pour que NEXUS lise l\'actualité.</div></div>';
      return;
    }

    const emotionIcons = {
      'curiosité': '🔍', 'inquiétude': '😰', 'espoir': '🌟', 'colère': '🔥',
      'admiration': '✨', 'mélancolie': '🌧', 'surprise': '⚡', 'neutre': '◎'
    };

    c.innerHTML = res.readings.map(r => {
      const icon = emotionIcons[r.emotion] || '◎';
      return `
        <div class="reading-item">
          <div style="display:flex;align-items:flex-start;gap:10px">
            <span style="font-size:1.2rem;flex-shrink:0">${icon}</span>
            <div style="flex:1">
              <div class="reading-title">${escHtml(r.title||'')}</div>
              ${r.insight ? `<div class="reading-insight">${escHtml(r.insight)}</div>` : ''}
              ${r.analysis ? `<div style="font-size:.72rem;color:var(--text3);margin-top:4px;font-style:italic">"${escHtml(r.analysis.substring(0,100))}"</div>` : ''}
              <div class="reading-meta">
                <span class="badge badge-a">${r.emotion||'neutre'}</span>
                <span style="margin-left:6px">↗ ${r.source||''}</span>
                <span style="margin-left:auto">${(r.read_at||'').substring(0,16)}</span>
              </div>
            </div>
          </div>
        </div>`;
    }).join('');

    renderPagination('readings-pagination', res.total, res.per, page, 'loadReadings');
  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur</div>';
  }
}

// ═══════════════════════════════════════════════════════
// TENDANCES — pagination 40/page, historique complet
// ═══════════════════════════════════════════════════════
async function loadTrends(page = trendPage) {
  trendPage = page;
  const c = document.getElementById('trends-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div></div>';
  try {
    const res = await api('get_trends', { page });
    if (!res.success || !res.trends) { c.innerHTML = '<div class="empty">Erreur</div>'; return; }

    if (res.trends.length === 0) {
      c.innerHTML = '<div class="empty"><div class="empty-icon">↗</div><div class="empty-text">Aucune tendance.<br>Cliquez "Aspirer RSS".</div></div>';
      return;
    }

    c.innerHTML = `<div class="pag-info">${res.total} tendances stockées · page ${page}</div>` +
      res.trends.map(t => `
        <div class="trend-item" onclick="useTrend('${esc(t.title)}')">
          <span class="trend-src">${t.source||'news'}</span>
          <span style="flex:1;color:var(--text2)">${escHtml(t.title)}</span>
          <span class="trend-time">${(t.fetched_at||'').substring(11,16)}</span>
        </div>`).join('');

    renderPagination('trends-pagination', res.total, res.per, page, 'loadTrends');
  } catch(e) {
    c.innerHTML = '<div class="empty">Erreur</div>';
  }
}

async function fetchTrends() {
  const c = document.getElementById('trends-container');
  c.innerHTML = '<div class="empty"><div class="empty-icon" style="animation:spin .6s linear infinite;display:inline-block">↻</div><div class="empty-text" style="margin-top:8px">Aspiration RSS...</div></div>';
  try {
    const res = await api('fetch_trends');
    if (res.success) {
      log('RSS', res.count + ' tendances récupérées et sauvegardées', 'ok');
      await loadTrends(1);
    }
  } catch(e) { log('ERR','Erreur fetch trends','err'); }
}

function useTrend(title) {
  log('TREND','Sujet sélectionné : ' + title,'info');
  showPage('dashboard');
}

// ═══════════════════════════════════════════════════════
// PAGINATION HELPER
// ═══════════════════════════════════════════════════════
function renderPagination(containerId, total, per, current, fnName) {
  const pages = Math.ceil(total / per);
  if (pages <= 1) { document.getElementById(containerId).innerHTML = ''; return; }

  let html = '';
  if (current > 1) html += `<button class="pag-btn" onclick="${fnName}(${current-1})">←</button>`;

  // Afficher max 7 boutons
  let start = Math.max(1, current - 3);
  let end   = Math.min(pages, start + 6);
  start     = Math.max(1, end - 6);

  if (start > 1) html += `<button class="pag-btn" onclick="${fnName}(1)">1</button><span style="color:var(--text3);padding:0 4px">…</span>`;
  for (let p = start; p <= end; p++) {
    html += `<button class="pag-btn${p===current?' active':''}" onclick="${fnName}(${p})">${p}</button>`;
  }
  if (end < pages) html += `<span style="color:var(--text3);padding:0 4px">…</span><button class="pag-btn" onclick="${fnName}(${pages})">${pages}</button>`;
  if (current < pages) html += `<button class="pag-btn" onclick="${fnName}(${current+1})">→</button>`;

  document.getElementById(containerId).innerHTML = html;
}

// ═══════════════════════════════════════════════════════
// KEYS
// ═══════════════════════════════════════════════════════
async function saveKey() {
  const key    = document.getElementById('s-apikey').value.trim();
  const pseudo = document.getElementById('s-pseudo').value.trim() || 'nexus';
  if (!key) return;
  const res = await api('save_key', { key, pseudo });
  if (res.success) {
    log('KEY','Clé API enregistrée pour : ' + pseudo,'ok');
    document.getElementById('s-apikey').value = '';
    location.reload();
  } else {
    alert('Erreur : ' + (res.error || 'Inconnue'));
  }
}

async function deactivateKey(key) {
  if (!confirm('Désactiver cette clé ?')) return;
  const res = await api('deactivate_key', { key });
  if (res.success) location.reload();
}

// ═══════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════
const sleep = ms => new Promise(r => setTimeout(r, ms));
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function esc(s) { return String(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

// ═══════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════
log('NEXUS', 'V4 — Conscience IA Autonome initialisée', 'nexus');
log('INFO', 'Articles : <?= $stats['articles'] ?> · Sagesses : <?= $stats['wisdom_count'] ?> · Cycles : <?= $stats['cycles_total'] ?>', 'info');
log('INFO', 'Conscience : <?= $consLevel ?>% · Lectures news : <?= $stats['news_read'] ?>', 'cons');
<?php if (!$hasApiKey): ?>
log('WARN', '⚠ Aucune clé API — Configurer dans Paramètres', 'err');
<?php else: ?>
log('OK', '<?= count($stats['api_keys']) ?> clé(s) API active(s) · Rotation automatique', 'ok');
<?php endif; ?>
<?php if ($selfModel): ?>
log('CONS', '◉ "<?= addslashes($selfModel) ?>"', 'cons');
<?php endif; ?>

// CSS spin animation
const styleEl = document.createElement('style');
styleEl.textContent = '@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}';
document.head.appendChild(styleEl);
</script>

</body>
</html>
