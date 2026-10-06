<?php
declare(strict_types=1);
session_start();

const SUE_COLS = ['title','subtitle','year','rating','status','image_url','body'];
const SYNONYMS = [
    'title'     => ['title','name','book','movie','show','track','item'],
    'subtitle'  => ['subtitle','author','director','artist','creator','by','writer'],
    'year'      => ['year','released','release year','year published','date'],
    'rating'    => ['rating','my rating','stars','score'],
    'status'    => ['status','shelf','exclusive shelf','state','watched','read'],
    'image_url' => ['image_url','image','cover','poster','thumbnail','url','img'],
    'body'      => ['body','notes','review','my review','comment','comments','description'],
];

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function read_csv(string $path): array {
    $rows = [];
    if (($fh = fopen($path, 'r')) === false) return $rows;
    while (($r = fgetcsv($fh)) !== false) $rows[] = $r;
    fclose($fh);
    return $rows;
}

// Minimal XLSX reader — first sheet only, no formulas. Zip-bomb safe.
function read_xlsx(string $path): array {
    $rows = [];
    if (!class_exists('ZipArchive')) return $rows;
    $MAX = 50 * 1024 * 1024;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;
    $safe = function(ZipArchive $z, string $n) use ($MAX) {
        $st = $z->statName($n); if ($st === false) return false;
        if (($st['size'] ?? 0) > $MAX) return false;
        return $z->getFromName($n);
    };
    $shared = [];
    $ss = $safe($zip, 'xl/sharedStrings.xml');
    if ($ss !== false) {
        $sx = @simplexml_load_string($ss);
        if ($sx) foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }
    // Find first sheet from workbook.xml.rels
    $sheetXml = $safe($zip, 'xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return $rows;
    $sx = @simplexml_load_string($sheetXml);
    if (!$sx) return $rows;
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        $col = 0;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $idx = $ref ? col_letters_to_index(preg_replace('/\d+/', '', $ref)) : $col;
            while ($col < $idx) { $line[] = ''; $col++; }
            $type = (string)$c['t'];
            $v = isset($c->v) ? (string)$c->v : '';
            if ($type === 's' && is_numeric($v) && isset($shared[(int)$v])) $v = $shared[(int)$v];
            elseif ($type === 'inlineStr' && isset($c->is->t)) $v = (string)$c->is->t;
            $line[] = $v;
            $col++;
        }
        $rows[] = $line;
    }
    return $rows;
}

function col_letters_to_index(string $letters): int {
    $letters = strtoupper($letters);
    $n = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

function guess_header(string $target, array $headers): int {
    $syns = SYNONYMS[$target] ?? [];
    foreach ($headers as $i => $h) {
        $clean = strtolower(trim(preg_replace('/[\s_-]+/', ' ', $h)));
        foreach ($syns as $s) if ($clean === $s || str_contains($clean, $s)) return $i;
    }
    return -1;
}

function clean_cell(string $col, $v): string {
    $s = trim((string)$v);
    if ($col === 'year') return preg_match('/\d{4}/', $s, $m) ? $m[0] : '';
    if ($col === 'rating') return preg_match('/-?\d+(\.\d+)?/', $s, $m) ? $m[0] : '';
    if ($col === 'image_url') return preg_match('#^https?://#i', $s) ? $s : '';
    return $s;
}

function csv_escape(string $v): string {
    if (preg_match('/[",\r\n]/', $v)) return '"' . str_replace('"', '""', $v) . '"';
    return $v;
}

// ----- Routing -----
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Pick a file first.';
    } elseif (($_FILES['file']['size'] ?? 0) > 8 * 1024 * 1024) {
        $message = 'File too big. Keep it under 8 MB.';
    } else {
        $name = strtolower($_FILES['file']['name']);
        $tmp = $_FILES['file']['tmp_name'];
        $rows = str_ends_with($name, '.xlsx') ? read_xlsx($tmp) : read_csv($tmp);
        if (count($rows) < 2) {
            $message = 'Need at least a header row and one data row.';
        } else {
            $headers = array_map('strval', array_shift($rows));
            $_SESSION['conv'] = ['headers' => $headers, 'rows' => array_slice($rows, 0, 5000)];
            header('Location: ?step=map'); exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'download') {
    $stash = $_SESSION['conv'] ?? null;
    if (!$stash) { header('Location: ?'); exit; }
    $headers = $stash['headers']; $rows = $stash['rows'];
    $map = [];
    foreach (SUE_COLS as $c) {
        $v = $_POST['map'][$c] ?? '';
        $map[$c] = ($v === '') ? null : (int)$v;
    }
    if ($map['title'] === null) {
        $message = 'Pick which column is the title — it\'s required.';
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sue-import.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, SUE_COLS);
        foreach ($rows as $r) {
            $line = [];
            foreach (SUE_COLS as $c) {
                $line[] = $map[$c] === null ? '' : clean_cell($c, $r[$map[$c]] ?? '');
            }
            if ($line[0] === '') continue;
            fputcsv($out, $line);
        }
        fclose($out);
        unset($_SESSION['conv']);
        exit;
    }
}

