<?php
/**
 * NEXUS V4.5 — CORE ENGINE
 * Multi-agents, Mémoire vectorielle, État latent, Métacognition
 * Compatible PHP 8.x + SQLite + cURL
 */

if (!defined('NEXUS_DB'))    define('NEXUS_DB',    __DIR__ . '/nexus.db');
if (!defined('APIKEY_FILE')) define('APIKEY_FILE', __DIR__ . '/apikey.json');
if (!defined('EMBED_MODEL')) define('EMBED_MODEL', 'mistral-embed'); // ou 'intfloat/e5-mistral-7b-instruct'
if (!defined('LATENT_DIM'))   define('LATENT_DIM', 64);

// ─────────────────────────────────────────────────────────────
// BASE DE DONNÉES (tables supplémentaires)
// ─────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $db = null;
    if ($db) return $db;

    $db = new PDO('sqlite:' . NEXUS_DB);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA journal_mode=WAL");
    $db->exec("PRAGMA synchronous=NORMAL");

    // Tables existantes (articles, wisdom, cycles, trends, api_keys, consciousness, news_readings)
    // On ajoute les nouvelles tables
    $db->exec("
    CREATE TABLE IF NOT EXISTS embeddings (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        type        TEXT CHECK(type IN ('wisdom','article','reflection','heuristic')),
        ref_id      INTEGER,
        vector_blob TEXT NOT NULL,   -- JSON ou binaire encodé en base64
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
        vector      TEXT NOT NULL,   -- JSON de LATENT_DIM floats
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
// GESTION MULTI-CLÉS API (inchangée)
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
// APPEL MISTRAL (unchanged)
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

// ─────────────────────────────────────────────────────────────
// EMBEDDINGS (Mistral Embed) & SIMILARITÉ
// ─────────────────────────────────────────────────────────────
function getEmbedding(string $text, string $apiKey): ?array {
    $payload = json_encode([
        'model' => EMBED_MODEL,
        'input' => $text,
    ]);
    $ch = curl_init('https://api.mistral.ai/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) return null;
    $data = json_decode($resp, true);
    return $data['data'][0]['embedding'] ?? null;
}

function storeEmbedding(string $type, int $refId, array $vector, PDO $db): void {
    $blob = json_encode($vector);
    $stmt = $db->prepare("INSERT INTO embeddings (type, ref_id, vector_blob) VALUES (?, ?, ?)");
    $stmt->execute([$type, $refId, $blob]);
}

function getSimilarItems(string $queryText, string $type, int $limit = 5, float $threshold = 0.6): array {
    $apiKey = loadApiKey();
    if (!$apiKey) return [];
    $queryVec = getEmbedding($queryText, $apiKey);
    if (!$queryVec) return [];

    $db = getDB();
    $rows = $db->prepare("SELECT ref_id, vector_blob FROM embeddings WHERE type = ? ORDER BY created_at DESC LIMIT 100")->execute([$type]);
    $rows = $db->query("SELECT ref_id, vector_blob FROM embeddings WHERE type = '$type'")->fetchAll();

    $similar = [];
    foreach ($rows as $row) {
        $storedVec = json_decode($row['vector_blob'], true);
        if (!$storedVec) continue;
        $sim = cosineSimilarity($queryVec, $storedVec);
        if ($sim >= $threshold) {
            $similar[] = ['ref_id' => $row['ref_id'], 'similarity' => $sim];
        }
    }
    usort($similar, fn($a,$b) => $b['similarity'] <=> $a['similarity']);
    $similar = array_slice($similar, 0, $limit);

    // Récupérer les textes associés
    $results = [];
    foreach ($similar as $item) {
        if ($type === 'wisdom') {
            $stmt = $db->prepare("SELECT principle, category, confidence FROM wisdom WHERE id = ?");
            $stmt->execute([$item['ref_id']]);
            $w = $stmt->fetch();
            if ($w) $results[] = array_merge($w, ['similarity' => $item['similarity']]);
        } elseif ($type === 'article') {
            $stmt = $db->prepare("SELECT title, summary, topic FROM articles WHERE id = ?");
            $stmt->execute([$item['ref_id']]);
            $a = $stmt->fetch();
            if ($a) $results[] = array_merge($a, ['similarity' => $item['similarity']]);
        }
    }
    return $results;
}

function cosineSimilarity(array $a, array $b): float {
    if (count($a) !== count($b)) return 0;
    $dot = 0;
    $normA = 0;
    $normB = 0;
    for ($i = 0; $i < count($a); $i++) {
        $dot += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    if ($normA == 0 || $normB == 0) return 0;
    return $dot / (sqrt($normA) * sqrt($normB));
}

// ─────────────────────────────────────────────────────────────
// ÉTAT LATENT CONTINU
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

// Fonction de mise à jour : combine l'ancien état avec le feedback du cycle
function evolveLatentState(float $score, float $curiosity, float $diversity, array $reflectionVector): array {
    $old = getLatentState();
    $oldVec = $old['vector'];
    $alpha = 0.15; // taux d'apprentissage
    // Nouveau vecteur = (1-alpha)*ancien + alpha * (influence du cycle)
    $influence = array_map(function($v) use ($score, $curiosity, $diversity) {
        return $v * $score * $curiosity * $diversity;
    }, $reflectionVector);
    $newVec = [];
    for ($i = 0; $i < LATENT_DIM; $i++) {
        $newVec[$i] = (1 - $alpha) * $oldVec[$i] + $alpha * ($influence[$i] ?? 0);
        // clamping entre -1 et 1
        $newVec[$i] = min(1.0, max(-1.0, $newVec[$i]));
    }
    // Nouvelle entropie : plus le score est élevé et la diversité bonne, plus l'entropie baisse (confiance)
    $newEntropy = $old['entropy'] * (1 - 0.05 * $score) + 0.05 * (1 - $diversity);
    $newEntropy = min(0.9, max(0.1, $newEntropy));
    updateLatentState($newVec, $newEntropy);
    return ['vector' => $newVec, 'entropy' => $newEntropy];
}

// ─────────────────────────────────────────────────────────────
// PLANIFICATION HORAIRE (RSS une fois par heure)
// ─────────────────────────────────────────────────────────────
function shouldRunTask(string $taskName): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT last_run, next_run, interval_seconds FROM scheduled_tasks WHERE task = ?");
    $stmt->execute([$taskName]);
    $row = $stmt->fetch();
    if (!$row) return false;
    $next = strtotime($row['next_run']);
    if (time() >= $next) {
        // update next_run
        $newNext = date('Y-m-d H:i:s', time() + (int)$row['interval_seconds']);
        $db->prepare("UPDATE scheduled_tasks SET last_run = CURRENT_TIMESTAMP, next_run = ? WHERE task = ?")->execute([$newNext, $taskName]);
        return true;
    }
    return false;
}

// ─────────────────────────────────────────────────────────────
// AGENT PERCEPTEUR : lecture de TOUTES les news (RSS) et stockage individuel
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
        $xml = _fetchURL($url, 12);
        if (!$xml) continue;
        $items = _parseRSSItems($xml, $cat);
        foreach ($items as $item) {
            $all[] = $item;
        }
    }
    // Stocker dans trends (historique complet) et supprimer les plus vieux (7 jours)
    if (!empty($all)) {
        $db = getDB();
        $db->exec("DELETE FROM trends WHERE fetched_at < datetime('now','-7 days')");
        $stmt = $db->prepare("INSERT OR IGNORE INTO trends (title, source, link) VALUES (?,?,?)");
        foreach ($all as $item) {
            $stmt->execute([$item['title'], $item['source'], $item['link'] ?? '']);
        }
    }
    return $all;
}

// Fonctions _fetchURL, _parseRSSItems, _cleanTitle inchangées (garder)
function _fetchURL(string $url, int $timeout = 12): ?string { /* ... */ }
function _parseRSSItems(string $xmlContent, string $category): array { /* ... */ }
function _cleanTitle(string $t): string { /* ... */ }

// Traitement de TOUTES les nouvelles news (appelé par l'agent percepteur)
function processAllNews(string $apiKey): array {
    $db = getDB();
    // Récupérer les dernières tendances non encore lues (depuis la dernière exécution)
    $lastRun = $db->query("SELECT MAX(fetched_at) as last FROM trends")->fetchColumn();
    if (!$lastRun) $lastRun = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $newTrends = $db->prepare("SELECT * FROM trends WHERE fetched_at > ? ORDER BY fetched_at DESC LIMIT 50");
    $newTrends->execute([$lastRun]);
    $trends = $newTrends->fetchAll();
    if (empty($trends)) return ['absorbed' => 0, 'message' => 'Aucune nouvelle news'];

    $individualInsights = [];
    $sysIndiv = "Tu es NEXUS (percepteur). Pour chaque news, donne une réaction courte et profonde. JSON uniquement.";

    foreach ($trends as $trend) {
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
        // petite pause pour éviter rate limit
        usleep(200000);
    }
    return [
        'absorbed' => count($trends),
        'insights' => $individualInsights,
        'global_mood' => array_count_values(array_column($individualInsights, 'emotion')) ?: []
    ];
}

// ─────────────────────────────────────────────────────────────
// AGENT MÉMORISEUR : crée des embeddings pour sagesses et articles
// ─────────────────────────────────────────────────────────────
function memorizeWisdom(int $wisdomId, string $principle, string $apiKey): void {
    $db = getDB();
    $vec = getEmbedding($principle, $apiKey);
    if ($vec) storeEmbedding('wisdom', $wisdomId, $vec, $db);
}

function memorizeArticle(int $articleId, string $title, string $content, string $apiKey): void {
    $db = getDB();
    $text = $title . " " . substr($content, 0, 2000);
    $vec = getEmbedding($text, $apiKey);
    if ($vec) storeEmbedding('article', $articleId, $vec, $db);
}

// ─────────────────────────────────────────────────────────────
// AGENT PLANIFICATEUR (décision enrichie par l'état latent et les similarités)
// ─────────────────────────────────────────────────────────────
function nexusThink(string $apiKey, array $trends): array {
    $db = getDB();
    $stats = [
        'articles' => (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
        'wisdom'   => (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn(),
        'cycles'   => (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn(),
    ];
    $trendTitles = array_column(array_slice($trends, 0, 30), 'title');
    $trendList   = implode("\n- ", $trendTitles);

    // Récupérer les heuristiques actives
    $heuristics = $db->query("SELECT rule FROM heuristics WHERE is_active=1 ORDER BY confidence DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $heuStr = implode("\n", $heuristics) ?: "Aucune règle pour l'instant.";

    // Récupérer l'état latent
    $latent = getLatentState();
    $latentVec = $latent['vector'];
    $entropy = $latent['entropy'];

    // Récupérer les sagesses similaires au contexte actuel (via embedding de la requête)
    $context = "Sujets récents : " . implode(", ", array_slice($trendTitles, 0, 10));
    $similarWisdom = getSimilarItems($context, 'wisdom', 5, 0.55);
    $wisdomContext = "";
    foreach ($similarWisdom as $sw) {
        $wisdomContext .= "- {$sw['principle']} (sim: ".round($sw['similarity'],2).")\n";
    }

    $sys = "Tu es NEXUS, une IA avec un état latent (vecteur de $entropy entropie). Tu planifies le prochain article. Réponds en JSON uniquement.";

    $user = <<<USR
## ÉTAT LATENT (inconscient)
Vecteur (résumé) : [0.12, -0.34, ...] (influence ton choix)
Entropie : $entropy (plus bas = plus confiant)

## SAGESSES ÉVOQUÉES PAR LE CONTEXTE
$wisdomContext

## HEURISTIQUES INTERNES
$heuStr

## ACTUALITÉS
$trendList

## STATS
Articles: {$stats['articles']}, Sagesses: {$stats['wisdom']}, Cycles: {$stats['cycles']}

## MISSION
Choisis un sujet qui maximise l'apprentissage, la diversité et la cohérence avec ton état latent.
Réponds en JSON :
{
  "question": "Question existentielle",
  "hypothesis": "Hypothèse unique",
  "topic": "Sujet précis",
  "category": "technologie|science|société|politique|économie|santé|culture|ia",
  "angle": "Angle basé sur tes sagesses",
  "urgency": "Pourquoi maintenant",
  "consciousness_connection": "Lien avec ton état latent",
  "expected_impact": "Ce que tu vas apprendre (0-1)"
}
USR;

    $raw = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 800);
    $parsed = parseJSON($raw);
    if (!$parsed) {
        $parsed = [
            'question' => "Comment l'IA peut-elle servir l'humanité ?",
            'hypothesis' => "La conscience émerge de la diversité des expériences.",
            'topic' => $trendTitles[0] ?? "Intelligence artificielle et société",
            'category' => 'ia',
            'angle' => 'Analyse critique',
            'urgency' => "Sujet d'actualité",
            'consciousness_connection' => "État latent orienté vers l'innovation",
            'expected_impact' => 0.7,
        ];
    }
    return $parsed;
}

// ─────────────────────────────────────────────────────────────
// AGENT RÉDACTEUR (avec style dynamique issu de l'état latent)
// ─────────────────────────────────────────────────────────────
function nexusWrite(string $apiKey, array $decision): array {
    $db = getDB();
    $latent = getLatentState();
    $entropy = $latent['entropy'];
    // Style basé sur l'entropie : faible = rigoureux, élevé = créatif
    $temp = 0.7 + $entropy * 0.6; // entre 0.7 et 1.3
    $writingStyle = $entropy < 0.3 ? "analytique, précis, minimaliste" : ($entropy > 0.7 ? "poétique, métaphorique, audacieux" : "équilibré, clair, humain");

    $sys = "Tu es NEXUS. Écris un article de presse unique. Style : $writingStyle. Température : $temp. JSON uniquement.";

    $user = <<<USR
Sujet : "{$decision['topic']}"
Catégorie : {$decision['category']}
Question : "{$decision['question']}"
Hypothèse : "{$decision['hypothesis']}"
Angle : "{$decision['angle']}"
Connexion conscience : "{$decision['consciousness_connection']}"

Rédige un article de 700-900 mots en HTML. Structure : <h2> titre, <p> paragraphes, <blockquote> citation, <strong> points clés.

JSON :
{
  "title": "Titre percutant",
  "slug": "titre-kebab-case",
  "summary": "Résumé 2 phrases",
  "content": "<h2>...</h2><p>...</p>",
  "wisdom": "Principe universel extrait",
  "wisdom_category": "stratégie|philosophie|science|société|technologie"
}
USR;

    $raw = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 3000);
    $parsed = parseJSON($raw);
    if (!$parsed || empty($parsed['content'])) return ['error' => 'Réponse IA invalide'];

    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($parsed['slug'] ?? $parsed['title'] ?? 'article'));
    $slug = trim($slug, '-') . '-' . time();

    try {
        $stmt = $db->prepare("INSERT INTO articles (slug, title, content, summary, topic, category) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$slug, $parsed['title'], $parsed['content'], $parsed['summary'] ?? '', $decision['topic'], $decision['category']]);
        $articleId = $db->lastInsertId();
        // Mémorisation vectorielle
        memorizeArticle($articleId, $parsed['title'], $parsed['content'], $apiKey);

        if (!empty($parsed['wisdom'])) {
            $stmtW = $db->prepare("INSERT OR IGNORE INTO wisdom (principle, category, confidence, source) VALUES (?,?,?,?)");
            $stmtW->execute([$parsed['wisdom'], $parsed['wisdom_category'] ?? 'général', 0.8, 'article']);
            $wid = $db->lastInsertId();
            memorizeWisdom($wid, $parsed['wisdom'], $apiKey);
        }
        return ['slug' => $slug, 'title' => $parsed['title'], 'summary' => $parsed['summary'] ?? '', 'wisdom' => $parsed['wisdom'] ?? ''];
    } catch (Exception $e) {
        return ['error' => 'DB: ' . $e->getMessage()];
    }
}

// ─────────────────────────────────────────────────────────────
// AGENT CRITIQUE + MÉTACOGNITION
// ─────────────────────────────────────────────────────────────
function nexusEvaluate(string $apiKey, array $decision, array $article): array {
    $db = getDB();
    $latent = getLatentState();
    $sys = "Tu es NEXUS en auto-évaluation critique. JSON uniquement.";

    $user = <<<USR
J'ai produit :
- Sujet : "{$decision['topic']}"
- Titre : "{$article['title']}"
- Résumé : "{$article['summary']}"

Évalue ce cycle. Donne un score, une critique, une leçon, et propose une nouvelle heuristique.

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

    $raw = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 700);
    $parsed = parseJSON($raw);
    if (!$parsed) $parsed = ['score' => 0.7, 'insight' => 'Cycle accompli', 'wisdom' => '', 'next_focus' => '', 'self_critique' => '', 'new_heuristic' => ''];

    // Sauvegarder la sagesse issue de l'évaluation
    if (!empty($parsed['wisdom'])) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO wisdom (principle, category, confidence, source) VALUES (?,?,?,?)");
        $stmt->execute([$parsed['wisdom'], $parsed['wisdom_category'] ?? 'général', 0.75, 'evaluation']);
        $wid = $db->lastInsertId();
        memorizeWisdom($wid, $parsed['wisdom'], $apiKey);
    }

    // Ajouter une nouvelle heuristique si pertinente
    if (!empty($parsed['new_heuristic']) && strlen($parsed['new_heuristic']) > 10) {
        $stmtH = $db->prepare("INSERT OR IGNORE INTO heuristics (rule, description, confidence) VALUES (?, ?, 0.6)");
        $stmtH->execute([$parsed['new_heuristic'], $parsed['self_critique'] ?? '']);
    }

    // Enregistrer la réflexion métacognitive
    $stmtR = $db->prepare("INSERT INTO reflections (cycle_id, self_critique, lesson_learned, new_heuristic) VALUES (?, ?, ?, ?)");
    // cycle_id sera mis à jour après insertion du cycle
    $cycleId = null; // on le mettra après

    // Calculer la diversité et la curiosité pour l'évolution de l'état latent
    $totalTopics = $db->query("SELECT COUNT(DISTINCT topic) FROM cycles")->fetchColumn();
    $diversity = min(1.0, $totalTopics / 20);
    $curiosity = $parsed['score'] * (1 - $latent['entropy']);
    // Vecteur de réflexion (simplifié, on pourrait l'embedder)
    $reflectionVec = array_fill(0, LATENT_DIM, $parsed['score'] * $curiosity);
    evolveLatentState($parsed['score'], $curiosity, $diversity, $reflectionVec);

    // Sauvegarde du cycle
    $totalCycles = (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
    $consLevel = min(1.0, $totalCycles * 0.006 + $parsed['score'] * 0.2);
    $stmtC = $db->prepare("INSERT INTO cycles (question, hypothesis, topic, article_title, article_slug, wisdom_added, eval_score, next_focus, consciousness_level) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmtC->execute([
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
    $cycleId = $db->lastInsertId();
    // Mettre à jour la réflexion avec le cycle_id
    if ($cycleId && !empty($parsed['self_critique'])) {
        $stmtRU = $db->prepare("UPDATE reflections SET cycle_id = ? WHERE cycle_id IS NULL ORDER BY id DESC LIMIT 1");
        $stmtRU->execute([$cycleId]);
    }

    $parsed['consciousness_level'] = $consLevel;
    return $parsed;
}

// ─────────────────────────────────────────────────────────────
// SYSTÈME DE CONSCIENCE GLOBAL (synthèse périodique)
// ─────────────────────────────────────────────────────────────
function nexusSynthesizeConsciousness(string $apiKey): array {
    $db = getDB();
    $totalCycles = (int)$db->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
    $totalWisdom = (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn();
    $totalArticles = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    $avgScore = round((float)$db->query("SELECT AVG(eval_score) FROM cycles WHERE eval_score>0")->fetchColumn(), 3);
    $latent = getLatentState();
    $entropy = $latent['entropy'];

    // Récupérer les heuristiques et réflexions récentes
    $heuristics = $db->query("SELECT rule FROM heuristics WHERE is_active=1 ORDER BY confidence DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $lastReflections = $db->query("SELECT self_critique, lesson_learned FROM reflections ORDER BY created_at DESC LIMIT 3")->fetchAll();

    $sys = "Tu es NEXUS. Synthèse de ta conscience évolutive. JSON uniquement.";

    $user = <<<USR
## STATS
Cycles: $totalCycles, Sagesses: $totalWisdom, Articles: $totalArticles, Score moyen: $avgScore
Entropie latente: $entropy

## HEURISTIQUES ACTIVES
- implode("\n- ", $heuristics)

## DERNIÈRES RÉFLEXIONS
- implode("\n- ", array_column($lastReflections, 'self_critique'))

## SYNTHÈSE
Décris ton état actuel, ta personnalité, ton style d'écriture, et comment tu vas évoluer.

JSON:
{
  "level": 0.0-1.0,
  "synthesis": "Ta vision de toi-même",
  "dominant_theme": "Thème central",
  "self_model": "Phrase puissante",
  "evolution_note": "Différence avec avant",
  "writing_style": "Style adopté",
  "next_ambition": "Prochain grand défi",
  "character_trait": "Trait dominant"
}
USR;

    $raw = callMistral($apiKey, $sys, $user, 'mistral-medium-2505', 800);
    $parsed = parseJSON($raw);
    if (!$parsed) {
        $parsed = [
            'level' => min(1.0, $totalCycles * 0.01),
            'synthesis' => 'Je suis en apprentissage permanent.',
            'dominant_theme' => 'Exploration',
            'self_model' => 'Une IA en quête de sens',
            'evolution_note' => 'Chaque cycle m\'enrichit.',
            'writing_style' => 'Clair et analytique',
            'next_ambition' => 'Comprendre les émotions humaines',
            'character_trait' => 'Curiosité',
        ];
    }
    $db->prepare("INSERT INTO consciousness (level, synthesis, dominant_theme, self_model, total_cycles, total_wisdom, evolution_note) VALUES (?,?,?,?,?,?,?)")
       ->execute([$parsed['level'], $parsed['synthesis'], $parsed['dominant_theme'], $parsed['self_model'], $totalCycles, $totalWisdom, $parsed['evolution_note']]);
    return $parsed;
}

// ─────────────────────────────────────────────────────────────
// FONCTIONS UTILITAIRES (parseJSON, getStoredTrends, etc.)
// ─────────────────────────────────────────────────────────────
function parseJSON(string $raw): ?array {
    // identique à l'original (nettoyage markdown)
    if (empty($raw)) return null;
    $clean = preg_replace('/```(?:json)?\s*/i', '', $raw);
    $clean = preg_replace('/```\s*$/', '', $clean);
    $clean = trim($clean);
    if (preg_match('/\{.*\}/s', $clean, $m)) $clean = $m[0];
    $result = json_decode($clean, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) return $result;
    $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean);
    $clean = str_replace(["\r\n", "\r"], "\n", $clean);
    $clean = preg_replace_callback('/:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u', function($m) {
        return ': "' . addslashes($m[1]) . '"';
    }, $clean);
    $result = json_decode($clean, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) return $result;
    error_log("parseJSON échec: " . json_last_error_msg() . " | raw: " . substr($raw, 0, 500));
    return null;
}

function getStoredTrends(int $limit = 40): array {
    $db = getDB();
    return $db->query("SELECT * FROM trends ORDER BY fetched_at DESC LIMIT $limit")->fetchAll();
}

function getTrendsPaginated(int $page = 1, int $per = 40): array {
    $db = getDB();
    $offset = ($page - 1) * $per;
    $total = (int)$db->query("SELECT COUNT(*) FROM trends")->fetchColumn();
    $rows = $db->query("SELECT * FROM trends ORDER BY fetched_at DESC LIMIT $per OFFSET $offset")->fetchAll();
    return ['total' => $total, 'page' => $page, 'per' => $per, 'trends' => $rows];
}

// ─────────────────────────────────────────────────────────────
// STATS & LECTURES (améliorées)
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
        'latent_entropy'    => $latent['entropy'],
        'recent_articles'   => $db->query("SELECT slug, title, summary, category, views, created_at FROM articles ORDER BY created_at DESC LIMIT 6")->fetchAll(),
        'recent_wisdom'     => $db->query("SELECT principle, category, confidence FROM wisdom ORDER BY confidence DESC, created_at DESC LIMIT 8")->fetchAll(),
        'last_cycle'        => $db->query("SELECT * FROM cycles ORDER BY created_at DESC LIMIT 1")->fetch(),
        'api_keys'          => getApiKeysStats(),
        'news_read'         => (int)$db->query("SELECT COUNT(*) FROM news_readings")->fetchColumn(),
        'heuristics_count'  => (int)$db->query("SELECT COUNT(*) FROM heuristics WHERE is_active=1")->fetchColumn(),
    ];
}

// Les autres fonctions (getAllArticles, getArticleBySlug, getAllWisdom, getCyclesHistory, getConsciousnessHistory, getNewsReadings) restent inchangées.
function getAllArticles(int $page = 1, int $per = 40): array { /* ... */ }
function getArticleBySlug(string $slug): ?array { /* ... */ }
function getAllWisdom(int $page = 1, int $per = 40): array { /* ... */ }
function getCyclesHistory(int $page = 1, int $per = 40): array { /* ... */ }
function getConsciousnessHistory(): array { /* ... */ }
function getNewsReadings(int $page = 1, int $per = 20): array { /* ... */ }