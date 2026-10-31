<?php

/**
 * Generic Table Builder helper (CodeIgniter 4, DataTables server-side)  ->  app/Helpers/table_helper.php
 *
 * Load with `helper('table');`. Every function is prefixed `lb_`.
 * Requires in the layout: jQuery, DataTables (js + css), Bootstrap 5, Bootstrap Icons, toastr (optional).
 *
 * =====================================================================================
 * CONFIGURATION STRUCTURE (built in the Controller, passed to the view as $tableConfig)
 * =====================================================================================
 * Same shape as the Form Builder config (form_helper.php): urls / labels / messages / values / csrf / layout.
 * [
 *   'table_id'  => 'mainDataTables',     // DOM id of the <table>; must be unique per page
 *   'title'     => 'Products',
 *   'subtitle'  => 'All products in the catalogue',
 *   'id_field'  => 'id',                 // JSON key of the primary key: DataTables row id + {id} in urls.edit (null = none)
 *   'method'    => 'POST',               // HTTP verb of the data request (GET|POST)
 *   'editable'  => true,                 // false = no edit-button column
 *   'csrf'      => true,                 // true = auto CI4 token (sent as a header on POST), false = disable
 *   'id_prefix' => null,                 // prepended to filter ids (null = table_id . '_f_')
 *   'layout'    => ['filter_col' => 3],  // default Bootstrap width (1-12) of each filter at md and up
 *   'urls' => [
 *       'data'   => site_url('products/data'),       // JSON endpoint (server-side DataTables)
 *       'create' => site_url('products/create'),     // Add button (rendered only when not empty)
 *       'edit'   => site_url('products/edit/{id}'),  // pattern used by lb_rows(); {field} = row value
 *   ],
 *   'edit_key' => 'edit_url',            // JSON key holding each row's edit URL (always the FIRST column)
 *   'labels'   => ['add'=>'Add New','edit'=>'Edit','reset'=>'Reset','all'=>'All'],
 *   'icons'    => ['add'=>'bi-plus-circle','edit'=>'bi-eye','reset'=>'bi-arrow-counterclockwise'],
 *   'messages' => ['loadFailed'=>...],
 *   'values'   => ['status' => 'A'],     // initial filter values (keyed by filter name)
 *   'columns'  => [ <column>, <column>, ... ],
 *   'filters'  => [ <filter>, <filter>, ... ],
 *   'order'       => [['name', 'asc']],  // [[column data key, dir]]
 *   'page_length' => 25,
 *   'length_menu' => [10, 25, 50, 100],
 *   'max_page_length' => 500,            // server-side cap (also caps DataTables' "All")
 *   'search_delay'    => 400,            // ms debounce for search box and typed filters
 *   'dt_options'  => [],                 // extra raw DataTables options (JSON-safe only)
 * ]
 *
 * COLUMN:
 * [
 *   'data'       => 'price',             // required: JSON key of the row
 *   'title'      => 'Price',             // default: humanised data
 *   'db'         => 'p.price',           // real column/alias used for search + order (default: data)
 *   'type'       => 'text' | 'html' | 'badge' | 'boolean' | 'number',
 *                   // NOTE: 'text' (default) is HTML-escaped; 'html' is inserted as-is, so only send trusted markup.
 *   'orderable'  => true,
 *   'searchable' => true,
 *   'class'      => 'text-end',
 *   'width'      => '10%',
 *   'labels'     => ['A' => 'Active'],   // value => label  (boolean: ['true' => 'Yes', 'false' => 'No'])
 *   'badges'     => ['A' => ['label' => 'Active', 'class' => 'bg-success']],
 *   'decimals'   => 2,                   // number only
 * ]
 *
 * FILTER (same keys as a Form Builder field where they apply):
 * [
 *   'name'        => 'category_id',      // required: sent as filters[name]
 *   'type'        => 'text' | 'search' | 'select' | 'date' | 'datetime-local' | 'time' | 'month' | 'number',
 *   'id'          => 'category_id',      // default: id_prefix + name
 *   'label'       => 'Category',         // default: humanised name
 *   'help'        => 'Help message',
 *   'default'     => 'x',                // initial value when values[name] is not set
 *   'options'     => [1 => 'Electronics', 2 => 'Books']      // value => label
 *                 or [['value'=>1,'label'=>'Electronics','disabled'=>true], ...]
 *   'multiple'    => true,               // select multiple (operator defaults to 'in')
 *   'attrs'       => ['min'=>0,'step'=>1,'placeholder'=>'..','class'=>'extra','data-x'=>'y', ...],
 *                    // shortcut: placeholder/min/max/step/autocomplete may also be set directly on the filter
 *   'column'      => 'p.category_id',    // DB column (default: name)
 *   'operator'    => '=' | '!=' | '>' | '>=' | '<' | '<=' | 'like' | 'in',   // default: like for text, = otherwise
 *   'col'         => 3,                  // Bootstrap width override (default: layout.filter_col)
 * ]
 *
 * Legacy keys (ajax_url, row_id, add[url,label], edit[key,url,label,icon], filter 'value') are still accepted.
 */

