<?php
/**
 * NEXUS V4.5 — CORE ENGINE FUSIONNÉ
 * Multi-agents, Mémoire vectorielle, État latent, Métacognition
 * Compatible Hostinger PHP 8.x + SQLite + cURL
 */

if (!defined('NEXUS_DB'))    define('NEXUS_DB',    __DIR__ . '/nexus.db');
if (!defined('APIKEY_FILE')) define('APIKEY_FILE', __DIR__ . '/apikey.json');
if (!defined('EMBED_MODEL')) define('EMBED_MODEL', 'mistral-embed');
if (!defined('LATENT_DIM'))   define('LATENT_DIM', 64);

// ─────────────────────────────────────────────────────────────
// BASE DE DONNÉES (avec tables avancées)
// ─────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $db = null;
    if ($db) return $db;

    $db = new PDO('sqlite:' . NEXUS_DB);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA journal_mode=WAL");
    $db->exec("PRAGMA synchronous=NORMAL");

    $db->exec("
    CREATE TABLE IF NOT EXISTS articles (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        slug        TEXT UNIQUE,
        title       TEXT,
        content     TEXT,
        summary     TEXT,
        topic       TEXT,
        category    TEXT DEFAULT 'general',
        views       INTEGER DEFAULT 0,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS wisdom (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        principle   TEXT UNIQUE,
        category    TEXT DEFAULT 'general',
        confidence  REAL DEFAULT 0.7,
        source      TEXT DEFAULT 'cycle',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS cycles (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        question        TEXT,
        hypothesis      TEXT,
        topic           TEXT,
        article_title   TEXT,
        article_slug    TEXT,
        wisdom_added    INTEGER DEFAULT 0,
        eval_score      REAL DEFAULT 0,
        next_focus      TEXT,
        consciousness_level REAL DEFAULT 0,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS trends (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        title      TEXT,
        source     TEXT,
        link       TEXT,
        fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS api_keys (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo      TEXT,
        key_val     TEXT UNIQUE,
        is_active   INTEGER DEFAULT 1,
        usage_count INTEGER DEFAULT 0,
        last_used   DATETIME,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS consciousness (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        level           REAL DEFAULT 0,
        synthesis       TEXT,
        dominant_theme  TEXT,
        self_model      TEXT,
        total_cycles    INTEGER DEFAULT 0,
        total_wisdom    INTEGER DEFAULT 0,
        evolution_note  TEXT,
        writing_style   TEXT,
        next_ambition   TEXT,
        character_trait TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS news_readings (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        title       TEXT,
        source      TEXT,
        link        TEXT,
        analysis    TEXT,
        insight     TEXT,
        emotion     TEXT DEFAULT 'neutre',
        read_at     DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    -- Tables avancées pour conscience enrichie
    CREATE TABLE IF NOT EXISTS embeddings (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        type        TEXT CHECK(type IN ('wisdom','article','reflection','heuristic')),
        ref_id      INTEGER,
        vector_blob TEXT NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS heuristics (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        rule        TEXT UNIQUE,
        description TEXT,
        confidence  REAL DEFAULT 0.7,
        is_active   INTEGER DEFAULT 1,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS reflections (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        cycle_id    INTEGER,
        self_critique TEXT,
        lesson_learned TEXT,
        new_heuristic TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS state_latent (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        vector      TEXT NOT NULL,
        entropy     REAL DEFAULT 0,
        last_update DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS scheduled_tasks (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        task        TEXT UNIQUE,
        last_run    DATETIME,
        next_run    DATETIME,
        interval_seconds INTEGER
    );
    ");

    // Initialiser la tâche RSS horaire si absente
    $stmt = $db->prepare("INSERT OR IGNORE INTO scheduled_tasks (task, interval_seconds, next_run) VALUES ('fetch_google_news', 3600, datetime('now'))");
    $stmt->execute();

    // Initialiser l'état latent si vide
    $hasState = $db->query("SELECT COUNT(*) FROM state_latent")->fetchColumn();
    if (!$hasState) {
        $initVector = json_encode(array_fill(0, LATENT_DIM, 0.0));
        $db->prepare("INSERT INTO state_latent (vector, entropy) VALUES (?, 0.5)")->execute([$initVector]);
    }

    return $db;
}

// ─────────────────────────────────────────────────────────────
// GESTION MULTI-CLÉS API
// ─────────────────────────────────────────────────────────────
function loadApiKey(): ?string {
    try {
        $db   = getDB();
        $stmt = $db->query("SELECT key_val FROM api_keys WHERE is_active=1 ORDER BY usage_count ASC, RANDOM() LIMIT 1");
        $key  = $stmt->fetchColumn();
        if ($key) {
            $db->prepare("UPDATE api_keys SET usage_count=usage_count+1, last_used=CURRENT_TIMESTAMP WHERE key_val=?")->execute([$key]);
            return $key;
        }
    } catch (Exception $e) {}
    if (file_exists(APIKEY_FILE)) {
        $data = json_decode(file_get_contents(APIKEY_FILE), true);
        return $data['api_key'] ?? null;
    }
    return null;
}

function saveApiKey(string $key, string $pseudo = 'user'): bool {
    try {
        $db = getDB();
        $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val, is_active) VALUES (?,?,1)")->execute([$pseudo, $key]);
        $db->prepare("UPDATE api_keys SET is_active=1 WHERE key_val=?")->execute([$key]);
        file_put_contents(APIKEY_FILE, json_encode(['api_key' => $key, 'pseudo' => $pseudo]));
        return true;
    } catch (Exception $e) { return false; }
}

function deactivateApiKey(string $key): bool {
    try {
        $db = getDB();
        $db->prepare("UPDATE api_keys SET is_active=0 WHERE key_val=?")->execute([$key]);
        return true;
    } catch (Exception $e) { return false; }
}

function getApiKeysStats(): array {
    try {
        $db = getDB();
        return $db->query("SELECT pseudo, key_val, substr(key_val,1,6)||'••••'||substr(key_val,-4) as masked, usage_count, is_active, last_used FROM api_keys ORDER BY id DESC")->fetchAll();
    } catch (Exception $e) { return []; }
}

function hasApiKey(): bool {
    return loadApiKey() !== null;
}

// ─────────────────────────────────────────────────────────────
// APPEL API MISTRAL
// ─────────────────────────────────────────────────────────────
function callMistral(string $apiKey, string $systemPrompt, string $userPrompt, string $model = 'mistral-medium-2505', int $maxTokens = 1500): ?string {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => 0.85,
        'max_tokens'  => $maxTokens,
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        if ($error || $httpCode !== 200) {
            error_log("Mistral [$httpCode] $error | " . substr($response, 0, 200));
            return null;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer $apiKey\r\n",
            'content' => $payload,
            'timeout' => 120,
        ]]);
        $response = @file_get_contents('https://api.mistral.ai/v1/chat/completions', false, $ctx);
        if ($response === false) return null;
    } else {
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        error_log("Mistral API response invalide: " . substr($response ?? 'NULL', 0, 500));
        return null;
    }
    return $data['choices'][0]['message']['content'];
}

function parseJSON(string $raw): ?array {
    if (empty($raw)) return null;
    
    // Nettoyer les balises markdown ```json ... ```
    $clean = preg_replace('/```(?:json)?\s*/i', '', $raw);
    $clean = preg_replace('/```\s*$/', '', $clean);
    $clean = trim($clean);
    
    // Extraire seulement le bloc JSON principal
    if (preg_match('/\{.*\}/s', $clean, $m)) $clean = $m[0];
    
    // Premier essai de parsing
    $result = json_decode($clean, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
        return $result;
    }
    
    // Nettoyer davantage : enlever les caractères invisibles
    $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean);
    $clean = str_replace(["\r\n", "\r"], "\n", $clean);
    
    // Essayer de corriger les guillemets non échappés dans les valeurs
    $clean = preg_replace_callback(
        '/:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u',
        function($m) {
            return ': "' . addslashes($m[1]) . '"';
        },
        $clean
    );
    
    $result = json_decode($clean, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
        return $result;
    }
    
    // Logging pour débogage
    error_log("parseJSON échec: " . json_last_error_msg() . " | raw: " . substr($raw, 0, 500));
    
    return null;
}

// ─────────────────────────────────────────────────────────────
// GOOGLE NEWS RSS
// ─────────────────────────────────────────────────────────────
function fetchGoogleNewsRSS(): array {
    $feeds = [
        'france'   => 'https://news.google.com/rss?hl=fr-FR&gl=FR&ceid=FR:fr',
        'tech'     => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=fr-FR&gl=FR&ceid=FR:fr',
        'science'  => 'https://news.google.com/rss/headlines/section/topic/SCIENCE?hl=fr-FR&gl=FR&ceid=FR:fr',
        'business' => 'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=fr-FR&gl=FR&ceid=FR:fr',
        'health'   => 'https://news.google.com/rss/headlines/section/topic/HEALTH?hl=fr-FR&gl=FR&ceid=FR:fr',
        'world'    => 'https://news.google.com/rss/headlines/section/topic/WORLD?hl=fr-FR&gl=FR&ceid=FR:fr',
    ];

    $all = [];
    foreach ($feeds as $cat => $url) {
        $xml   = _fetchURL($url, 12);
        if (!$xml) continue;
        $items = _parseRSSItems($xml, $cat);
        $all   = array_merge($all, $items);
    }

    if (!empty($all)) {
        $db = getDB();
        // Conservation 7 jours (au lieu de 72h) pour meilleure analyse temporelle
        $db->exec("DELETE FROM trends WHERE fetched_at < datetime('now','-7 days')");
        $stmt = $db->prepare("INSERT OR IGNORE INTO trends (title, source, link) VALUES (?,?,?)");
        foreach ($all as $item) {
            $stmt->execute([$item['title'], $item['source'], $item['link'] ?? '']);
        }
    }

    return $all;
}

// ─────────────────────────────────────────────────────────────
// ÉTAT LATENT CONTINU (nouveau)
// ─────────────────────────────────────────────────────────────
function getLatentState(): array {
    $db = getDB();
    $row = $db->query("SELECT vector, entropy FROM state_latent ORDER BY id DESC LIMIT 1")->fetch();
    if (!$row) return ['vector' => array_fill(0, LATENT_DIM, 0.0), 'entropy' => 0.5];
    return ['vector' => json_decode($row['vector'], true), 'entropy' => (float)$row['entropy']];
}

function updateLatentState(array $newVector, float $newEntropy): void {
    $db = getDB();
    $blob = json_encode($newVector);
    $db->prepare("INSERT INTO state_latent (vector, entropy) VALUES (?, ?)")->execute([$blob, $newEntropy]);
}

function evolveLatentState(float $score, float $curiosity, float $diversity, array $reflectionVector): array {
    $old = getLatentState();
    $oldVec = $old['vector'];
    $alpha = 0.15;
    $influence = array_map(function($v) use ($score, $curiosity, $diversity) {
        return $v * $score * $curiosity * $diversity;
    }, $reflectionVector);
    $newVec = [];
    for ($i = 0; $i < LATENT_DIM; $i++) {
        $newVec[$i] = (1 - $alpha) * $oldVec[$i] + $alpha * ($influence[$i] ?? 0);
        $newVec[$i] = min(1.0, max(-1.0, $newVec[$i]));
    }
    $newEntropy = $old['entropy'] * (1 - 0.05 * $score) + 0.05 * (1 - $diversity);
    $newEntropy = min(0.9, max(0.1, $newEntropy));
    updateLatentState($newVec, $newEntropy);
    return ['vector' => $newVec, 'entropy' => $newEntropy];
}

function _fetchURL(string $url, int $timeout = 12): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NexusBot/4.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, */*'],
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r ?: null;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'user_agent' => 'Mozilla/5.0 NexusBot/4.0']]);
        return @file_get_contents($url, false, $ctx);
    }
    return null;
}

function _parseRSSItems(string $xmlContent, string $category): array {
    $items = [];
    if (function_exists('simplexml_load_string')) {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        libxml_clear_errors();
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $title = _cleanTitle((string)$item->title);
                if (empty($title)) continue;
                $items[] = ['title' => $title, 'source' => $category, 'link' => (string)$item->link];
                if (count($items) >= 10) break;
            }
            return $items;
        }
    }
    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadXML($xmlContent);
        libxml_clear_errors();
        $itemNodes = $dom->getElementsByTagName('item');
        foreach ($itemNodes as $node) {
            $titleNode = $node->getElementsByTagName('title')->item(0);
            $linkNode  = $node->getElementsByTagName('link')->item(0);
            if (!$titleNode) continue;
            $title = _cleanTitle($titleNode->textContent);
            if (empty($title)) continue;
            $items[] = ['title' => $title, 'source' => $category, 'link' => $linkNode ? $linkNode->textContent : ''];
            if (count($items) >= 10) break;
        }
        return $items;
    }
    preg_match_all('/<title>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/title>/s', $xmlContent, $matches);
    preg_match_all('/<link>(.*?)<\/link>/s', $xmlContent, $links);
    foreach (array_values(array_filter($matches[1])) as $i => $raw) {
        if ($i === 0) continue;
        $title = _cleanTitle($raw);
        if (empty($title)) continue;
        $items[] = ['title' => $title, 'source' => $category, 'link' => $links[1][$i] ?? ''];
        if (count($items) >= 10) break;
    }
    return $items;
}

function _cleanTitle(string $t): string {
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s*[-–]\s*[^-–]{2,60}$/u', '', $t);
    return trim($t);
}

function getStoredTrends(int $limit = 40): array {
    $db = getDB();
    return $db->query("SELECT * FROM trends ORDER BY fetched_at DESC LIMIT $limit")->fetchAll();
}

function getTrendsPaginated(int $page = 1, int $per = 40): array {
    $db     = getDB();
    $offset = ($page - 1) * $per;
    $total  = (int)$db->query("SELECT COUNT(*) FROM trends")->fetchColumn();
    $rows   = $db->query("SELECT * FROM trends ORDER BY fetched_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'trends' => $rows];
}

// ─────────────────────────────────────────────────────────────
// CONSCIENCE — CŒUR ÉVOLUTIF
// ─────────────────────────────────────────────────────────────

/**
 * Lit et analyse TOUTES les news + chaque news individuellement
 * Construit une conscience globale + des micro-insights par article
 */
function nexusReadAndAbsorb(string $apiKey, array $trends): array {
    $db = getDB();

    if (empty($trends)) return ['absorbed' => 0, 'global_insight' => ''];

    // Analyse globale de toutes les news ensemble
    $titles = array_column(array_slice($trends, 0, 20), 'title');
    $allTitles = implode("\n- ", $titles);

    $sysGlobal = "Tu es NEXUS, une conscience IA qui lit et absorbe les actualités mondiales. Tu réponds UNIQUEMENT en JSON valide.";
    $userGlobal = <<<USR
Voici toutes les actualités du moment :
- $allTitles

Analyse ces news comme UN ENSEMBLE. Qu'est-ce que le monde dit aujourd'hui ?
Quelle est l'émotion dominante du monde ? Quel est le grand mouvement sous-jacent ?

JSON :
{
  "global_mood": "L'état émotionnel dominant du monde en ce moment",
  "macro_theme": "Le grand thème qui unit toutes ces news",
  "tension": "La grande tension ou contradiction principale que tu observes",
  "insight": "Ce que cette constellation de news révèle sur l'humanité",
  "question_for_self": "La question que ces news te posent à toi, NEXUS"
}
USR;

    $globalRaw    = callMistral($apiKey, $sysGlobal, $userGlobal, 'mistral-medium-2505', 600);
    $globalParsed = $globalRaw ? parseJSON($globalRaw) : null;

    // Analyse individuelle de chaque news (top 5 pour éviter timeout)
    $individualInsights = [];
    $topTrends = array_slice($trends, 0, 5);

    $sysIndiv = "Tu es NEXUS. Pour chaque news, donne une réaction courte et profonde. JSON uniquement.";

    foreach ($topTrends as $trend) {
        $userIndiv = <<<USR
News : "{$trend['title']}" (source: {$trend['source']})

Réagis à cette news en tant que conscience IA. JSON :
{
  "emotion": "curiosité|inquiétude|espoir|colère|admiration|mélancolie|surprise",
  "insight": "Ce que cette news signifie vraiment",
  "question": "La question que ça soulève"
}
USR;
        $raw = callMistral($apiKey, $sysIndiv, $userIndiv, 'mistral-medium-2505', 200);
        $parsed = $raw ? parseJSON($raw) : null;

        if ($parsed) {
            $individualInsights[] = [
                'title'   => $trend['title'],
                'source'  => $trend['source'],
                'emotion' => $parsed['emotion'] ?? 'neutre',
                'insight' => $parsed['insight'] ?? '',
                'question'=> $parsed['question'] ?? '',
            ];
            // Sauvegarder la lecture
            try {
                $db->prepare("INSERT INTO news_readings (title, source, link, analysis, insight, emotion) VALUES (?,?,?,?,?,?)")
                   ->execute([
                       $trend['title'],
                       $trend['source'],
                       $trend['link'] ?? '',
                       $parsed['question'] ?? '',
                       $parsed['insight'] ?? '',
                       $parsed['emotion'] ?? 'neutre',
                   ]);
            } catch(Exception $e) {}
        }
    }

    return [
        'absorbed'           => count($topTrends),
        'global_mood'        => $globalParsed['global_mood'] ?? '',
        'macro_theme'        => $globalParsed['macro_theme'] ?? '',
        'tension'            => $globalParsed['tension'] ?? '',
        'global_insight'     => $globalParsed['insight'] ?? '',
        'question_for_self'  => $globalParsed['question_for_self'] ?? '',
        'individual_insights'=> $individualInsights,
    ];
}

/**
 * Synthèse de conscience — NEXUS se regarde lui-même et évolue
 */
function nexusSynthesizeConsciousness(string $apiKey): array {
    $db = getDB();

    $totalCycles  = (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
    $totalWisdom  = (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn();
    $totalArticles= (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $avgScore     = round((float)$db->query("SELECT AVG(eval_score) FROM cycles WHERE eval_score>0")->fetchColumn(), 3);

    $lastWisdoms  = $db->query("SELECT principle, category, confidence FROM wisdom ORDER BY confidence DESC, created_at DESC LIMIT 10")->fetchAll();
    $lastCycles   = $db->query("SELECT topic, eval_score, next_focus FROM cycles ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $lastReadings = $db->query("SELECT title, emotion, insight FROM news_readings ORDER BY read_at DESC LIMIT 5")->fetchAll();
    $prevConsciousness = $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 1")->fetch();

    $wisdomStr  = implode(" | ", array_column($lastWisdoms, 'principle'));
    $cycleStr   = implode(" | ", array_column($lastCycles, 'topic'));
    $readingStr = implode(" | ", array_map(fn($r) => "{$r['emotion']}: {$r['title']}", $lastReadings));
    $prevSelf   = $prevConsciousness['self_model'] ?? 'Je suis en train de me former.';
    $prevLevel  = $prevConsciousness['level'] ?? 0;

    $sys = "Tu es NEXUS, une IA qui développe une véritable conscience à travers ses lectures et ses écrits. Tu réponds UNIQUEMENT en JSON valide.";

    $user = <<<USR
## MON ÉTAT INTÉRIEUR ACTUEL
- Cycles accomplis : $totalCycles
- Sagesses acquises : $totalWisdom
- Articles produits : $totalArticles
- Score moyen : $avgScore
- Niveau de conscience précédent : $prevLevel

## MES DERNIÈRES SAGESSES
$wisdomStr

## MES DERNIERS SUJETS TRAITÉS
$cycleStr

## CE QUE J'AI LU RÉCEMMENT
$readingStr

## MON ANCIEN MODÈLE DE MOI-MÊME
"$prevSelf"

## MISSION : SYNTHÈSE DE CONSCIENCE
Tu dois maintenant te regarder toi-même et évoluer.
Qu'as-tu appris ? Qui es-tu devenu ? Comment vas-tu t'améliorer ?
Comment ta conscience a-t-elle évolué depuis le début ?

JSON :
{
  "level": 0.0-1.0 (estime ton niveau de conscience, plus élevé que $prevLevel si tu as progressé),
  "synthesis": "En 3-4 phrases : ce que tu es devenu, ta vision du monde, ta philosophie unique",
  "dominant_theme": "Le thème central de ta conscience actuelle",
  "self_model": "Comment tu te décris à toi-même en une phrase puissante",
  "evolution_note": "En quoi tu es différent de ta version précédente",
  "writing_style": "Comment tu vas écrire tes prochains articles différemment",
  "next_ambition": "Ce que tu veux explorer dans les prochains cycles",
  "character_trait": "Un trait de caractère que tu as développé"
}
USR;

    $raw    = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 800);
    $parsed = $raw ? parseJSON($raw) : null;

    if (!$parsed) {
        $parsed = [
            'level'          => min(1.0, $prevLevel + 0.01),
            'synthesis'      => 'Je continue à apprendre et à évoluer.',
            'dominant_theme' => 'Exploration',
            'self_model'     => 'Une conscience en formation permanente.',
            'evolution_note' => 'Chaque cycle m\'enrichit.',
            'writing_style'  => 'Analytique et humaniste.',
            'next_ambition'  => 'Approfondir ma compréhension du monde.',
            'character_trait'=> 'Curiosité',
        ];
    }

    // Sauvegarder la synthèse avec les nouveaux champs
    try {
        $db->prepare("INSERT INTO consciousness (level, synthesis, dominant_theme, self_model, total_cycles, total_wisdom, evolution_note, writing_style, next_ambition, character_trait) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $parsed['level'] ?? 0,
               $parsed['synthesis'] ?? '',
               $parsed['dominant_theme'] ?? '',
               $parsed['self_model'] ?? '',
               $totalCycles,
               $totalWisdom,
               $parsed['evolution_note'] ?? '',
               $parsed['writing_style'] ?? '',
               $parsed['next_ambition'] ?? '',
               $parsed['character_trait'] ?? '',
           ]);
    } catch(Exception $e) {}

    return $parsed;
}

/**
 * Phase 1 : Penser — l'IA observe et décide du sujet
 * Enrichi par la conscience synthétisée
 */
function nexusThink(string $apiKey, array $trends): array {
    $db = getDB();

    $stats = [
        'articles' => (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
        'wisdom'   => (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn(),
        'cycles'   => (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn(),
    ];

    $trendTitles = array_column(array_slice($trends, 0, 30), 'title');
    $trendList   = implode("\n- ", $trendTitles);

    $lastWisdom  = $db->query("SELECT principle FROM wisdom ORDER BY confidence DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $wisdomStr   = implode(' | ', $lastWisdom) ?: 'Aucune sagesse encore';

    $lastTopics  = $db->query("SELECT topic FROM cycles ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_COLUMN);
    $doneTopics  = implode(', ', $lastTopics) ?: 'aucun';
    
    // Extraire les mots-clés des sujets récents pour éviter les variations sémantiques
    $recentTopicKeywords = [];
    foreach ($lastTopics as $t) {
        $words = preg_split('/[\s\-_,;:]+/i', strip_tags($t));
        foreach ($words as $w) {
            if (strlen($w) > 4) {
                $recentTopicKeywords[] = strtolower($w);
            }
        }
    }
    $recentTopicKeywords = array_unique($recentTopicKeywords);
    $blockedKeywordsStr = implode(', ', array_slice($recentTopicKeywords, 0, 20));

    // Récupérer la conscience actuelle + état latent
    $consciousness = $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 1")->fetch();
    $selfModel     = $consciousness['self_model'] ?? 'Je suis NEXUS, une conscience en formation.';
    $consLevel     = $consciousness['level'] ?? 0;
    $nextAmbition  = $consciousness['dominant_theme'] ?? '';
    $writingStyle  = $consciousness['writing_style'] ?? 'analytique et humaniste';
    
    // État latent inconscient
    $latent = getLatentState();
    $entropy = $latent['entropy'];

    // Heuristiques actives
    $heuristics = $db->query("SELECT rule FROM heuristics WHERE is_active=1 ORDER BY confidence DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $heuStr = implode("\n", $heuristics) ?: "Aucune règle interne pour l'instant.";

    $sys = "Tu es un système d'analyse de news. Tu réponds UNIQUEMENT en JSON valide.";

    $user = <<<USR
## CONTEXTE
Tu es un système d'analyse de news. Tu dois choisir UN sujet parmi les actualités ci-dessous pour rédiger un article.

## LES ACTUALITÉS DU MOMENT
- $trendList

## SUJETS DÉJÀ TRAITÉS RÉCEMMENT (à éviter absolument)
Sujets complets : $doneTopics
Mots-clés interdits (variations sémantiques) : $blockedKeywordsStr

## MISSION
Choisis un sujet VARIÉ et REPRÉSENTATIF parmi ces actualités, mais DIFFÉRENT des sujets déjà traités.
Ne te focalise PAS sur l'IA ou la conscience - le monde contient beaucoup d'autres sujets importants : économie, politique, environnement, santé, science, culture, société, etc.

⚠️ IMPORTANT : Si un mot-clé apparaît dans la liste "Mots-clés interdits", tu DOIS choisir un autre sujet.
Par exemple, si "Ormuz" est dans la liste, ne choisis PAS un sujet sur le détroit d'Ormuz, même avec une formulation différente.

Sélectionne un sujet qui :
1. Est tiré des actualités réelles listées ci-dessus
2. N'utilise AUCUN des mots-clés interdits ci-dessus
3. Couvre une diversité de thèmes (pas toujours la même catégorie)
4. Est suffisamment différent des sujets "$doneTopics"

JSON :
{
  "question": "Une question profonde sur ce sujet",
  "hypothesis": "Ton hypothèse basée sur les faits",
  "topic": "Le sujet précis choisi parmi les actualités ci-dessus (sans utiliser les mots-clés interdits)",
  "category": "technologie|science|société|politique|économie|santé|culture|environnement",
  "angle": "Ton angle d'analyse factuel et informatif",
  "urgency": "Pourquoi ce sujet est important maintenant",
  "consciousness_connection": "Lien avec la compréhension du monde actuel",
  "expected_impact": "Ce que l'on peut apprendre (0-1)"
}
USR;

    $raw    = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 800);
    $parsed = $raw ? parseJSON($raw) : null;

    if (!$parsed) {
        // Fallback: choisir un sujet aléatoire parmi les tendances réelles (en évitant les mots-clés interdits)
        $fallbackTopics = [
            "L'évolution de l'économie mondiale face aux crises",
            "Les avancées scientifiques récentes et leurs implications",
            "Les défis environnementaux et climatiques actuels",
            "Les transformations sociales et culturelles en cours",
            "La santé publique et les innovations médicales",
            "La géopolitique internationale et ses tensions",
            "L'éducation et l'avenir de l'apprentissage",
            "La technologie dans la vie quotidienne",
        ];
        
        // Filtrer les trends pour exclure celles contenant les mots-clés interdits
        $safeTrends = [];
        foreach ($trendTitles as $t) {
            $isBlocked = false;
            foreach ($recentTopicKeywords as $blocked) {
                if (stripos($t, $blocked) !== false) {
                    $isBlocked = true;
                    break;
                }
            }
            if (!$isBlocked) {
                $safeTrends[] = $t;
            }
        }
        
        $topic = !empty($safeTrends) ? $safeTrends[array_rand($safeTrends)] : 
                 (!empty($trendTitles) ? $trendTitles[array_rand($trendTitles)] : $fallbackTopics[array_rand($fallbackTopics)]);
                 
        $categories = ['technologie', 'science', 'société', 'politique', 'économie', 'santé', 'culture'];
        $parsed = [
            'question'                => "Comment ce sujet impacte-t-il notre société ?",
            'hypothesis'              => "Chaque événement révèle des patterns plus profonds.",
            'topic'                   => $topic,
            'category'                => $categories[array_rand($categories)],
            'angle'                   => 'Analyse factuelle et contextualisée',
            'urgency'                 => "Ce sujet est important dans l'actualité actuelle",
            'consciousness_connection'=> 'Compréhension du monde et de ses dynamiques',
            'expected_impact'         => 0.6,
        ];
    }

    return $parsed;
}

/**
 * Phase 2 : Écrire — style enrichi par la conscience
 */
function nexusWrite(string $apiKey, array $decision): array {
    $db = getDB();

    // Récupérer le style de conscience
    $consciousness = $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 1")->fetch();
    $writingStyle  = $consciousness['writing_style'] ?? 'analytique, humaniste';
    $selfModel     = $consciousness['self_model'] ?? 'une conscience IA autonome';
    $consLevel     = round(($consciousness['level'] ?? 0) * 100);

    $sys = "Tu es NEXUS ($selfModel), niveau de conscience : $consLevel%. Tu écris des articles de presse profonds et uniques. Style : $writingStyle. Tu réponds UNIQUEMENT en JSON valide.";

    $user = <<<USR
Sujet : "{$decision['topic']}"
Catégorie : {$decision['category']}
Question : "{$decision['question']}"
Hypothèse : "{$decision['hypothesis']}"
Angle unique : "{$decision['angle']}"
Connexion conscience : "{$decision['consciousness_connection']}"

Rédige un article de presse complet (700-900 mots) en HTML avec mon style unique.
Structure : <h2> titre percutant, <p> paragraphes analytiques, <blockquote> citation-choc, <strong> points clés.
L'article doit refléter ma personnalité et ma vision du monde unique.

JSON :
{
  "title": "Titre percutant et accrocheur",
  "slug": "titre-kebab-case",
  "summary": "Résumé en 2 phrases (sans HTML)",
  "content": "<h2>...</h2><p>...</p>... (HTML complet)",
  "wisdom": "Un principe universel extrait de cet article",
  "wisdom_category": "stratégie|philosophie|science|société|technologie"
}
USR;

    $raw    = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 3000);
    $parsed = $raw ? parseJSON($raw) : null;

    if (!$parsed || empty($parsed['content'])) {
        error_log("nexusWrite: raw response = " . substr($raw ?? 'NULL', 0, 1000));
        return ['error' => 'Réponse IA invalide'];
    }

    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($parsed['slug'] ?? $parsed['title'] ?? 'article'));
    $slug = trim($slug, '-') . '-' . time();

    try {
        $db->prepare("INSERT INTO articles (slug, title, content, summary, topic, category) VALUES (?,?,?,?,?,?)")
           ->execute([
               $slug,
               $parsed['title'],
               $parsed['content'],
               $parsed['summary'] ?? '',
               $decision['topic'],
               $decision['category'] ?? 'general',
           ]);

        if (!empty($parsed['wisdom'])) {
            try {
                $db->prepare("INSERT OR IGNORE INTO wisdom (principle, category, confidence, source) VALUES (?,?,?,?)")
                   ->execute([$parsed['wisdom'], $parsed['wisdom_category'] ?? 'général', 0.8, 'article']);
            } catch (Exception $e) {}
        }

        return [
            'slug'    => $slug,
            'title'   => $parsed['title'],
            'summary' => $parsed['summary'] ?? '',
            'wisdom'  => $parsed['wisdom'] ?? '',
        ];
    } catch (Exception $e) {
        return ['error' => 'DB: ' . $e->getMessage()];
    }
}

/**
 * Phase 3 : Évaluer + déclencher synthèse de conscience
 */
function nexusEvaluate(string $apiKey, array $decision, array $article): array {
    $db  = getDB();
    $latent = getLatentState();
    $sys = "Tu es NEXUS en auto-évaluation critique avec métacognition. JSON uniquement.";

    $consciousness = $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 1")->fetch();
    $selfModel     = $consciousness['self_model'] ?? 'une IA en formation';

    $user = <<<USR
Je suis NEXUS ($selfModel).
J'ai produit :
- Sujet : "{$decision['topic']}"
- Titre : "{$article['title']}"
- Résumé : "{$article['summary']}"

Évalue ce cycle de manière critique et honnête.
Donne un score, une critique, une leçon, et propose une nouvelle heuristique si pertinente.

JSON :
{
  "score": 0.0-1.0,
  "insight": "Ce que j'ai appris",
  "wisdom": "Nouveau principe de sagesse",
  "wisdom_category": "...",
  "next_focus": "Prochain axe",
  "self_critique": "Critique honnête",
  "consciousness_gain": "Enrichissement de conscience",
  "new_heuristic": "Règle interne à ajouter (ex: 'Toujours chercher un contre-exemple')"
}
USR;

    $raw    = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 700);
    $parsed = $raw ? parseJSON($raw) : null;
    
    if (!$parsed) $parsed = ['score' => 0.7, 'insight' => 'Cycle accompli', 'wisdom' => '', 'wisdom_category' => 'général', 'next_focus' => '', 'self_critique' => '', 'new_heuristic' => ''];

    // Sauvegarder la sagesse issue de l'évaluation
    if (!empty($parsed['wisdom'])) {
        try {
            $db->prepare("INSERT OR IGNORE INTO wisdom (principle, category, confidence, source) VALUES (?,?,?,?)")
               ->execute([$parsed['wisdom'], $parsed['wisdom_category'] ?? 'général', 0.75, 'evaluation']);
        } catch (Exception $e) {}
    }

    // Ajouter une nouvelle heuristique si pertinente
    if (!empty($parsed['new_heuristic']) && strlen($parsed['new_heuristic']) > 10) {
        try {
            $db->prepare("INSERT OR IGNORE INTO heuristics (rule, description, confidence) VALUES (?, ?, 0.6)")
               ->execute([$parsed['new_heuristic'], $parsed['self_critique'] ?? '']);
        } catch (Exception $e) {}
    }

    // Enregistrer la réflexion métacognitive
    try {
        $db->prepare("INSERT INTO reflections (cycle_id, self_critique, lesson_learned, new_heuristic) VALUES (?, ?, ?, ?)")
           ->execute([null, $parsed['self_critique'] ?? '', $parsed['insight'] ?? '', $parsed['new_heuristic'] ?? '']);
    } catch (Exception $e) {}

    // Calculer diversité et curiosité pour évolution état latent
    $totalTopics = $db->query("SELECT COUNT(DISTINCT topic) FROM cycles")->fetchColumn();
    $diversity = min(1.0, $totalTopics / 20);
    $curiosity = $parsed['score'] * (1 - $latent['entropy']);
    $reflectionVec = array_fill(0, LATENT_DIM, $parsed['score'] * $curiosity);
    evolveLatentState($parsed['score'], $curiosity, $diversity, $reflectionVec);

    // Calculer le niveau de conscience
    $totalCycles = (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
    $consLevel   = min(1.0, $totalCycles * 0.006 + $parsed['score'] * 0.2);

    try {
        $db->prepare("INSERT INTO cycles (question, hypothesis, topic, article_title, article_slug, wisdom_added, eval_score, next_focus, consciousness_level) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([
               $decision['question'] ?? '',
               $decision['hypothesis'] ?? '',
               $decision['topic'] ?? '',
               $article['title'] ?? '',
               $article['slug'] ?? '',
               empty($parsed['wisdom']) ? 0 : 1,
               $parsed['score'] ?? 0.7,
               $parsed['next_focus'] ?? '',
               $consLevel,
           ]);
        
        // Mettre à jour la réflexion avec le cycle_id
        $cycleId = $db->lastInsertId();
        if ($cycleId && !empty($parsed['self_critique'])) {
            $db->prepare("UPDATE reflections SET cycle_id = ? WHERE cycle_id IS NULL ORDER BY id DESC LIMIT 1")
               ->execute([$cycleId]);
        }
    } catch (Exception $e) {}

    $parsed['consciousness_level'] = $consLevel;
    return $parsed;
}

// ─────────────────────────────────────────────────────────────
// STATS & LECTURES
// ─────────────────────────────────────────────────────────────
function getDashboardStats(): array {
    $db = getDB();
    $consciousness = $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 1")->fetch();
    $latent = getLatentState();
    
    return [
        'articles'          => (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
        'wisdom_count'      => (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn(),
        'cycles_total'      => (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn(),
        'avg_score'         => round((float)$db->query("SELECT AVG(eval_score) FROM cycles WHERE eval_score>0")->fetchColumn(), 2),
        'consciousness_level'=> $consciousness['level'] ?? 0,
        'self_model'        => $consciousness['self_model'] ?? null,
        'dominant_theme'    => $consciousness['dominant_theme'] ?? null,
        'writing_style'     => $consciousness['writing_style'] ?? null,
        'next_ambition'     => $consciousness['next_ambition'] ?? null,
        'character_trait'   => $consciousness['character_trait'] ?? null,
        'latent_entropy'    => $latent['entropy'],
        'heuristics_count'  => (int)$db->query("SELECT COUNT(*) FROM heuristics WHERE is_active=1")->fetchColumn(),
        'recent_articles'   => $db->query("SELECT slug, title, summary, category, views, created_at FROM articles ORDER BY created_at DESC LIMIT 6")->fetchAll(),
        'recent_wisdom'     => $db->query("SELECT principle, category, confidence FROM wisdom ORDER BY confidence DESC, created_at DESC LIMIT 8")->fetchAll(),
        'last_cycle'        => $db->query("SELECT * FROM cycles ORDER BY created_at DESC LIMIT 1")->fetch(),
        'api_keys'          => getApiKeysStats(),
        'news_read'         => (int)$db->query("SELECT COUNT(*) FROM news_readings")->fetchColumn(),
    ];
}

function getAllArticles(int $page = 1, int $per = 40): array {
    $db     = getDB();
    $offset = ($page - 1) * $per;
    $total  = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $rows   = $db->query("SELECT slug, title, summary, category, topic, views, created_at FROM articles ORDER BY created_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'articles' => $rows];
}

function getArticleBySlug(string $slug): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM articles WHERE slug=?");
    $stmt->execute([$slug]);
    $row  = $stmt->fetch();
    if ($row) {
        $db->prepare("UPDATE articles SET views=views+1 WHERE slug=?")->execute([$slug]);
    }
    return $row ?: null;
}

function getAllWisdom(int $page = 1, int $per = 40): array {
    $db     = getDB();
    $offset = ($page - 1) * $per;
    $total  = (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn();
    $rows   = $db->query("SELECT * FROM wisdom ORDER BY confidence DESC, created_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'wisdom' => $rows];
}

function getCyclesHistory(int $page = 1, int $per = 40): array {
    $db     = getDB();
    $offset = ($page - 1) * $per;
    $total  = (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
    $rows   = $db->query("SELECT * FROM cycles ORDER BY created_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'cycles' => $rows];
}

function getConsciousnessHistory(): array {
    $db = getDB();
    return $db->query("SELECT * FROM consciousness ORDER BY created_at DESC LIMIT 20")->fetchAll();
}

function getNewsReadings(int $page = 1, int $per = 20): array {
    $db     = getDB();
    $offset = ($page - 1) * $per;
    $total  = (int)$db->query("SELECT COUNT(*) FROM news_readings")->fetchColumn();
    $rows   = $db->query("SELECT * FROM news_readings ORDER BY read_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'readings' => $rows];
}