$step = $_GET['step'] ?? 'upload';
if ($step === 'map' && empty($_SESSION['conv'])) $step = 'upload';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sue CSV Converter (PHP)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;700&family=Patrick+Hand&display=swap" rel="stylesheet">
<style>
  :root { --paper:#f5efe1; --ink:#2a2a2a; --ink-soft:#555048; --rule:#c8b89a; --accent:#7a4e9b; }
  *{box-sizing:border-box}
  body{margin:0;padding:32px 16px;background:#ece4cf;font-family:'Patrick Hand',sans-serif;color:var(--ink);font-size:18px;min-height:100vh}
  .sheet{max-width:820px;margin:0 auto;background:var(--paper);
    background-image:radial-gradient(1200px 600px at 20% 0%,#fff7e0,var(--paper)),
      repeating-linear-gradient(transparent 0 31px,rgba(140,110,60,.18) 31px 32px);
    border:1px solid #d8c89a;border-radius:6px;box-shadow:0 12px 40px rgba(0,0,0,.18);
    padding:36px 44px 48px;position:relative}
  .sheet::before{content:"";position:absolute;left:70px;top:0;bottom:0;width:2px;background:var(--accent);opacity:.55}
  h1{font-family:'Caveat',cursive;font-size:56px;margin:0 0 8px;transform:rotate(-1.2deg)}
  .tagline{font-family:'Caveat',cursive;color:var(--accent);font-size:24px;margin:0 0 24px}
  .card{background:#fffaf0;border:1px solid #d8c89a;padding:18px 22px;border-radius:4px;margin:18px 0}
  label{display:block;font-family:'Caveat',cursive;font-size:22px;color:var(--ink-soft);margin:0 0 4px}
  input[type=file],select{width:100%;background:transparent;border:0;border-bottom:2px solid var(--ink);
    padding:6px 4px;font-family:'Patrick Hand',sans-serif;font-size:18px;color:var(--ink);outline:none}
  select:focus{border-bottom-color:var(--accent)}
  table.map{width:100%;border-collapse:collapse;margin-top:8px}
  table.map td{padding:6px 8px;vertical-align:middle;border-bottom:1px dashed rgba(0,0,0,.12)}
  table.map td.k{width:32%;font-family:'Caveat',cursive;font-size:24px;color:var(--accent)}
  table.map td.k small{display:block;color:var(--ink-soft);font-family:'Patrick Hand',sans-serif;font-size:14px}
  button{background:var(--ink);color:#fff7e0;border:0;padding:10px 22px;border-radius:999px;
    font-family:'Caveat',cursive;font-size:24px;cursor:pointer;transform:rotate(-1deg);
    box-shadow:2px 3px 0 rgba(0,0,0,.15);margin-top:16px}
  button:hover{background:var(--accent);color:#fff7e0}
  .preview{margin-top:14px;max-height:180px;overflow:auto;border:1px solid #d8c89a;background:#fffaf0;font-size:14px;font-family:ui-monospace,monospace}
  .preview table{width:100%;border-collapse:collapse}
  .preview th,.preview td{padding:4px 8px;border-bottom:1px solid #eadfc6;text-align:left;white-space:nowrap}
  .preview th{background:#f0e5cc;font-family:'Caveat',cursive;font-size:16px;color:var(--accent);position:sticky;top:0}
  .muted{color:var(--ink-soft);font-size:15px}
  .err{color:var(--accent);font-family:'Caveat',cursive;font-size:22px}
  .pill{display:inline-block;padding:2px 10px;border-radius:999px;background:#fff5a8;font-family:'Caveat',cursive;font-size:18px;transform:rotate(-1deg);margin-right:6px}
  footer{text-align:center;margin-top:24px;color:var(--ink-soft);font-size:14px}
  footer a{color:var(--accent)}
</style>
</head>
<body>
<div class="sheet">
  <h1>Sue CSV Converter</h1>
  <p class="tagline">drop any Excel or CSV → get a clean Sue-ready file (server-side PHP)</p>

  <?php if ($message): ?><p class="err"><?= h($message) ?></p><?php endif; ?>

  <?php if ($step === 'upload'): ?>
    <form class="card" method="post" enctype="multipart/form-data" action="?action=upload">
      <input type="hidden" name="action" value="upload">
      <label>1. Pick your spreadsheet</label>
      <input type="file" name="file" accept=".xlsx,.csv" required>
      <p class="muted">Supported: .xlsx, .csv. Max 8 MB. The file is read on the server and discarded.</p>
      <button type="submit">Read file</button>
    </form>
  <?php else:
      $stash = $_SESSION['conv'];
      $headers = $stash['headers'];
      $rows = $stash['rows'];
  ?>
    <form class="card" method="post" action="?action=download">
      <input type="hidden" name="action" value="download">
      <label>2. Match your columns to Sue's columns</label>
      <p class="muted">We auto-pick obvious matches. Pick <em>(none)</em> to leave a column empty.</p>
      <table class="map">
        <?php foreach (SUE_COLS as $c):
            $hint = ['title'=>'required','subtitle'=>'author / director / artist','year'=>'integer','rating'=>'number','status'=>'read / watching / etc.','image_url'=>'http(s) cover URL','body'=>'your notes'][$c] ?? '';
            $guess = guess_header($c, $headers);
        ?>
          <tr>
            <td class="k"><?= h($c) ?> <small><?= h($hint) ?></small></td>
            <td>
              <select name="map[<?= h($c) ?>]">
                <option value="">(none)</option>
                <?php foreach ($headers as $i => $hd): ?>
                  <option value="<?= $i ?>" <?= $i === $guess ? 'selected' : '' ?>><?= h($hd) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>

      <div class="preview">
        <table>
          <tr><?php foreach ($headers as $hd): ?><th><?= h($hd) ?></th><?php endforeach; ?></tr>
          <?php foreach (array_slice($rows, 0, 8) as $r): ?>
            <tr><?php foreach ($headers as $i => $_): ?><td><?= h((string)($r[$i] ?? '')) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div style="margin-top:12px;">
        <span class="pill"><?= count($rows) ?> rows</span>
        <a href="?" style="color:var(--accent);margin-left:8px;">start over</a>
      </div>
      <button type="submit">Download sue-import.csv</button>
    </form>
  <?php endif; ?>

  <footer>
    Pairs with <strong>Sue</strong>. Made by
    <a href="https://dimaalhajajlah-portfolio.my.canva.site" target="_blank" rel="noopener">Dima Alhajajlah</a>.
  </footer>
</div>
</body>
</html>