// ---------------------------------------------------------------------------------------------
// 0. Utilities
// ---------------------------------------------------------------------------------------------

if (! function_exists('lb_esc')) {
    function lb_esc($value, string $context = 'html'): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return function_exists('esc') ? (string) esc($value, $context) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('lb_attrs')) {
    function lb_attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $out .= ' ' . $key;
                continue;
            }
            if (is_array($value)) {
                $value = implode(' ', array_filter($value, static fn ($x) => $x !== null && $x !== '' && $x !== false));
            }
            $out .= ' ' . $key . '="' . lb_esc($value, 'attr') . '"';
        }

        return $out;
    }
}

if (! function_exists('lb_humanize')) {
    function lb_humanize(string $name): string
    {
        return ucwords(trim(str_replace(['_', '-', '.'], ' ', $name)));
    }
}

if (! function_exists('lb_options')) {
    /** value => label  or  [['value'=>..,'label'=>..,'disabled'=>..], ...]  ->  [['value'=>string,'label'=>string,'disabled'=>bool], ...] */
    function lb_options($options): array
    {
        $out = [];
        foreach ((array) $options as $key => $opt) {
            if (is_array($opt)) {
                $value = $opt['value'] ?? $key;
                $out[] = ['value' => (string) $value, 'label' => (string) ($opt['label'] ?? $value), 'disabled' => ! empty($opt['disabled'])];
            } else {
                $out[] = ['value' => (string) $key, 'label' => (string) $opt, 'disabled' => false];
            }
        }

        return $out;
    }
}

// ---------------------------------------------------------------------------------------------
// 1. Config normalisation
// ---------------------------------------------------------------------------------------------

if (! function_exists('lb_csrf')) {
    function lb_csrf()
    {
        if (! function_exists('csrf_token')) {
            return false;
        }

        return [
            'name'   => csrf_token(),
            'hash'   => function_exists('csrf_hash') ? csrf_hash() : '',
            'header' => function_exists('csrf_header') ? csrf_header() : 'X-CSRF-TOKEN',
        ];
    }
}

if (! function_exists('lb_legacy')) {
    /** Map the old config keys (ajax_url, row_id, add[...], edit[...]) onto the shared structure. */
    function lb_legacy(array $cfg): array
    {
        if (array_key_exists('ajax_url', $cfg)) {
            $cfg['urls']['data'] ??= $cfg['ajax_url'];
        }
        if (array_key_exists('row_id', $cfg) && ! array_key_exists('id_field', $cfg)) {
            $cfg['id_field'] = $cfg['row_id'];
        }
        if (isset($cfg['add']) && is_array($cfg['add'])) {
            if (isset($cfg['add']['url'])) {
                $cfg['urls']['create'] ??= $cfg['add']['url'];
            }
            if (isset($cfg['add']['label'])) {
                $cfg['labels']['add'] ??= $cfg['add']['label'];
            }
        }
        if (isset($cfg['edit']) && is_array($cfg['edit'])) {
            if (isset($cfg['edit']['key'])) {
                $cfg['edit_key'] ??= $cfg['edit']['key'];
            }
            if (isset($cfg['edit']['url'])) {
                $cfg['urls']['edit'] ??= $cfg['edit']['url'];
            }
            if (isset($cfg['edit']['label'])) {
                $cfg['labels']['edit'] ??= $cfg['edit']['label'];
            }
            if (isset($cfg['edit']['icon'])) {
                $cfg['icons']['edit'] ??= $cfg['edit']['icon'];
            }
        }
        unset($cfg['ajax_url'], $cfg['row_id'], $cfg['add'], $cfg['edit']);

        return $cfg;
    }
}

