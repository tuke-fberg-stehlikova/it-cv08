<?php
// Autograder cv08: 10 kontrol × 10 bodov. Spúšťa sa v GitHub Actions (.github/workflows/classroom.yml) alebo lokálne: php tests/test.php
declare(strict_types=1);

$root = dirname(__DIR__);
$prefix = '/student/test.user/it/cv08';
$port = 8765;
$score = 0;
$results = [];

function check(string $name, bool $ok, string $hint = ''): void
{
    global $score, $results;
    $score += $ok ? 10 : 0;
    $results[] = sprintf('%s %s%s', $ok ? '✔' : '✘', $name, $ok || $hint === '' ? '' : "  → $hint");
}

/** HTTP GET na php -S; vráti [stavový kód, telo, hlavičky (kľúče malými písmenami)]. */
function get(string $path): array
{
    global $prefix, $port;
    $ctx  = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]]);
    $body = @file_get_contents("http://127.0.0.1:$port$prefix$path", false, $ctx);
    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        } elseif (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return [$status, (string) $body, $headers];
}

function isJson(array $headers): bool
{
    return str_contains(strtolower($headers['content-type'] ?? ''), 'application/json');
}

// --- statické kontroly súborov -------------------------------------------------
$migrations = glob("$root/sql/migrations/*.sql") ?: [];
$migAll = '';
foreach ($migrations as $f) {
    $migAll .= (string) file_get_contents($f);
}
check('sql/migrations/: aspoň dva súbory .sql, jeden s CREATE INDEX',
    count($migrations) >= 2 && preg_match('/CREATE\s+INDEX/i', $migAll) === 1,
    'priečinok sql/migrations/ so súbormi 001_….sql (ALTER TABLE z týždňa 8) a 002_….sql (CREATE INDEX) (úloha 9.2)');

$schema = @file_get_contents("$root/sql/schema.sql") ?: '';
check('sql/schema.sql: INDEX na stĺpec (okrem PRIMARY a FOREIGN KEY)',
    preg_match('/(?<!PRIMARY )(?<!UNIQUE )\bINDEX\s+`?\w+`?\s*\(/i', $schema) === 1 || preg_match('/CREATE\s+INDEX/i', $schema) === 1,
    'riadok INDEX idx_… (stĺpec) v CREATE TABLE main entity, rovnaký ako v migrácii 002 (úloha 9.2)');

// Model main entity = súbor v src/Model/ s beginTransaction
$mainModel = '';
foreach (glob("$root/src/Model/*.php") as $f) {
    $c = (string) file_get_contents($f);
    if (str_contains($c, 'beginTransaction')) {
        $mainModel = $c;
        break;
    }
}
check('Model main entity: beginTransaction(), commit() a rollBack()',
    $mainModel !== '' && str_contains($mainModel, 'commit(') && str_contains($mainModel, 'rollBack('),
    'insert() a update() v modeli obalené beginTransaction() / try / commit() / catch / rollBack() (úloha 9.6)');

$form = @file_get_contents("$root/views/form.twig") ?: '';
check('views/form.twig: checkbox s name="…[]"',
    preg_match('/type=["\']checkbox["\'][^>]*name=["\']\w+\[\]["\']|name=["\']\w+\[\]["\'][^>]*type=["\']checkbox["\']/i', $form) === 1,
    '<input type="checkbox" name="<lookup>[]" value="{{ c.id }}"> pre každý záznam lookup table (úloha 9.5)');

$response = @file_get_contents("$root/src/Http/Response.php") ?: '';
$request  = @file_get_contents("$root/src/Http/Request.php") ?: '';
check('src/Http/Response.php: json(); src/Http/Request.php: postArray()',
    preg_match('/function\s+json\s*\(/', $response) === 1 && preg_match('/function\s+postArray\s*\(/', $request) === 1,
    'static metóda Response::json(array $data, int $status = 200) a metóda Request::postArray(string $key) (úlohy 9.5, 9.7)');

$api = @file_get_contents("$root/src/Controller/ApiController.php") ?: '';
$ix  = @file_get_contents("$root/index.php") ?: '';
preg_match_all('#->get\(\s*[\'"](/api/[^\'"]+)[\'"]#', $ix, $mm);
$apiRoutes = $mm[1];
check('src/Controller/ApiController.php s Response::json(); index.php: aspoň 3 route /api/…',
    $api !== '' && str_contains($api, 'Response::json') && count($apiRoutes) >= 3,
    'ApiController s metódami, ktoré vracajú Response::json(); route GET /api/<entita>, /api/<entita>/{id}, /api/<lookup> (úloha 9.7)');

// --- HTTP kontroly cez php -S ----------------------------------------------------
$cmd = sprintf('php -S 127.0.0.1:%d %s > /dev/null 2>&1 & echo $!', $port, escapeshellarg("$root/tests/router.php"));
$pid = (int) trim((string) shell_exec($cmd));
usleep(800000);

