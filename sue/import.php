<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$u = require_login();

$sid = (int)($_GET['id'] ?? $_POST['section_id'] ?? 0);
$section = fetch_one('SELECT * FROM sections WHERE id=? AND user_id=?', [$sid, $u['id']], 'ii');
if (!$section) { http_response_code(404); die('Section not found.'); }

$step = $_POST['step'] ?? 'upload';
$message = '';
$preview = null;
$headers = [];
$rows = [];

function read_csv(string $path): array {
    $rows = [];
    if (($fh = fopen($path, 'r')) !== false) {
        while (($r = fgetcsv($fh)) !== false) $rows[] = $r;
        fclose($fh);
    }
    return $rows;
}

// XLSX (Office Open XML) — minimal reader without composer.
// Protected against zip bombs: limits per-entry uncompressed size.
function read_xlsx(string $path): array {
    $rows = [];
    if (!class_exists('ZipArchive')) return $rows;
    $MAX_ENTRY_SIZE = 50 * 1024 * 1024; // 50 MB uncompressed per entry
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return $rows;
    $safe_get = function(ZipArchive $zip, string $name) use ($MAX_ENTRY_SIZE) {
        $stat = $zip->statName($name);
        if ($stat === false) return false;
        if (($stat['size'] ?? 0) > $MAX_ENTRY_SIZE) return false;
        return $zip->getFromName($name);
    };
    $shared = [];
    $ssXml = $safe_get($zip, 'xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sx = @simplexml_load_string($ssXml);
        if ($sx) foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }
    $sheetXml = $safe_get($zip, 'xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return $rows;
    $sx = @simplexml_load_string($sheetXml);
    if (!$sx) return $rows;
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        $col_idx = 0;
        foreach ($row->c as $c) {
            // column letter -> index
            $ref = (string)$c['r'];
            $letters = preg_replace('/[0-9]/', '', $ref);
            $idx = 0;
            for ($i=0; $i<strlen($letters); $i++) $idx = $idx*26 + (ord($letters[$i]) - 64);
            $idx -= 1;
            while ($col_idx < $idx) { $line[] = ''; $col_idx++; }
            $val = (string)$c->v;
            if ((string)$c['t'] === 's') $val = $shared[(int)$val] ?? '';
            elseif ((string)$c['t'] === 'inlineStr' && isset($c->is->t)) $val = (string)$c->is->t;
            $line[] = $val;
            $col_idx++;
        }
        $rows[] = $line;
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'upload') {
    check_csrf();
    $MAX_UPLOAD = 8 * 1024 * 1024; // 8 MB upload cap
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Pick a file first.';
    } elseif (($_FILES['file']['size'] ?? 0) > $MAX_UPLOAD) {
        $message = 'File too big. Keep it under 8 MB.';
    } else {
        $name = strtolower($_FILES['file']['name']);
        $tmp = $_FILES['file']['tmp_name'];
        if (str_ends_with($name, '.xlsx')) $rows = read_xlsx($tmp);
        else $rows = read_csv($tmp);

        if (count($rows) < 2) {
            $message = 'File looks empty or unreadable.';
        } else {
            $headers = array_map('strval', array_shift($rows));
            $_SESSION['import_'.$sid] = ['headers' => $headers, 'rows' => array_slice($rows, 0, 2000)];
            $step = 'map';
            $preview = array_slice($_SESSION['import_'.$sid]['rows'], 0, 5);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'commit') {
    check_csrf();
    $stash = $_SESSION['import_'.$sid] ?? null;
    if (!$stash) { redirect('/import.php?id=' . $sid); }
    $map = $_POST['map'] ?? [];
    $idx = function(string $field) use ($map) {
        $v = $map[$field] ?? '';
        return $v === '' ? null : (int)$v;
    };
    $i_title = $idx('title'); $i_sub = $idx('subtitle');
    $i_year = $idx('year');   $i_rate = $idx('rating');
    $i_stat = $idx('status'); $i_img  = $idx('image_url');
    $i_body = $idx('body');

    $next = (int)(fetch_one('SELECT COALESCE(MAX(sort_order),0) AS n FROM items WHERE section_id=?', [$sid], 'i')['n'] ?? 0);
    $count = 0;
    foreach ($stash['rows'] as $r) {
        $get = function(?int $i) use ($r) { return ($i===null || !isset($r[$i])) ? null : trim((string)$r[$i]); };
        $title = $get($i_title);
        if ($title === null || $title === '') continue;
        $next++; $count++;
        insert(
            'INSERT INTO items (section_id,title,subtitle,body,rating,year,status,image_url,sort_order)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$sid, $title, $get($i_sub), $get($i_body),
             $get($i_rate)!==null && is_numeric($get($i_rate)) ? (float)$get($i_rate) : null,
             $get($i_year)!==null && is_numeric($get($i_year)) ? (int)$get($i_year) : null,
             $get($i_stat), safe_image_url($get($i_img)), $next],
            'isssdisss'
        );
    }
    unset($_SESSION['import_'.$sid]);
    flash("Imported $count items.");
    redirect('/edit-section.php?id=' . $sid);
}

// If we already have a stash, show map step
if ($step !== 'map' && !empty($_SESSION['import_'.$sid])) {
    $headers = $_SESSION['import_'.$sid]['headers'];
    $preview = array_slice($_SESSION['import_'.$sid]['rows'], 0, 5);
    $step = 'map';
}

$page_title = 'Import · ' . $section['title'];
include __DIR__ . '/includes/header.php';
?>
<p class="small"><a href="<?= url('/edit-section.php?id=' . $sid) ?>">← back to <?= h($section['title']) ?></a></p>

<h1>Import.</h1>
<p class="tagline">CSV or XLSX → <?= h($section['title']) ?></p>

<?php if ($message): ?><div class="sticky-note" style="background:#ffd5d5;"><?= h($message) ?></div><?php endif; ?>

<?php if ($step === 'upload'): ?>
  <p class="muted small">Letterboxd, Goodreads, Spotify, or any spreadsheet. We'll show you a preview before saving anything.</p>
  <form method="post" enctype="multipart/form-data" class="notebook" style="max-width:560px;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="step" value="upload">
    <div class="row">
      <label>file (.csv or .xlsx)</label>
      <input type="file" name="file" accept=".csv,.xlsx" required>
    </div>
    <button type="submit">read file</button>
  </form>

<?php else:
  $field_options = [
    'title'=>'Title (required)',
    'subtitle'=>'Author / artist / subtitle',
    'year'=>'Year',
    'rating'=>'Rating',
    'status'=>'Status',
    'image_url'=>'Poster / cover URL',
    'body'=>'Review / notes / quote',
  ];
?>
  <p class="muted small">Map your file's columns to Sue fields. Leave a field blank to skip it.</p>

  <form method="post" class="notebook">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="step" value="commit">
    <table class="notebook" style="max-width:680px;">
      <?php foreach ($field_options as $key => $label):
        // Pre-pick if header name matches
        $pre = '';
        foreach ($headers as $i => $hd) {
          if (strcasecmp(trim($hd), $key) === 0 || stripos($hd, $key) !== false) { $pre = (string)$i; break; }
        }
      ?>
        <tr>
          <th style="width:40%;"><?= h($label) ?></th>
          <td>
            <select name="map[<?= $key ?>]">
              <option value="">— skip —</option>
              <?php foreach ($headers as $i => $hd): ?>
                <option value="<?= $i ?>" <?= $pre === (string)$i ? 'selected' : '' ?>><?= h($hd) ?: '(col '.($i+1).')' ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div class="spacer"></div>
    <button type="submit">import items</button>
  </form>

  <hr class="rule">
  <h3>Preview (first 5 rows)</h3>
  <div style="overflow:auto;">
  <table class="notebook">
    <tr><?php foreach ($headers as $hd): ?><th><?= h($hd) ?></th><?php endforeach; ?></tr>
    <?php foreach ($preview as $r): ?>
      <tr><?php foreach ($headers as $i => $_): ?><td><?= h($r[$i] ?? '') ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