if (! function_exists('lb_defaults')) {
    /** Merge a controller config with defaults. Idempotent, so every helper can safely call it. */
    function lb_defaults(array $cfg): array
    {
        if (! empty($cfg['_lb_ready'])) {
            return $cfg;
        }

        $cfg = lb_legacy($cfg);

        // lists are handled separately: array_replace_recursive would merge them index by index
        $lengthMenu = $cfg['length_menu'] ?? [10, 25, 50, 100];
        unset($cfg['length_menu']);

        $cfg = array_replace_recursive([
            'table_id'        => 'mainDataTables',
            'title'           => '',
            'subtitle'        => '',
            'id_field'        => 'id',
            'method'          => 'POST',
            'editable'        => true,
            'csrf'            => true,
            'id_prefix'       => null,
            'layout'          => ['filter_col' => 3],
            'urls'            => ['data' => '', 'create' => '', 'edit' => ''],
            'edit_key'        => 'edit_url',
            'labels'          => ['add' => 'Add New', 'edit' => 'Edit', 'reset' => 'Reset', 'all' => 'All'],
            'icons'           => ['add' => 'bi-plus-circle', 'edit' => 'bi-eye', 'reset' => 'bi-arrow-counterclockwise'],
            'messages'        => ['loadFailed' => 'Could not load data. Please try again.'],
            'values'          => [],
            'columns'         => [],
            'filters'         => [],
            'order'           => [],
            'page_length'     => 25,
            'max_page_length' => 500,
            'search_delay'    => 400,
            'dt_options'      => [],
        ], $cfg);

        $cfg['length_menu'] = $lengthMenu;
        $cfg['table_id']    = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $cfg['table_id']);
        $cfg['method']      = strtoupper((string) $cfg['method']) === 'GET' ? 'GET' : 'POST';
        $cfg['layout']['filter_col'] = max(1, min(12, (int) $cfg['layout']['filter_col']));
        if ($cfg['id_prefix'] === null) {
            $cfg['id_prefix'] = $cfg['table_id'] . '_f_';
        }
        if ($cfg['csrf'] === true) {
            $cfg['csrf'] = lb_csrf();
        } elseif (! is_array($cfg['csrf'])) {
            $cfg['csrf'] = false;
        }
        $cfg['_lb_ready'] = true;

        return $cfg;
    }
}

if (! function_exists('lb_columns')) {
    /** Normalised column table (without the edit column, which is prepended by the script when editable). */
    function lb_columns(array $cfg): array
    {
        $cfg = lb_defaults($cfg);
        $out = [];
        foreach ((array) $cfg['columns'] as $c) {
            $data = (string) ($c['data'] ?? '');
            if ($data === '') {
                continue;
            }
            $out[] = array_merge([
                'title'      => lb_humanize($data),
                'db'         => $data,
                'type'       => 'text',
                'orderable'  => true,
                'searchable' => true,
                'class'      => '',
                'width'      => '',
            ], $c, ['data' => $data]);
        }

        return $out;
    }
}

if (! function_exists('lb_filters_norm')) {
    function lb_filters_norm(array $cfg): array
    {
        $cfg    = lb_defaults($cfg);
        $ops    = ['=', '!=', '>', '>=', '<', '<=', 'like', 'in'];
        $values = (array) $cfg['values'];
        $out    = [];
        foreach ((array) $cfg['filters'] as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $type     = strtolower((string) ($f['type'] ?? 'text'));
            $multiple = ! empty($f['multiple']) && $type === 'select';
            $op       = strtolower((string) ($f['operator'] ?? ($multiple ? 'in' : ($type === 'text' ? 'like' : '='))));

            $value = array_key_exists($name, $values) ? $values[$name] : ($f['default'] ?? $f['value'] ?? null);

            $a = (array) ($f['attrs'] ?? []);
            foreach (['placeholder', 'min', 'max', 'step', 'autocomplete'] as $k) {
                if (array_key_exists($k, $f) && ! array_key_exists($k, $a)) {
                    $a[$k] = $f[$k];
                }
            }

            $out[] = [
                'name'     => $name,
                'type'     => $type,
                'id'       => (string) ($f['id'] ?? ($cfg['id_prefix'] . preg_replace('/[^A-Za-z0-9_\-]/', '_', $name))),
                'label'    => (string) ($f['label'] ?? lb_humanize($name)),
                'help'     => (string) ($f['help'] ?? ''),
                'options'  => lb_options($f['options'] ?? []),
                'value'    => $value ?? ($multiple ? [] : ''),
                'column'   => (string) ($f['column'] ?? $name),
                'operator' => in_array($op, $ops, true) ? $op : '=',
                'multiple' => $multiple,
                'col'      => max(1, min(12, (int) ($f['col'] ?? $cfg['layout']['filter_col']))),
                'attrs'    => $a,
            ];
        }

        return $out;
    }
}