// Kandidáti: main entity = route '/api/X/{id}'; lookup = route '/api/Y' bez {id}, rôzna od main entity.
// Pri viacerých kandidátoch (vlastný endpoint z 9.8, /api/<lookup>/{id} z obhajoby) sa vyberie ten, ktorý sa správa správne.
$entityCandidates = [];
$plainCandidates  = [];
foreach ($apiRoutes as $r) {
    if (preg_match('#^(/api/[a-z0-9_\-]+)/\{[a-z_]+\}$#i', $r, $m)) {
        $entityCandidates[] = $m[1];
    } elseif (preg_match('#^/api/[a-z0-9_\-]+$#i', $r)) {
        $plainCandidates[] = $r;
    }
}
if ($entityCandidates === []) {
    $entityCandidates = ['/api/recipes'];
}

// main entity: prvý kandidát, ktorého /api/X vráti JSON pole a /api/X/<id> objekt s poľom (pivot hodnoty); inak prvý kandidát
$entity = $entityCandidates[0];
$list   = null;
$one    = null;
$sList  = 0;
$hList  = [];
$sOne   = 0;
$hOne   = [];
$firstId = 1;
foreach ($entityCandidates as $cand) {
    [$s1, $b1, $h1] = get($cand);
    $l = json_decode($b1, true);
    $okL = $s1 === 200 && isJson($h1) && is_array($l) && array_is_list($l) && $l !== [] && isset($l[0]['id']);
    $id = $okL ? (int) $l[0]['id'] : 1;
    [$s2, $b2, $h2] = get("$cand/$id");
    $o = json_decode($b2, true);
    $hasArr = false;
    foreach (is_array($o) ? $o : [] as $v) {
        if (is_array($v)) {
            $hasArr = true;
        }
    }
    $okO = $s2 === 200 && isJson($h2) && is_array($o) && isset($o['id']) && (int) $o['id'] === $id && $hasArr;
    if ($list === null || ($okL && $okO)) {          // prvý kandidát ako záloha; kandidát, ktorý prešiel, ho nahradí
        $entity = $cand;
        [$list, $sList, $hList, $firstId] = [$l, $s1, $h1, $id];
        [$one, $sOne, $hOne, $oneHasArr] = [$o, $s2, $h2, $hasArr];
    }
    if ($okL && $okO) {
        break;
    }
}
$okList = $sList === 200 && isJson($hList) && is_array($list) && array_is_list($list) && $list !== [] && isset($list[0]['id']);
check("GET $entity → 200, Content-Type application/json, pole záznamov",
    $okList,
    "stavový kód $sList, Content-Type: " . ($hList['content-type'] ?? '(žiadny)') . '; telo má byť JSON pole objektov s kľúčom id (seed.sql má mať záznamy)');

check("GET $entity/$firstId → 200 JSON objekt s id a s hodnotami z pivot table (pole)",
    $sOne === 200 && isJson($hOne) && is_array($one) && isset($one['id']) && (int) $one['id'] === $firstId && ($oneHasArr ?? false),
    "stavový kód $sOne; show() má vrátiť jeden record ako objekt a v ňom kľúč s poľom hodnôt z pivot table (napr. \"categories\": [...]) (úloha 9.7)");

[$s, $b, $h] = get("$entity/999999");
$err = json_decode($b, true);
check("GET $entity/999999 → 404 a JSON s kľúčom error",
    $s === 404 && isJson($h) && is_array($err) && isset($err['error']),
    "stavový kód $s; pri neznámom id Response::json(['error' => 'Not found'], 404) (úloha 9.7)");

// lookup: ktorýkoľvek kandidát (rôzny od main entity), ktorý vráti JSON pole záznamov
$lookupOk   = false;
$lookupName = '';
$lookupWhy  = [];
foreach ($plainCandidates as $cand) {
    if ($cand === $entity) {
        continue;
    }
    [$s, $b, $h] = get($cand);
    $lk = json_decode($b, true);
    if ($s === 200 && isJson($h) && is_array($lk) && array_is_list($lk) && $lk !== []) {
        $lookupOk   = true;
        $lookupName = $cand;
        break;
    }
    $lookupWhy[] = "$cand → $s" . (isJson($h) ? '' : ' (nie JSON)');
}
check('GET /api/<lookup> → 200 JSON pole záznamov lookup table' . ($lookupName !== '' ? " ($lookupName)" : ''),
    $lookupOk,
    $plainCandidates === [] || array_diff($plainCandidates, [$entity]) === []
        ? 'v index.php chýba route GET /api/<lookup> (bez {id}) (úloha 9.7)'
        : 'žiadna route bez {id} okrem main entity nevrátila JSON pole: ' . implode(', ', $lookupWhy) . ' (metóda pre lookup table má vrátiť Response::json(getAll()))');

if ($pid) {
    exec("kill $pid 2>/dev/null");
}

echo implode("\n", $results), "\n\n", "Body: $score / 100\n";
exit($score === 100 ? 0 : 1);
