# Sue CSV Converter

Two standalone tools that take any spreadsheet and convert it to a CSV in
**Sue's exact import format**:

```
title,subtitle,year,rating,status,image_url,body
```

`title` is required. Everything else is optional and becomes empty if left
unmapped.

## Two versions

### `index.html` — browser-only (no server needed)

Just open it in a browser. Reads `.xlsx`, `.xls`, `.csv` entirely client-side
using SheetJS (loaded from CDN). Nothing is uploaded anywhere.

- Double-click `index.html`, or host it anywhere static (GitHub Pages,
  InfinityFree htdocs, your own laptop).
- Internet required the first time only (to fetch SheetJS from CDN).

### `index.php` — server-side PHP

Drop into any PHP 8+ host (e.g. InfinityFree). Reads `.xlsx` and `.csv`,
shows a column-mapping form, then streams a clean CSV back as a download.

- Run locally: `php -S localhost:8001 -t sue-converter`
  then open `http://localhost:8001/`.
- On InfinityFree: upload the folder to `htdocs/sue-converter/`.

## How it works

1. **Upload** any `.xlsx` or `.csv`.
2. The tool reads the first sheet's header row, then auto-matches obvious
   column names (e.g. `Author` → `subtitle`, `My Rating` → `rating`,
   `Exclusive Shelf` → `status`, `Year Published` → `year`).
3. **Adjust the mapping** — pick which input column maps to each Sue column
   from a dropdown. Choose `(none)` to leave a column empty.
4. Click **Download sue-import.csv**.

Output is always exactly:

```
title,subtitle,year,rating,status,image_url,body
```

with proper quoting, UTF-8 BOM (so Excel opens it correctly), and these
cleanups:

- `year` — first 4-digit number found in the cell.
- `rating` — first number found in the cell.
- `image_url` — only kept if it starts with `http://` or `https://`.

The output drops straight into Sue's import flow:
**dashboard → edit page → import CSV / XLSX**.