// ---------------------------------------------------------------------------------------------
// 2. Rendering (each piece can be called on its own)
// ---------------------------------------------------------------------------------------------

if (! function_exists('lb_header')) {
    function lb_header(array $cfg): string
    {
        $cfg = lb_defaults($cfg);

        return '<div class="card-header"><h5 class="card-title">' . lb_esc($cfg['title']) . '</h5>'
            . ($cfg['subtitle'] !== '' ? '<p class="card-subtitle">' . lb_esc($cfg['subtitle']) . '</p>' : '')
            . '</div>';
    }
}

if (! function_exists('lb_add_button')) {
    /** "Add" button -> form page in create mode. Rendered only when urls.create is not empty. */
    function lb_add_button(array $cfg): string
    {
        $cfg = lb_defaults($cfg);
        if (trim((string) $cfg['urls']['create']) === '') {
            return '';
        }

        return '<div class="text-end mb-3 me-3"><a class="btn btn-outline-primary" href="' . lb_esc($cfg['urls']['create'], 'attr') . '">'
            . '<i class="bi ' . lb_esc($cfg['icons']['add'], 'attr') . '"></i> ' . lb_esc($cfg['labels']['add']) . '</a></div>';
    }
}

if (! function_exists('lb_filter_control')) {
    /** One filter (label + control + help) inside a Bootstrap column. */
    function lb_filter_control(array $f, array $cfg): string
    {
        $cfg   = lb_defaults($cfg);
        $id    = $f['id'];
        $attrs = $f['attrs'];
        $extra = $attrs['class'] ?? '';
        unset($attrs['class']);
        $extra = is_array($extra) ? implode(' ', $extra) : (string) $extra;

        if ($f['type'] === 'select') {
            $ph = array_key_exists('placeholder', $attrs) ? $attrs['placeholder'] : $cfg['labels']['all'];
            unset($attrs['placeholder']);
            $sel   = array_map('strval', (array) $f['value']);
            $attrs = ['id' => $id, 'data-lb-filter' => $f['name'], 'class' => trim('form-select ' . $extra), 'multiple' => $f['multiple']] + $attrs;
            $html  = '<select' . lb_attrs($attrs) . '>';
            if (! $f['multiple'] && $ph !== false && $ph !== null) {
                $html .= '<option value="">' . lb_esc($ph) . '</option>';
            }
            foreach ($f['options'] as $o) {
                $html .= '<option value="' . lb_esc($o['value'], 'attr') . '"'
                    . (in_array($o['value'], $sel, true) ? ' selected' : '')
                    . ($o['disabled'] ? ' disabled' : '') . '>' . lb_esc($o['label']) . '</option>';
            }
            $control = $html . '</select>';
        } else {
            $type    = in_array($f['type'], ['text', 'search', 'date', 'number', 'datetime-local', 'time', 'month'], true) ? $f['type'] : 'text';
            $attrs   = ['type' => $type, 'id' => $id, 'data-lb-filter' => $f['name'], 'class' => trim('form-control ' . $extra),
                        'value' => is_array($f['value']) ? '' : (string) $f['value']] + $attrs;
            $control = '<input' . lb_attrs($attrs) . '>';
        }

        return '<div class="col-12 col-md-' . $f['col'] . '"><label class="form-label" for="' . lb_esc($id, 'attr') . '">' . lb_esc($f['label']) . '</label>'
            . $control . ($f['help'] !== '' ? '<div class="form-text">' . lb_esc($f['help']) . '</div>' : '') . '</div>';
    }
}

if (! function_exists('lb_filters')) {
    /** The filter area. Returns '' when the controller passed no filters. */
    function lb_filters(array $cfg): string
    {
        $cfg     = lb_defaults($cfg);
        $filters = lb_filters_norm($cfg);
        if (! $filters) {
            return '';
        }
        $html = '<form id="' . lb_esc($cfg['table_id'], 'attr') . '-filters" class="row g-2 align-items-end mb-3 mx-3" autocomplete="off">';
        foreach ($filters as $f) {
            $html .= lb_filter_control($f, $cfg);
        }

        return $html . '<div class="col-auto"><button type="reset" class="btn btn-outline-secondary"><i class="bi ' . lb_esc($cfg['icons']['reset'], 'attr') . '"></i> '
            . lb_esc($cfg['labels']['reset']) . '</button></div></form>';
    }
}

if (! function_exists('lb_table')) {
    /** <table> with the header row. When editable, the first <th> belongs to the edit-button column. */
    function lb_table(array $cfg): string
    {
        $cfg  = lb_defaults($cfg);
        $html = '<div class="table-responsive"><table id="' . lb_esc($cfg['table_id'], 'attr') . '" class="table table-hover table-striped" style="width:100%">'
            . '<thead><tr>';
        if ($cfg['editable']) {
            $html .= '<th style="width:1%"><span class="visually-hidden">' . lb_esc($cfg['labels']['edit']) . '</span></th>';
        }
        foreach (lb_columns($cfg) as $c) {
            $html .= '<th' . ($c['width'] !== '' ? ' style="width:' . lb_esc($c['width'], 'attr') . '"' : '') . '>' . lb_esc($c['title']) . '</th>';
        }

        return $html . '</tr></thead><tbody></tbody></table></div>';
    }
}

if (! function_exists('lb_render')) {
    /** MAIN FUNCTION: the whole card (header, add button, filters, table). */
    function lb_render(array $cfg): string
    {
        $cfg = lb_defaults($cfg);

        return '<div class="card" id="' . lb_esc($cfg['table_id'], 'attr') . '-card">' . lb_header($cfg) . '<div class="card-body px-0">'
            . lb_add_button($cfg) . lb_filters($cfg) . lb_table($cfg)
            . '</div></div>';
    }
}

if (! function_exists('lb_script')) {
    /**
     * Generates the <script> that initialises DataTables, the edit column and the filters.
     * Echo it after lb_render(): <?= lb_script($tableConfig) ?>
     */
    function lb_script(array $cfg): string
    {
        $cfg     = lb_defaults($cfg);
        $columns = lb_columns($cfg);
        $offset  = $cfg['editable'] ? 1 : 0; // the edit column, when present, is always column 0
        $index   = [];
        foreach ($columns as $i => $c) {
            $index[$c['data']] = $i + $offset;
        }
        $order = [];
        foreach ((array) $cfg['order'] as $o) {
            if (isset($index[$o[0] ?? ''])) {
                $order[] = [$index[$o[0]], strtolower((string) ($o[1] ?? 'asc')) === 'desc' ? 'desc' : 'asc'];
            }
        }

        $jsCols = [];
        foreach ($columns as $c) {
            $jsCols[] = [
                'data'       => $c['data'],
                'type'       => $c['type'],
                'orderable'  => (bool) $c['orderable'],
                'searchable' => (bool) $c['searchable'],
                'className'  => $c['class'],
                'width'      => $c['width'],
                'labels'     => (object) ($c['labels'] ?? []),
                'badges'     => (object) ($c['badges'] ?? []),
                'decimals'   => isset($c['decimals']) ? (int) $c['decimals'] : null,
            ];
        }

        $js = [
            'tableId'     => $cfg['table_id'],
            'method'      => $cfg['method'],
            'idField'     => $cfg['id_field'],
            'editable'    => (bool) $cfg['editable'],
            'editKey'     => $cfg['edit_key'],
            'urls'        => ['data' => $cfg['urls']['data']],
            'labels'      => $cfg['labels'],
            'icons'       => $cfg['icons'],
            'csrf'        => $cfg['csrf'] ? ['name' => $cfg['csrf']['name'], 'hash' => $cfg['csrf']['hash'], 'header' => $cfg['csrf']['header']] : null,
            'columns'     => $jsCols,
            'order'       => $order,
            'pageLength'  => (int) $cfg['page_length'],
            'lengthMenu'  => $cfg['length_menu'],
            'searchDelay' => (int) $cfg['search_delay'],
            'dtOptions'   => (object) $cfg['dt_options'],
            'messages'    => $cfg['messages'],
        ];
        $json = json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $script = <<<'JS'
(function (cfg) {
  'use strict';

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function notify(type, html) {
    if (window.toastr && typeof window.toastr[type] === 'function') {
      try { window.toastr[type](html, '', { timeOut: 5000, closeButton: true, progressBar: true, escapeHtml: false }); return; }
      catch (e) { /* fall through */ }
    }
    if (window.console) { console[type === 'error' ? 'error' : 'log'](html); }
  }

  // Cell renderers. DataTables does NOT escape by default, so every non-"html" type escapes here.
  function renderer(c) {
    var labels = c.labels || {};
    var badges = c.badges || {};
    function badge(text, cls) { return '<span class="badge ' + esc(cls || 'bg-secondary') + '">' + esc(text) + '</span>'; }

    switch (c.type) {
      case 'html':
        return null; // raw HTML from the server (trusted markup only)
      case 'badge':
        return function (d, type) {
          if (type !== 'display') { return d; }
          if (d === null || d === undefined || d === '') { return ''; }
          var b = badges[d];
          if (b) { return badge(b.label !== undefined ? b.label : (labels[d] !== undefined ? labels[d] : d), b.class); }
          return badge(labels[d] !== undefined ? labels[d] : d, 'bg-secondary');
        };
      case 'boolean':
        return function (d, type) {
          if (type !== 'display') { return d; }
          var on = d === true || d === 1 || d === '1' || d === 'true';
          return on ? badge(labels['true'] !== undefined ? labels['true'] : 'Yes', 'bg-success')
                    : badge(labels['false'] !== undefined ? labels['false'] : 'No', 'bg-secondary');
        };
      case 'number':
        return function (d, type) {
          if (type !== 'display' || d === null || d === undefined || d === '' || isNaN(Number(d))) { return d === null || d === undefined ? '' : esc(d); }
          var opt = c.decimals === null ? {} : { minimumFractionDigits: c.decimals, maximumFractionDigits: c.decimals };
          return esc(Number(d).toLocaleString(undefined, opt));
        };
      default:
        return function (d, type) {
          if (type !== 'display') { return d; }
          if (d === null || d === undefined) { return ''; }
          return esc(labels[d] !== undefined ? labels[d] : d);
        };
    }
  }

  function init() {
    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.DataTable) {
      if (window.console) { console.error('TableBuilder: jQuery/DataTables must be loaded before this script.'); }
      return;
    }
    var tableEl = document.getElementById(cfg.tableId);
    if (!tableEl) { return; }
    var filterForm = document.getElementById(cfg.tableId + '-filters');
    var csrfHash = cfg.csrf ? cfg.csrf.hash : '';

    // ---- filters -> extra request parameters: filters[name]=value --------------------------
    function currentFilters() {
      var out = {};
      if (!filterForm) { return out; }
      filterForm.querySelectorAll('[data-lb-filter]').forEach(function (el) {
        var name = el.getAttribute('data-lb-filter');
        if (el.multiple) {
          var vals = Array.prototype.slice.call(el.selectedOptions).map(function (o) { return o.value; }).filter(Boolean);
          if (vals.length) { out[name] = vals; }
        } else if (String(el.value).trim() !== '') {
          out[name] = String(el.value).trim();
        }
      });
      return out;
    }

    // ---- columns: the FIRST column is the edit button (URL comes from the JSON) ------------
    var columns = [];
    if (cfg.editable) {
      columns.push({
        data: cfg.editKey, orderable: false, searchable: false, className: 'text-nowrap', defaultContent: '',
        render: function (url, type) {
          if (type !== 'display' || !url) { return ''; }
          return '<a class="btn btn-sm btn-outline-primary" href="' + esc(url) + '" title="' + esc(cfg.labels.edit) + '">' +
                 '<i class="bi ' + esc(cfg.icons.edit) + '"></i></a>';
        }
      });
    }
    cfg.columns.forEach(function (c) {
      var col = { data: c.data, orderable: c.orderable, searchable: c.searchable, className: c.className || '', defaultContent: '' };
      var r = renderer(c);
      if (r) { col.render = r; }
      columns.push(col);
    });

    // Surface server / network errors as a toast and keep the CSRF token fresh. (Returning true tells
    // DataTables the error was handled; if your DataTables version still shows its own alert,
    // set $.fn.dataTable.ext.errMode = 'none'.)
    $(tableEl).on('xhr.dt', function (e, settings, json, xhr) {
      if (cfg.csrf) {
        var fresh = (json && json.csrf_hash) || (xhr && xhr.getResponseHeader && xhr.getResponseHeader(cfg.csrf.header));
        if (fresh) { csrfHash = fresh; }
      }
      if (!json || json.error) {
        notify('error', esc((json && json.error) || cfg.messages.loadFailed));
        return true;
      }
    });

    var opts = {
      processing: true,
      serverSide: true,
      searchDelay: cfg.searchDelay,
      pageLength: cfg.pageLength,
      lengthMenu: cfg.lengthMenu,
      order: cfg.order,
      columns: columns,
      ajax: {
        url: cfg.urls.data,
        type: cfg.method,
        data: function (d) { d.filters = currentFilters(); },
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
          if (cfg.csrf && cfg.method !== 'GET' && csrfHash) { xhr.setRequestHeader(cfg.csrf.header, csrfHash); }
        }
      }
    };
    if (cfg.idField) { opts.rowId = cfg.idField; }
    var table = $(tableEl).DataTable($.extend(true, opts, cfg.dtOptions));

    // ---- when a filter changes, reload the data (back to page 1) ---------------------------
    if (filterForm) {
      var timer = null;
      var typed = 'input[type="text"], input[type="search"], input[type="number"]';
      function reload() { table.ajax.reload(); }

      filterForm.addEventListener('change', function (e) {
        if (e.target.matches(typed)) { return; } // typing is handled (debounced) below
        reload();
      });
      filterForm.addEventListener('input', function (e) {
        if (!e.target.matches(typed)) { return; }
        clearTimeout(timer);
        timer = setTimeout(reload, cfg.searchDelay);
      });
      filterForm.addEventListener('submit', function (e) { e.preventDefault(); reload(); });
      filterForm.addEventListener('reset', function () { setTimeout(reload, 0); });
    }

    // Public handle, e.g. window.TableBuilder['mainDataTables'].reload()
    window.TableBuilder = window.TableBuilder || {};
    window.TableBuilder[cfg.tableId] = { table: table, filters: currentFilters, reload: function () { table.ajax.reload(); } };
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})(__LB_CONFIG__);
JS;

        return '<script>' . "\n" . str_replace('__LB_CONFIG__', $json, $script) . "\n" . '</script>';
    }
}

// ---------------------------------------------------------------------------------------------
// 3. Server side (use in the controller's JSON endpoint)
// ---------------------------------------------------------------------------------------------

if (! function_exists('lb_request')) {
    /** Parse the DataTables request (cfg.method) into a safe structure. Only configured columns/filters are honoured. */
    function lb_request($request, array $cfg): array
    {
        $cfg = lb_defaults($cfg);
        $g   = (array) ($cfg['method'] === 'GET' ? $request->getGet() : $request->getPost());

        $cols = [];
        foreach (lb_columns($cfg) as $c) {
            $cols[$c['data']] = $c;
        }

        $length = isset($g['length']) ? (int) $g['length'] : (int) $cfg['page_length'];
        if ($length < 1 || $length > $cfg['max_page_length']) {
            $length = (int) $cfg['max_page_length']; // also caps DataTables' "All" (-1)
        }

        $order = [];
        foreach ((array) ($g['order'] ?? []) as $o) {
            $key = $g['columns'][(int) ($o['column'] ?? -1)]['data'] ?? null;
            if ($key !== null && isset($cols[$key]) && $cols[$key]['orderable']) {
                $order[] = [$cols[$key]['db'], strtolower((string) ($o['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC'];
            }
        }
        if (! $order) {
            foreach ((array) $cfg['order'] as $o) {
                if (isset($cols[$o[0] ?? ''])) {
                    $order[] = [$cols[$o[0]]['db'], strtolower((string) ($o[1] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC'];
                }
            }
        }

        $raw     = (array) ($g['filters'] ?? []);
        $filters = [];
        foreach (lb_filters_norm($cfg) as $f) {
            if (! array_key_exists($f['name'], $raw) || $raw[$f['name']] === '' || $raw[$f['name']] === []) {
                continue;
            }
            $filters[] = ['def' => $f, 'value' => $raw[$f['name']]];
        }

        return [
            'draw'    => (int) ($g['draw'] ?? 0),
            'start'   => max(0, (int) ($g['start'] ?? 0)),
            'length'  => $length,
            'search'  => trim((string) ($g['search']['value'] ?? '')),
            'order'   => $order,
            'filters' => $filters,
            'columns' => array_values($cols),
        ];
    }
}

if (! function_exists('lb_apply_filters')) {
    /** Apply the request filters to a CI4 query builder. */
    function lb_apply_filters($builder, array $filters): void
    {
        foreach ($filters as $item) {
            $f   = $item['def'];
            $v   = $item['value'];
            $col = $f['column'];
            $ok  = array_column($f['options'], 'value');

            if (is_array($v) || $f['operator'] === 'in') {
                $vals = array_values(array_filter(array_map('strval', (array) $v), 'strlen'));
                if ($ok) {
                    $vals = array_values(array_intersect($vals, $ok)); // labels differ from values: only accept configured values
                }
                if ($vals) {
                    $builder->whereIn($col, $vals);
                }
                continue;
            }

            $v = (string) $v;
            if ($f['type'] === 'select' && $ok && ! in_array($v, $ok, true)) {
                continue;
            }
            // "created to 2026-10-30" must include the whole day for DATETIME columns
            if ($f['type'] === 'date' && $f['operator'] === '<=' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $v .= ' 23:59:59';
            }
            switch ($f['operator']) {
                case 'like':
                    $builder->like($col, $v);
                    break;
                case '=':
                    $builder->where($col, $v);
                    break;
                default: // != > >= < <=
                    $builder->where($col . ' ' . $f['operator'], $v);
            }
        }
    }
}

if (! function_exists('lb_rows')) {
    /** Add the edit URL (JSON key edit_key) to every row from the urls.edit pattern, e.g. products/edit/{id}. */
    function lb_rows(array $rows, array $cfg): array
    {
        $cfg     = lb_defaults($cfg);
        $key     = $cfg['edit_key'];
        $pattern = (string) $cfg['urls']['edit'];
        if ($pattern === '' || ! $cfg['editable']) {
            return $rows;
        }
        foreach ($rows as &$row) {
            if (empty($row[$key])) {
                // base_url()/site_url() percent-encode the braces, so {id} may arrive as %7Bid%7D
                $row[$key] = preg_replace_callback('/(?:\{|%7B)(\w+)(?:\}|%7D)/i', static fn ($m) => rawurlencode((string) ($row[$m[1]] ?? '')), $pattern);
            }
        }
        unset($row);

        return $rows;
    }
}

if (! function_exists('lb_response')) {
    function lb_response(int $draw, int $total, int $filtered, array $rows): array
    {
        return ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows];
    }
}

if (! function_exists('lb_handle')) {
    /**
     * One call for the whole JSON endpoint.
     *
     *   $builder = $this->db->table('products p')->select('p.id, p.name, c.name AS category')->join(...);
     *   return $this->response->setJSON(lb_handle($builder, $this->tableConfig(), $this->request));
     *
     * @param object        $builder CI4 query builder (base conditions already applied, no limit/order)
     * @param callable|null $rowFn   optional fn(array $row): array to format a row (dates, html, ...)
     */
    function lb_handle($builder, array $cfg, $request, ?callable $rowFn = null): array
    {
        $cfg = lb_defaults($cfg);
        $p   = lb_request($request, $cfg);

        $total = (int) $builder->countAllResults(false); // base conditions only

        lb_apply_filters($builder, $p['filters']);

        if ($p['search'] !== '') {
            $searchable = array_filter($p['columns'], static fn ($c) => $c['searchable']);
            if ($searchable) {
                $builder->groupStart();
                foreach ($searchable as $c) {
                    $builder->orLike($c['db'], $p['search']);
                }
                $builder->groupEnd();
            }
        }

        $filtered = (int) $builder->countAllResults(false);

        foreach ($p['order'] as [$col, $dir]) {
            $builder->orderBy($col, $dir);
        }
        $rows = $builder->limit($p['length'], $p['start'])->get()->getResultArray();

        if ($rowFn) {
            $rows = array_map($rowFn, $rows);
        }

        $out = lb_response($p['draw'], $total, $filtered, lb_rows($rows, $cfg));
        if ($cfg['csrf'] && function_exists('csrf_hash')) {
            $out['csrf_hash'] = csrf_hash(); // lets the script keep its token fresh when CSRF regenerates
        }

        return $out;
    }
}
