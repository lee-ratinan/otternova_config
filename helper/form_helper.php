<?php

/**
 * Generic Form Builder helper (CodeIgniter 4)  ->  app/Helpers/form_helper.php
 *
 * Load with `helper('form');`. CI4 core already has a `form` helper, so every function here is
 * prefixed `fb_` to avoid clashing with form_open(), form_input(), etc.
 *
 * =====================================================================================
 * CONFIGURATION STRUCTURE (built in the Controller, passed to the view as $formConfig)
 * =====================================================================================
 * [
 *   'form_id'   => 'fb-form',            // DOM id of the <form>; must be unique per page
 *   'title'     => 'Edit Product',
 *   'subtitle'  => 'Update the product details',
 *   'mode'      => 'create'|'edit'|'view',
 *   'id_field'  => 'id',                 // name of the primary-key field (hidden input)
 *   'id'        => 12,                   // PK value (defaults to values[id_field])
 *   'method'    => 'POST',               // HTTP verb for saving; PUT/PATCH are sent as POST + _method
 *   'delete_method' => 'POST',           // verb for delete (DELETE => POST + _method=DELETE)
 *   'editable'  => true,                 // false = view-only page, no Edit button
 *   'deletable' => null,                 // null = true when urls.delete is set
 *   'csrf'      => true,                 // true = auto CI4 token, false = disable
 *   'id_prefix' => '',                   // prepended to auto-generated field ids
 *   'layout'    => ['label_col' => 4],   // label column width (Bootstrap 1-11); control = 12 - label_col
 *   'urls' => [
 *       'submit' => site_url('products/save'),     // create/edit POST target
 *       'delete' => site_url('products/delete'),
 *       'edit'   => site_url('products/edit/12'),  // fallback href of the Edit button (no-JS)
 *       'list'   => site_url('products'),
 *       'after_save'   => '',            // redirect after save ('' = reload; create mode = reset form)
 *       'after_delete' => '',            // redirect after delete (falls back to urls.list)
 *   ],
 *   'labels'   => ['reset'=>'Reset','save'=>'Save Change','edit'=>'Edit','delete'=>'Delete','cancel'=>'Cancel'],
 *   'messages' => ['saved'=>..., 'saveFailed'=>..., 'deleted'=>..., 'deleteFailed'=>..., 'confirmDelete'=>..., 'fixErrors'=>...],
 *   'values'   => ['name' => 'Foo', 'tags' => ['a','b'], 'is_active' => 1],   // saved values (edit/view)
 *   'fields'   => [ <field>, <field>, ... ],
 * ]
 *
 * FIELD:
 * [
 *   'name'  => 'category_id',            // required (not for heading/divider/html)
 *   'type'  => 'text' | 'email' | 'password' | 'number' | 'range' | 'date' | 'datetime-local' | 'time'
 *            | 'month' | 'week' | 'color' | 'tel' | 'url' | 'search' | 'hidden'
 *            | 'textarea' | 'select' | 'radio' | 'checkbox' | 'switch'
 *            | 'heading' (label) | 'divider' | 'html' (html => raw markup)
 *   'id'          => 'category_id',      // default: id_prefix + name
 *   'label'       => 'Category',         // default: humanised name
 *   'help'        => 'Help message',
 *   'badge'       => 'New' | ['text'=>'New','class'=>'bg-success'],
 *   'optional'    => true,               // shows the "(optional)" marker
 *   'default'     => 'x',                // initial value in create mode
 *   'attrs'       => ['min'=>0,'max'=>10,'step'=>0.5,'required'=>true,'disabled'=>true,'readonly'=>true,
 *                     'placeholder'=>'..','maxlength'=>50,'pattern'=>'[A-Z]+','class'=>'extra','data-x'=>'y', ...],
 *                    // shortcut: placeholder/min/max/step/minlength/maxlength/pattern/rows/cols/required/
 *                    // disabled/readonly/autocomplete may also be set directly on the field
 *   'readonly_in' => ['edit'],           // make readonly only in these modes (create|edit)
 *   'disabled_in' => ['edit'],
 *   'show_in'     => ['create','edit','view'],   // or 'hide_in' => ['create']
 *   'options'     => [1 => 'Electronics', 2 => 'Books']      // value => label
 *                 or [['value'=>1,'label'=>'Electronics','disabled'=>true], ...]
 *   'multiple'    => true,               // select multiple (checkbox with options is always multi-value)
 *   'inline'      => true,               // radio/checkbox groups on one line
 *   'text'        => 'Enable',           // text beside a single checkbox / switch
 *   'checked_value' => '1', 'off_value' => '0',     // single checkbox / switch submitted values
 *   'view_labels' => ['on'=>'Active','off'=>'Inactive'],  // how a single checkbox/switch reads in view mode
 *   'view'        => function ($value, $field) { return strtoupper($value); },  // custom view-mode text
 *   'rules'       => 'required|min_length[3]|max_length[50]|valid_email'   // CI4 rule string (or array)
 *   'messages'    => ['required' => 'Please enter a name.'],  // custom messages keyed by rule name
 * ]
 *
 * The same `rules` strings are used on the client (JS) and by fb_server_validation() on the server.
 */

// ---------------------------------------------------------------------------------------------
// 0. Small utilities
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_esc')) {
    /** HTML/attribute escape (uses CI4 esc() when available). */
    function fb_esc($value, string $context = 'html'): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return function_exists('esc') ? (string) esc($value, $context) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('fb_str')) {
    /** Normalise any scalar to the string used when comparing saved values with option values. */
    function fb_str($value): string
    {
        if ($value === true) {
            return '1';
        }
        if ($value === false || $value === null) {
            return $value === false ? '0' : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}

if (! function_exists('fb_attrs')) {
    /**
     * Turn ['id'=>'x','required'=>true,'disabled'=>false,'class'=>['a','b']] into ` id="x" required class="a b"`.
     * null/false are skipped, true prints the bare attribute, int keys print bare attributes.
     */
    function fb_attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            if (is_int($key)) {
                $out .= ' ' . fb_esc($value, 'attr');
                continue;
            }
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
            $out .= ' ' . $key . '="' . fb_esc($value, 'attr') . '"';
        }

        return $out;
    }
}

if (! function_exists('fb_humanize')) {
    function fb_humanize(string $name): string
    {
        return ucwords(trim(str_replace(['_', '-', '.'], ' ', preg_replace('/\[\]$/', '', $name))));
    }
}

if (! function_exists('fb_is_input')) {
    /** True for real inputs; false for heading / divider / html items. */
    function fb_is_input(array $field): bool
    {
        return ! in_array($field['type'] ?? 'text', ['heading', 'divider', 'html'], true);
    }
}

if (! function_exists('fb_options')) {
    /** Normalise options to [['value'=>string,'label'=>string,'disabled'=>bool,'attrs'=>array], ...]. */
    function fb_options($options): array
    {
        $out = [];
        foreach ((array) $options as $key => $opt) {
            if (is_array($opt)) {
                $value = $opt['value'] ?? $key;
                $out[] = [
                    'value'    => fb_str($value),
                    'label'    => (string) ($opt['label'] ?? $value),
                    'disabled' => ! empty($opt['disabled']),
                    'attrs'    => (array) ($opt['attrs'] ?? []),
                ];
            } else {
                $out[] = ['value' => (string) $key, 'label' => (string) $opt, 'disabled' => false, 'attrs' => []];
            }
        }

        return $out;
    }
}

if (! function_exists('fb_values_array')) {
    /** Saved value -> list of strings (arrays, JSON arrays, comma lists and scalars are all accepted). */
    function fb_values_array($value, bool $multiple = true): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_map('fb_str', array_values($value));
        }
        if (is_string($value) && $multiple) {
            $trim = trim($value);
            if ($trim !== '' && $trim[0] === '[') {
                $json = json_decode($trim, true);
                if (is_array($json)) {
                    return array_map('fb_str', array_values($json));
                }
            }

            return array_map('trim', explode(',', $value));
        }

        return [fb_str($value)];
    }
}

if (! function_exists('fb_input_value')) {
    /** Saved value formatted for the HTML control type (e.g. DATETIME "2026-10-30 14:30:00" -> datetime-local). */
    function fb_input_value(array $f): string
    {
        $v = $f['value'] ?? null;
        if ($v === null || is_array($v) || is_object($v) || $f['type'] === 'password') {
            return '';
        }
        $v = fb_str($v);
        $m = [];
        switch ($f['type']) {
            case 'date':
                return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : $v;
            case 'datetime-local':
                return preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $v, $m) ? $m[1] . 'T' . $m[2] : $v;
            case 'month':
                return preg_match('/^\d{4}-\d{2}/', $v) ? substr($v, 0, 7) : $v;
            case 'time':
                return preg_match('/(\d{2}:\d{2}(?::\d{2})?)/', $v, $m) ? $m[1] : $v;
        }

        return $v;
    }
}

// ---------------------------------------------------------------------------------------------
// 1. Validation rules (CI4 rule strings -> client rules / server rules)
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_rule_pairs')) {
    /** 'required|min_length[3]' or ['required','min_length'=>3] -> [['required',true],['min_length','3']] */
    function fb_rule_pairs($rules): array
    {
        if (is_string($rules)) {
            $rules = array_filter(array_map('trim', explode('|', $rules)), 'strlen');
        }
        $pairs = [];
        foreach ((array) $rules as $key => $rule) {
            if (is_string($key)) {
                $pairs[] = [$key, $rule];
            } elseif (is_string($rule) && preg_match('/^([A-Za-z_]+)(?:\[(.*)\])?$/s', $rule, $m)) {
                $pairs[] = [$m[1], $m[2] ?? true];
            }
        }

        return $pairs;
    }
}

if (! function_exists('fb_rule_strings')) {
    /** Rules -> list of CI4 rule strings: ['required','min_length[3]']. */
    function fb_rule_strings($rules): array
    {
        $out = [];
        foreach (fb_rule_pairs($rules) as [$name, $param]) {
            if ($param === true) {
                $out[] = $name;
            } else {
                $out[] = $name . '[' . (is_array($param) ? implode(',', $param) : $param) . ']';
            }
        }

        return $out;
    }
}

if (! function_exists('fb_parse_rules')) {
    /** CI4 rules -> normalised client-side rule map understood by the generated JavaScript. */
    function fb_parse_rules($rules): array
    {
        $o = [];
        foreach (fb_rule_pairs($rules) as [$name, $p]) {
            switch ($name) {
                case 'required':
                    $o['required'] = true;
                    break;
                case 'min_length':
                    $o['minlength'] = (int) $p;
                    break;
                case 'max_length':
                    $o['maxlength'] = (int) $p;
                    break;
                case 'exact_length':
                    $o['minlength'] = $o['maxlength'] = (int) $p;
                    break;
                case 'greater_than_equal_to':
                    $o['min'] = $p;
                    break;
                case 'less_than_equal_to':
                    $o['max'] = $p;
                    break;
                case 'greater_than':
                    $o['gt'] = $p;
                    break;
                case 'less_than':
                    $o['lt'] = $p;
                    break;
                case 'numeric':
                case 'integer':
                case 'decimal':
                case 'alpha':
                case 'alpha_numeric':
                case 'alpha_dash':
                    $o[$name] = true;
                    break;
                case 'is_natural':
                    $o['natural'] = true;
                    break;
                case 'is_natural_no_zero':
                    $o['natural_nz'] = true;
                    break;
                case 'valid_email':
                    $o['email'] = true;
                    break;
                case 'valid_url':
                case 'valid_url_strict':
                    $o['url'] = true;
                    break;
                case 'valid_date':
                    $o['date'] = true;
                    break;
                case 'in_list':
                    $o['in_list'] = is_array($p) ? array_map('strval', $p) : array_map('trim', explode(',', (string) $p));
                    break;
                case 'matches':
                    $o['matches'] = (string) $p;
                    break;
                case 'regex_match':
                    $m = [];
                    if (preg_match('#^(.)(.*)\1([a-z]*)$#s', (string) $p, $m)) {
                        $o['regex'] = ['source' => $m[2], 'flags' => preg_replace('/[^gimsuy]/', '', $m[3])];
                    }
                    break;
            }
        }

        return $o;
    }
}

if (! function_exists('fb_server_validation')) {
    /**
     * Build CI4 server-side validation from the SAME config, so client and server never drift apart.
     *
     *   $v = fb_server_validation($config);
     *   if (! $this->validate($v['rules'], $v['messages'])) { ... }
     */
    function fb_server_validation(array $form): array
    {
        $form     = fb_defaults($form);
        $rules    = [];
        $messages = [];
        $map      = [
            'required' => 'required', 'minlength' => 'min_length', 'maxlength' => 'max_length',
            'min' => 'greater_than_equal_to', 'max' => 'less_than_equal_to', 'email' => 'valid_email',
            'url' => 'valid_url', 'numeric' => 'numeric', 'integer' => 'integer', 'regex' => 'regex_match',
            'in_list' => 'in_list', 'matches' => 'matches',
        ];

        foreach (fb_fields($form, 'edit') as $f) {
            if (! fb_is_input($f) || $f['type'] === 'hidden' || ! empty($f['attrs']['disabled']) || ! empty($f['attrs']['readonly'])) {
                continue;
            }
            $parts = fb_rule_strings($f['rules'] ?? []);
            $has   = static function (string $n) use (&$parts): bool {
                foreach ($parts as $p) {
                    if (strpos($p, $n) === 0 && ($p === $n || $p[strlen($n)] === '[')) {
                        return true;
                    }
                }

                return false;
            };
            $c     = $f['rules_client'];
            $isNum = in_array($f['type'], ['number', 'range'], true);

            if (! empty($c['required']) && ! $has('required')) {
                array_unshift($parts, 'required');
            }
            if (isset($c['minlength']) && ! $has('min_length') && ! $f['multiple']) {
                $parts[] = 'min_length[' . $c['minlength'] . ']';
            }
            if (isset($c['maxlength']) && ! $has('max_length') && ! $f['multiple']) {
                $parts[] = 'max_length[' . $c['maxlength'] . ']';
            }
            if ($isNum) {
                if (isset($c['min']) && ! $has('greater_than_equal_to')) {
                    $parts[] = 'greater_than_equal_to[' . $c['min'] . ']';
                }
                if (isset($c['max']) && ! $has('less_than_equal_to')) {
                    $parts[] = 'less_than_equal_to[' . $c['max'] . ']';
                }
                if (! $has('numeric')) {
                    $parts[] = 'numeric';
                }
            }
            if (! empty($c['email']) && ! $has('valid_email')) {
                $parts[] = 'valid_email';
            }
            if (! empty($c['url']) && ! $has('valid_url')) {
                $parts[] = 'valid_url';
            }

            // Saved values differ from labels, so validate the *value* is one of the allowed option values.
            $values = array_column($f['options'], 'value');
            $okList = $values && ! array_filter($values, static fn ($v) => strpos($v, ',') !== false);
            if ($f['multiple']) {
                $rules[$f['name']] = empty($c['required']) ? 'permit_empty' : 'required';
                if ($okList) {
                    $rules[$f['name'] . '.*'] = 'in_list[' . implode(',', $values) . ']';
                }
            } else {
                if ($okList && in_array($f['type'], ['select', 'radio'], true) && ! $has('in_list')) {
                    $parts[] = 'in_list[' . implode(',', $values) . ']';
                }
                if (empty($c['required']) && $parts) {
                    array_unshift($parts, 'permit_empty');
                }
                if ($parts) {
                    $rules[$f['name']] = implode('|', $parts);
                }
            }

            foreach ((array) ($f['messages'] ?? []) as $rule => $text) {
                $messages[$f['name']][$map[$rule] ?? $rule] = $text;
            }
        }

        return ['rules' => $rules, 'messages' => $messages];
    }
}

// ---------------------------------------------------------------------------------------------
// 2. Config normalisation
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_csrf')) {
    function fb_csrf()
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

if (! function_exists('fb_defaults')) {
    /** Merge a controller config with defaults. Idempotent, so every helper can safely call it. */
    function fb_defaults(array $form): array
    {
        if (! empty($form['_fb_ready'])) {
            return $form;
        }

        $form = array_replace_recursive([
            'form_id'       => 'fb-form',
            'title'         => '',
            'subtitle'      => '',
            'mode'          => 'create',
            'id_field'      => 'id',
            'id'            => null,
            'method'        => 'POST',
            'delete_method' => 'POST',
            'editable'      => true,
            'deletable'     => null,
            'csrf'          => true,
            'id_prefix'     => '',
            'layout'        => ['label_col' => 4],
            'urls'          => ['submit' => '', 'delete' => '', 'edit' => '#', 'list' => '', 'after_save' => '', 'after_delete' => ''],
            'labels'        => ['reset' => 'Reset', 'save' => 'Save Change', 'edit' => 'Edit', 'delete' => 'Delete', 'cancel' => 'Cancel'],
            'messages'      => [
                'saved'         => 'Saved successfully.',
                'saveFailed'    => 'Could not save. Please try again.',
                'deleted'       => 'Deleted successfully.',
                'deleteFailed'  => 'Could not delete. Please try again.',
                'confirmDelete' => 'Are you sure you want to delete this record? This cannot be undone.',
                'fixErrors'     => 'Please correct the following:',
            ],
            'values'        => [],
            'fields'        => [],
        ], $form);

        $form['mode']      = in_array($form['mode'], ['create', 'edit', 'view'], true) ? $form['mode'] : 'create';
        $form['edit_mode'] = $form['mode'] === 'create' ? 'create' : 'edit';
        $form['method']    = strtoupper((string) $form['method']);
        $form['form_id']   = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $form['form_id']);
        $form['layout']['label_col'] = max(1, min(11, (int) $form['layout']['label_col']));
        if ($form['id'] === null) {
            $form['id'] = $form['values'][$form['id_field']] ?? '';
        }
        if ($form['csrf'] === true) {
            $form['csrf'] = fb_csrf();
        } elseif (! is_array($form['csrf'])) {
            $form['csrf'] = false;
        }
        if ($form['deletable'] === null) {
            $form['deletable'] = ! empty($form['urls']['delete']);
        }
        $form['_fb_ready'] = true;

        return $form;
    }
}

if (! function_exists('fb_field')) {
    /**
     * Normalise one field for a pane ('edit' = create/edit inputs, 'view' = read-only text).
     * Returns null when the field should not appear in that pane.
     */
    function fb_field(array $f, array $form, string $pane = 'edit'): ?array
    {
        $f['type'] = strtolower((string) ($f['type'] ?? 'text'));
        $mode      = $pane === 'view' ? 'view' : $form['edit_mode'];

        if (isset($f['show_in']) && ! in_array($mode, (array) $f['show_in'], true)) {
            return null;
        }
        if (isset($f['hide_in']) && in_array($mode, (array) $f['hide_in'], true)) {
            return null;
        }
        if (! fb_is_input($f)) {
            return $f;
        }

        $name = (string) ($f['name'] ?? '');
        if ($name === '' || ($pane === 'view' && $f['type'] === 'hidden')) {
            return null;
        }

        $type = $f['type'];
        $base = preg_replace('/\[\]$/', '', $name);

        $f['name']     = $base;
        $f['label']    = (string) ($f['label'] ?? fb_humanize($base));
        $f['id']       = (string) ($f['id'] ?? ($form['id_prefix'] . preg_replace('/[^A-Za-z0-9_\-]/', '_', $base)));
        $f['options']  = fb_options($f['options'] ?? []);
        $f['group']    = in_array($type, ['checkbox', 'radio'], true) && $f['options'] !== [];
        $f['single']   = in_array($type, ['checkbox', 'switch'], true) && $f['options'] === [];
        $f['multiple'] = ! empty($f['multiple']) || ($type === 'checkbox' && $f['group']);
        if (($type !== 'select' && $type !== 'checkbox') || $f['single']) {
            $f['multiple'] = false;
        }
        $f['input_name'] = $base . ($f['multiple'] ? '[]' : '');

        // Saved value (create mode falls back to 'default')
        $values = (array) $form['values'];
        if (array_key_exists($base, $values)) {
            $val = $values[$base];
        } else {
            $val = $form['mode'] === 'create' ? ($f['default'] ?? null) : null;
        }
        $f['value'] = $val;

        // Single checkbox / switch
        $f['checked_value'] = fb_str($f['checked_value'] ?? '1');
        $f['off_value']     = fb_str($f['off_value'] ?? '0');
        $f['checked']       = $f['single'] && ($val === true || fb_str($val) === $f['checked_value']);

        // Attributes
        $a = (array) ($f['attrs'] ?? []);
        foreach (['placeholder', 'min', 'max', 'step', 'minlength', 'maxlength', 'pattern', 'rows', 'cols', 'autocomplete', 'required', 'disabled', 'readonly'] as $k) {
            if (array_key_exists($k, $f) && ! array_key_exists($k, $a)) {
                $a[$k] = $f[$k];
            }
        }
        foreach (['readonly', 'disabled'] as $k) {
            if (in_array($mode, (array) ($f[$k . '_in'] ?? []), true)) {
                $a[$k] = true;
            }
        }

        // Rules <-> attributes (rules also become native attributes, attributes also become rules)
        $rules  = fb_parse_rules($f['rules'] ?? []);
        $isText = in_array($type, ['text', 'email', 'password', 'search', 'tel', 'url', 'textarea'], true);
        $isNum  = in_array($type, ['number', 'range'], true);
        if (! empty($a['required'])) {
            $rules['required'] = true;
        }
        if ($isText) {
            foreach (['minlength', 'maxlength'] as $k) {
                if (isset($rules[$k]) && ! isset($a[$k])) {
                    $a[$k] = $rules[$k];
                }
            }
        }
        if ($isNum) {
            foreach (['min', 'max'] as $k) {
                if (isset($rules[$k]) && ! isset($a[$k])) {
                    $a[$k] = $rules[$k];
                }
            }
        }
        if (! empty($rules['required']) && ! $f['group']) {
            $a['required'] = true;
        }
        if ($f['group']) {
            unset($a['required']); // "required" on a checkbox group would force ALL boxes; JS validates the group instead
        }
        foreach (['min', 'max', 'minlength', 'maxlength', 'step', 'pattern'] as $k) {
            if (isset($a[$k]) && $a[$k] !== '') {
                $rules[$k] = $a[$k];
            }
        }
        if ($type === 'email') {
            $rules['email'] = true;
        } elseif ($type === 'url') {
            $rules['url'] = true;
        } elseif ($type === 'number') {
            $rules['numeric'] = true;
        }

        $f['attrs']        = $a;
        $f['rules_client'] = $rules;

        return $f;
    }
}

if (! function_exists('fb_fields')) {
    /** All normalised items (inputs + headings/dividers/html) visible in a pane. */
    function fb_fields(array $form, string $pane = 'edit'): array
    {
        $form = fb_defaults($form);
        $out  = [];
        foreach ((array) $form['fields'] as $f) {
            $n = fb_field((array) $f, $form, $pane);
            if ($n !== null) {
                $out[] = $n;
            }
        }

        return $out;
    }
}

// ---------------------------------------------------------------------------------------------
// 3. Controls (edit/create mode)
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_control_attrs')) {
    function fb_control_attrs(array $f, string $baseClass): array
    {
        $a     = $f['attrs'];
        $extra = $a['class'] ?? '';
        unset($a['class']);
        $class = trim($baseClass . ' ' . (is_array($extra) ? implode(' ', $extra) : $extra));

        return ['id' => $f['id'], 'name' => $f['input_name'], 'class' => $class] + $a;
    }
}

if (! function_exists('fb_input')) {
    /** <input> for text, email, password, number, range, date, time, color, hidden, ... */
    function fb_input(array $f): string
    {
        $type  = $f['type'];
        $attrs = ['type' => $type] + fb_control_attrs($f, $type === 'range' ? 'form-range' : 'form-control');
        if ($type !== 'password') {
            $attrs['value'] = fb_input_value($f);
        }

        return '<input' . fb_attrs($attrs) . '>';
    }
}

if (! function_exists('fb_textarea')) {
    function fb_textarea(array $f): string
    {
        $attrs         = fb_control_attrs($f, 'form-control');
        $attrs['rows'] = $attrs['rows'] ?? 3;

        return '<textarea' . fb_attrs($attrs) . '>' . fb_esc(fb_input_value($f)) . '</textarea>';
    }
}

if (! function_exists('fb_select')) {
    function fb_select(array $f): string
    {
        $attrs = fb_control_attrs($f, 'form-select');
        $ph    = array_key_exists('placeholder', $attrs) ? $attrs['placeholder'] : 'Select...';
        unset($attrs['placeholder']);
        if ($f['multiple']) {
            $attrs['multiple'] = true;
        }
        $sel  = fb_values_array($f['value'], $f['multiple']);
        $html = '<select' . fb_attrs($attrs) . '>';
        if (! $f['multiple'] && $ph !== false && $ph !== null) {
            $html .= '<option value=""' . ($sel === [] ? ' selected' : '') . '>' . fb_esc($ph) . '</option>';
        }
        foreach ($f['options'] as $o) {
            $html .= '<option value="' . fb_esc($o['value'], 'attr') . '"'
                . (in_array($o['value'], $sel, true) ? ' selected' : '')
                . ($o['disabled'] ? ' disabled' : '') . '>' . fb_esc($o['label']) . '</option>';
        }

        return $html . '</select>';
    }
}

if (! function_exists('fb_choice_group')) {
    /** A group of radios or checkboxes. Submitted value = option value, shown text = option label. */
    function fb_choice_group(array $f): string
    {
        $sel  = fb_values_array($f['value'], true);
        $base = $f['attrs'];
        $cls  = $base['class'] ?? '';
        unset($base['class'], $base['id'], $base['name'], $base['type'], $base['value'], $base['placeholder']);
        $html = '';
        foreach ($f['options'] as $i => $o) {
            $id    = $f['id'] . '_' . $i;
            $attrs = [
                    'class' => trim('form-check-input ' . (is_array($cls) ? implode(' ', $cls) : $cls)),
                    'type'  => $f['type'],
                    'id'    => $id,
                    'name'  => $f['input_name'],
                    'value' => $o['value'],
                ] + $base;
            $attrs = array_merge($attrs, $o['attrs']);
            $attrs['checked'] = in_array($o['value'], $sel, true);
            if ($o['disabled']) {
                $attrs['disabled'] = true;
            }
            $html .= '<div class="form-check mb-2' . (! empty($f['inline']) ? ' form-check-inline' : '') . '">'
                . '<input' . fb_attrs($attrs) . '>'
                . '<label class="form-check-label" for="' . fb_esc($id, 'attr') . '">' . fb_esc($o['label']) . '</label></div>';
        }

        return $html;
    }
}

if (! function_exists('fb_single_check')) {
    /** One checkbox or a switch (boolean-like). Submits checked_value, or off_value when unchecked. */
    function fb_single_check(array $f): string
    {
        $base = $f['attrs'];
        $cls  = $base['class'] ?? '';
        unset($base['class'], $base['id'], $base['name'], $base['type'], $base['value'], $base['placeholder']);
        $attrs = [
                'class' => trim('form-check-input ' . (is_array($cls) ? implode(' ', $cls) : $cls)),
                'type'  => 'checkbox',
                'id'    => $f['id'],
                'name'  => $f['input_name'],
                'value' => $f['checked_value'],
            ] + $base;
        $attrs['checked'] = $f['checked'];
        $isSwitch = $f['type'] === 'switch';
        if ($isSwitch) {
            $attrs['role'] = 'switch';
        }

        return '<div class="form-check' . ($isSwitch ? ' form-switch mb-0' : ' mb-2') . '">'
            . '<input' . fb_attrs($attrs) . '>'
            . (! empty($f['text']) ? '<label class="form-check-label" for="' . fb_esc($f['id'], 'attr') . '">' . fb_esc($f['text']) . '</label>' : '')
            . '</div>';
    }
}

if (! function_exists('fb_control')) {
    /** Dispatch to the right control for the field type. */
    function fb_control(array $f): string
    {
        switch ($f['type']) {
            case 'textarea':
                return fb_textarea($f);
            case 'select':
                return fb_select($f);
            case 'checkbox':
            case 'radio':
                return $f['group'] ? fb_choice_group($f) : ($f['single'] ? fb_single_check($f) : fb_input($f));
            case 'switch':
                return fb_single_check($f);
            default:
                return fb_input($f);
        }
    }
}

if (! function_exists('fb_label')) {
    function fb_label(array $f, array $form): string
    {
        $form  = fb_defaults($form);
        $col   = $form['layout']['label_col'];
        $for   = $f['group'] ? $f['id'] . '_0' : $f['id'];
        $class = "col-sm-{$col} text-sm-end col-form-label" . (! empty($f['rules_client']['required']) ? ' required' : '');
        $html  = '<label for="' . fb_esc($for, 'attr') . '" class="' . $class . '">' . fb_esc($f['label']);

        if (! empty($f['badge'])) {
            $b     = is_array($f['badge']) ? $f['badge'] : ['text' => $f['badge']];
            $html .= ' <span class="badge ' . fb_esc($b['class'] ?? 'bg-primary', 'attr') . ' ms-1">' . fb_esc($b['text'] ?? '') . '</span>';
        }
        if (! empty($f['optional'])) {
            $html .= ' <span class="form-label-optional">(optional)</span>';
        }

        return $html . '</label>';
    }
}

if (! function_exists('fb_help')) {
    function fb_help(array $f): string
    {
        return ! empty($f['help']) ? '<div class="form-text">' . fb_esc($f['help']) . '</div>' : '';
    }
}

if (! function_exists('fb_error_slot')) {
    /** Placeholder the generated JS fills with validation messages. */
    function fb_error_slot(array $f): string
    {
        return '<div class="invalid-feedback" data-fb-error="' . fb_esc($f['name'], 'attr') . '"></div>';
    }
}

if (! function_exists('fb_edit_row')) {
    /** One full row (label + control + help + error slot) in create/edit mode. */
    function fb_edit_row(array $f, array $form): string
    {
        if ($f['type'] === 'hidden') {
            return fb_input($f);
        }
        $form    = fb_defaults($form);
        $col     = 12 - $form['layout']['label_col'];
        $padding = ($f['group'] || $f['single']) ? ' pt-2' : '';

        return '<div class="row mb-3' . (! empty($f['row_class']) ? ' ' . fb_esc($f['row_class'], 'attr') : '') . '" data-fb-row="' . fb_esc($f['name'], 'attr') . '">'
            . fb_label($f, $form)
            . '<div class="col-sm-' . $col . $padding . '">'
            . fb_control($f) . fb_error_slot($f) . fb_help($f)
            . '</div></div>';
    }
}

// ---------------------------------------------------------------------------------------------
// 4. View mode (read-only text; saved values are translated back to labels)
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_view_value')) {
    function fb_view_value(array $f): string
    {
        $v     = $f['value'];
        $empty = '<span class="text-muted">&mdash;</span>';

        if (($f['view'] ?? null) instanceof \Closure) {
            $text = (string) ($f['view'])($v, $f);

            return $text === '' ? $empty : fb_esc($text);
        }

        if ($f['single']) {
            $labels = (array) ($f['view_labels'] ?? []);
            $on     = $labels['on'] ?? 'Yes';
            $off    = $labels['off'] ?? 'No';

            return $f['checked']
                ? '<span class="badge bg-success">' . fb_esc($on) . '</span>'
                : '<span class="badge bg-secondary">' . fb_esc($off) . '</span>';
        }

        if ($f['options'] !== [] && in_array($f['type'], ['select', 'radio', 'checkbox'], true)) {
            $map = array_column($f['options'], 'label', 'value');
            $out = [];
            foreach (fb_values_array($v, $f['multiple']) as $one) {
                $out[] = fb_esc($map[$one] ?? $one);
            }

            return $out ? implode(', ', $out) : $empty;
        }

        $text = fb_input_value($f);
        if ($f['type'] === 'password') {
            return $text === '' && $v === null ? $empty : '&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;';
        }
        if ($text === '') {
            return $empty;
        }
        switch ($f['type']) {
            case 'color':
                $swatch = preg_match('/^#[0-9a-f]{3,8}$/i', $text) ? $text : 'transparent';

                return '<span class="d-inline-block border rounded align-middle me-2" style="width:1.25rem;height:1.25rem;background:' . $swatch . '"></span>' . fb_esc($text);
            case 'textarea':
                return nl2br(fb_esc($text));
            case 'email':
                return '<a href="mailto:' . fb_esc($text, 'attr') . '">' . fb_esc($text) . '</a>';
            case 'url':
                return preg_match('#^https?://#i', $text)
                    ? '<a href="' . fb_esc($text, 'attr') . '" target="_blank" rel="noopener">' . fb_esc($text) . '</a>'
                    : fb_esc($text);
        }

        return fb_esc($text);
    }
}

if (! function_exists('fb_view_row')) {
    function fb_view_row(array $f, array $form): string
    {
        $form = fb_defaults($form);
        $col  = $form['layout']['label_col'];

        return '<div class="row mb-3" data-fb-view="' . fb_esc($f['name'], 'attr') . '">'
            . '<div class="col-sm-' . $col . ' text-sm-end">' . fb_esc($f['label']) . '</div>'
            . '<div class="col-sm-' . (12 - $col) . '">' . fb_view_value($f) . '</div></div>';
    }
}

// ---------------------------------------------------------------------------------------------
// 5. Panes, buttons, card, script
// ---------------------------------------------------------------------------------------------

if (! function_exists('fb_rows')) {
    /** All rows of a pane ('edit' or 'view'), including headings / dividers / raw html. */
    function fb_rows(array $form, string $pane = 'edit'): string
    {
        $form = fb_defaults($form);
        $col  = $form['layout']['label_col'];
        $html = '';
        foreach (fb_fields($form, $pane) as $f) {
            switch ($f['type']) {
                case 'heading':
                    $html .= '<div class="row mb-2 mt-4"><div class="col-sm-' . (12 - $col) . ' offset-sm-' . $col . '"><h6 class="text-uppercase text-muted mb-0">'
                        . fb_esc($f['label'] ?? '') . '</h6></div></div>';
                    break;
                case 'divider':
                    $html .= '<hr/>';
                    break;
                case 'html':
                    $html .= (string) ($f['html'] ?? '');
                    break;
                default:
                    $html .= $pane === 'view' ? fb_view_row($f, $form) : fb_edit_row($f, $form);
            }
        }

        return $html;
    }
}

if (! function_exists('fb_buttons_edit')) {
    function fb_buttons_edit(array $form): string
    {
        $form = fb_defaults($form);
        $l    = $form['labels'];
        $html = '<hr /><div class="row"><div class="col-6">'
            . '<button type="reset" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-counterclockwise"></i> ' . fb_esc($l['reset']) . '</button>'
            . '</div><div class="col-6 text-end">';
        if ($form['mode'] === 'view') {
            $html .= '<button type="button" class="btn btn-outline-secondary me-2" data-fb-action="cancel">' . fb_esc($l['cancel']) . '</button>';
        }

        return $html . '<button type="submit" class="btn btn-primary"><i class="bi bi-floppy"></i> ' . fb_esc($l['save']) . '</button></div></div>';
    }
}

if (! function_exists('fb_buttons_view')) {
    function fb_buttons_view(array $form): string
    {
        $form = fb_defaults($form);
        $l    = $form['labels'];
        $html = '<hr /><div class="row"><div class="col-6">';
        if ($form['deletable']) {
            $html .= '<button type="button" class="btn btn-outline-danger btn-delete ms-2" data-fb-action="delete"><i class="bi bi-trash"></i> ' . fb_esc($l['delete']) . '</button>';
        }
        $html .= '</div><div class="col-6 text-end">';
        if ($form['editable']) {
            $html .= '<a class="btn btn-primary" href="' . fb_esc($form['urls']['edit'], 'attr') . '" data-fb-action="edit"><i class="bi bi-pencil-square"></i> ' . fb_esc($l['edit']) . '</a>';
        }

        return $html . '</div></div>';
    }
}

if (! function_exists('fb_header')) {
    function fb_header(array $form): string
    {
        $form = fb_defaults($form);

        return '<div class="card-header"><h5 class="card-title">' . fb_esc($form['title']) . '</h5>'
            . ($form['subtitle'] !== '' ? '<p class="card-subtitle">' . fb_esc($form['subtitle']) . '</p>' : '')
            . '</div>';
    }
}

if (! function_exists('fb_hidden_fields')) {
    /** PK hidden input (id="id" by default) + CSRF token. */
    function fb_hidden_fields(array $form): string
    {
        $form = fb_defaults($form);
        $html = '<input type="hidden" id="' . fb_esc($form['id_field'], 'attr') . '" name="' . fb_esc($form['id_field'], 'attr') . '" value="' . fb_esc($form['id'], 'attr') . '" />';
        if ($form['csrf']) {
            $html .= '<input type="hidden" data-fb-csrf name="' . fb_esc($form['csrf']['name'], 'attr') . '" value="' . fb_esc($form['csrf']['hash'], 'attr') . '" />';
        }

        return $html;
    }
}

if (! function_exists('fb_form_open')) {
    function fb_form_open(array $form): string
    {
        $form = fb_defaults($form);

        return '<form id="' . fb_esc($form['form_id'], 'attr') . '" method="post" action="' . fb_esc($form['urls']['submit'], 'attr')
            . '" novalidate data-fb-mode="' . $form['mode'] . '">';
    }
}

if (! function_exists('fb_render')) {
    /**
     * MAIN FUNCTION. Renders the whole card (header + form with edit pane and/or view pane).
     *
     *   create : edit pane only
     *   edit   : edit pane only
     *   view   : view pane visible + edit pane hidden (the Edit button swaps them without reload)
     */
    function fb_render(array $form): string
    {
        $form = fb_defaults($form);
        $html = '<div class="card" id="' . fb_esc($form['form_id'], 'attr') . '-card">' . fb_header($form)
            . '<div class="card-body pt-0">' . fb_form_open($form) . fb_hidden_fields($form);

        $showEdit = $form['mode'] !== 'view' || $form['editable'];
        if ($showEdit) {
            $html .= '<div data-fb-pane="edit"' . ($form['mode'] === 'view' ? ' class="d-none"' : '') . '>'
                . fb_rows($form, 'edit') . fb_buttons_edit($form) . '</div>';
        }
        if ($form['mode'] === 'view') {
            $html .= '<div data-fb-pane="view">' . fb_rows($form, 'view') . fb_buttons_view($form) . '</div>';
        }

        return $html . '</form></div></div>';
    }
}

if (! function_exists('fb_script')) {
    /**
     * Generates the <script> that powers the form: live validation, submit (fetch + toastr),
     * view/edit switching, delete confirmation, reset. Needs toastr (falls back to alert()).
     * Echo it after fb_render(): <?= fb_script($formConfig) ?>
     */
    function fb_script(array $form): string
    {
        $form   = fb_defaults($form);
        $fields = [];
        foreach (fb_fields($form, 'edit') as $f) {
            if (! fb_is_input($f) || $f['type'] === 'hidden') {
                continue;
            }
            $fields[] = [
                'name'         => $f['name'],
                'inputName'    => $f['input_name'],
                'type'         => $f['type'],
                'label'        => $f['label'],
                'group'        => $f['group'],
                'single'       => $f['single'],
                'multiple'     => $f['multiple'],
                'offValue'     => $f['off_value'],
                'optionValues' => array_column($f['options'], 'value'),
                'rules'        => (object) $f['rules_client'],
                'messages'     => (object) ($f['messages'] ?? []),
            ];
        }

        $cfg = [
            'formId'       => $form['form_id'],
            'mode'         => $form['mode'],
            'id'           => $form['id'],
            'idField'      => $form['id_field'],
            'method'       => $form['method'],
            'deleteMethod' => strtoupper($form['delete_method']),
            'urls'         => [
                'submit'      => $form['urls']['submit'],
                'delete'      => $form['urls']['delete'],
                'list'        => $form['urls']['list'],
                'afterSave'   => $form['urls']['after_save'],
                'afterDelete' => $form['urls']['after_delete'],
            ],
            'csrf'         => $form['csrf'] ? ['name' => $form['csrf']['name'], 'header' => $form['csrf']['header']] : null,
            'messages'     => $form['messages'],
            'fields'       => $fields,
        ];

        $json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $js = <<<'JS'
(function (cfg) {
  'use strict';

  var DATE_TYPES = ['date', 'datetime-local', 'time', 'month', 'week'];
  var DEFAULT_MESSAGES = {
    required: '{label} is required.',
    minlength: '{label} must be at least {param} characters.',
    maxlength: '{label} cannot exceed {param} characters.',
    min: '{label} must be at least {param}.',
    max: '{label} must be at most {param}.',
    gt: '{label} must be greater than {param}.',
    lt: '{label} must be less than {param}.',
    step: '{label} must be a multiple of {param}.',
    numeric: '{label} must be a number.',
    integer: '{label} must be a whole number.',
    decimal: '{label} must be a decimal number.',
    natural: '{label} must be a non-negative whole number.',
    natural_nz: '{label} must be a positive whole number.',
    email: '{label} must be a valid email address.',
    url: '{label} must be a valid URL.',
    date: '{label} must be a valid date.',
    alpha: '{label} may only contain letters.',
    alpha_numeric: '{label} may only contain letters and numbers.',
    alpha_dash: '{label} may only contain letters, numbers, dashes and underscores.',
    pattern: '{label} format is invalid.',
    regex: '{label} format is invalid.',
    in_list: '{label} has an invalid selection.',
    matches: '{label} does not match {param}.'
  };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Message must already be HTML-escaped by the caller.
  function notify(type, html, title) {
    if (window.toastr && typeof window.toastr[type] === 'function') {
      try {
        window.toastr[type](html, title || '', { timeOut: 5000, extendedTimeOut: 2000, closeButton: true, progressBar: true, escapeHtml: false });
        return;
      } catch (e) {
        if (window.console) { console.warn('toastr failed (is jQuery loaded before toastr?)', e); }
      }
    }
    if (type === 'error') {
      window.alert((title ? title + '\n' : '') + String(html).replace(/<br\s*\/?>/g, '\n'));
    } else if (window.console) {
      console.log('[' + type + '] ' + html);
    }
  }

  function init() {
    var form = document.getElementById(cfg.formId);
    if (!form) { return; }

    var fields = {};
    cfg.fields.forEach(function (f) { fields[f.name] = f; });
    var busy = false;
    var mode = cfg.mode === 'view' ? 'view' : 'edit';

    function els(f) {
      return Array.prototype.slice.call(form.querySelectorAll('[name="' + CSS.escape(f.inputName) + '"]'));
    }

    function fieldOf(target) {
      if (!target || !target.name) { return null; }
      return fields[target.name.replace(/\[\]$/, '')] || null;
    }

    // ---- reading values ----------------------------------------------------------------
    function getValue(f) {
      var list = els(f);
      if (!list.length) { return ''; }
      if (f.single) { return list[0].checked ? list[0].value : ''; }
      if (f.type === 'radio') {
        var c = list.filter(function (e) { return e.checked; })[0];
        return c ? c.value : '';
      }
      if (f.group) {
        return list.filter(function (e) { return e.checked; }).map(function (e) { return e.value; });
      }
      if (f.type === 'select' && f.multiple) {
        return Array.prototype.slice.call(list[0].selectedOptions).map(function (o) { return o.value; });
      }
      return list[0].value;
    }

    // ---- validation --------------------------------------------------------------------
    function msg(f, rule, param) {
      var t = (f.messages && f.messages[rule]) || DEFAULT_MESSAGES[rule] || '{label} is invalid.';
      return t.replace(/\{label\}/g, function () { return f.label; })
              .replace(/\{param\}/g, function () { return param === undefined ? '' : param; });
    }

    function validateField(f) {
      var list = els(f);
      if (!list.length || list[0].disabled) { return ''; }

      var r = f.rules || {};
      var v = getValue(f);
      var multi = Array.isArray(v);
      var empty = multi ? v.length === 0 : String(v).trim() === '';

      if (r.required && empty) { return msg(f, 'required'); }
      if (empty) { return ''; }

      // The submitted value must be one of the configured option values (labels are display-only).
      var items = multi ? v : [v];
      if (f.optionValues && f.optionValues.length && items.some(function (x) { return f.optionValues.indexOf(x) === -1; })) {
        return msg(f, 'in_list');
      }
      if (multi || f.single) { return ''; }

      var s = String(v).trim();
      var n = Number(s);
      var isDate = DATE_TYPES.indexOf(f.type) > -1;

      if (r.minlength !== undefined && s.length < Number(r.minlength)) { return msg(f, 'minlength', r.minlength); }
      if (r.maxlength !== undefined && s.length > Number(r.maxlength)) { return msg(f, 'maxlength', r.maxlength); }
      if (r.numeric && isNaN(n)) { return msg(f, 'numeric'); }
      if (r.integer && !/^[-+]?\d+$/.test(s)) { return msg(f, 'integer'); }
      if (r.decimal && !/^[-+]?\d*\.\d+$/.test(s)) { return msg(f, 'decimal'); }
      if (r.natural && !/^\d+$/.test(s)) { return msg(f, 'natural'); }
      if (r.natural_nz && (!/^\d+$/.test(s) || Number(s) === 0)) { return msg(f, 'natural_nz'); }

      if (r.min !== undefined && r.min !== '') {
        if (isDate ? s < String(r.min) : (isNaN(n) || n < Number(r.min))) { return msg(f, 'min', r.min); }
      }
      if (r.max !== undefined && r.max !== '') {
        if (isDate ? s > String(r.max) : (isNaN(n) || n > Number(r.max))) { return msg(f, 'max', r.max); }
      }
      if (r.gt !== undefined && (isNaN(n) || n <= Number(r.gt))) { return msg(f, 'gt', r.gt); }
      if (r.lt !== undefined && (isNaN(n) || n >= Number(r.lt))) { return msg(f, 'lt', r.lt); }

      if (r.step && r.step !== 'any' && (f.type === 'number' || f.type === 'range') && !isNaN(n)) {
        var st = Number(r.step);
        var base = (r.min !== undefined && r.min !== '') ? Number(r.min) : 0;
        var q = (n - base) / st;
        if (st > 0 && Math.abs(q - Math.round(q)) > 1e-7) { return msg(f, 'step', r.step); }
      }

      if (r.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)) { return msg(f, 'email'); }
      if (r.url) { try { new URL(s); } catch (e) { return msg(f, 'url'); } }
      if (r.date && isNaN(Date.parse(s))) { return msg(f, 'date'); }
      if (r.alpha && !/^[A-Za-z]+$/.test(s)) { return msg(f, 'alpha'); }
      if (r.alpha_numeric && !/^[A-Za-z0-9]+$/.test(s)) { return msg(f, 'alpha_numeric'); }
      if (r.alpha_dash && !/^[A-Za-z0-9_-]+$/.test(s)) { return msg(f, 'alpha_dash'); }
      if (r.pattern) { try { if (!new RegExp('^(?:' + r.pattern + ')$').test(s)) { return msg(f, 'pattern'); } } catch (e) {} }
      if (r.regex) { try { if (!new RegExp(r.regex.source, r.regex.flags).test(s)) { return msg(f, 'regex'); } } catch (e) {} }
      if (r.in_list && r.in_list.indexOf(s) === -1) { return msg(f, 'in_list'); }
      if (r.matches) {
        var other = fields[r.matches];
        if (other && getValue(other) !== v) { return msg(f, 'matches', other.label); }
      }
      return '';
    }

    function showError(f, text) {
      els(f).forEach(function (e) { e.classList.toggle('is-invalid', !!text); });
      var slot = form.querySelector('[data-fb-error="' + CSS.escape(f.name) + '"]');
      if (slot) {
        slot.textContent = text || '';
        slot.style.display = text ? 'block' : '';
      }
    }

    function clearErrors() {
      cfg.fields.forEach(function (f) { showError(f, ''); });
    }

    function validateAll() {
      var errors = [];
      cfg.fields.forEach(function (f) {
        var m = validateField(f);
        showError(f, m);
        if (m) { errors.push({ field: f, message: m }); }
      });
      return errors;
    }

    // ---- requests ----------------------------------------------------------------------
    function csrfInput() { return form.querySelector('[data-fb-csrf]'); }

    function updateCsrf(hash) {
      var el = csrfInput();
      if (el && hash) { el.value = hash; }
    }

    async function send(url, body) {
      var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
      if (cfg.csrf && csrfInput()) { headers[cfg.csrf.header] = csrfInput().value; }
      var res = await fetch(url, { method: 'POST', body: body, headers: headers, credentials: 'same-origin' });
      var data = {};
      try { data = await res.json(); } catch (e) { data = {}; }
      if (data && data.csrf_hash) { updateCsrf(data.csrf_hash); }
      else if (cfg.csrf) { updateCsrf(res.headers.get(cfg.csrf.header)); }
      return { ok: res.ok && data.success !== false, status: res.status, data: data || {} };
    }

    function setBusy(on) {
      busy = on;
      form.querySelectorAll('[type="submit"], [data-fb-action]').forEach(function (b) {
        if (on) { b.setAttribute('disabled', 'disabled'); b.classList.add('disabled'); }
        else { b.removeAttribute('disabled'); b.classList.remove('disabled'); }
      });
    }

    function applyServerErrors(errors) {
      var out = [];
      Object.keys(errors || {}).forEach(function (k) {
        out.push(errors[k]);
        var f = fields[k.replace(/\.\*$/, '')];
        if (f) { showError(f, errors[k]); }
      });
      return out;
    }

    // ---- mode switching ----------------------------------------------------------------
    function setMode(next) {
      var editPane = form.querySelector('[data-fb-pane="edit"]');
      var viewPane = form.querySelector('[data-fb-pane="view"]');
      mode = next;
      if (editPane) { editPane.classList.toggle('d-none', next !== 'edit'); }
      if (viewPane) { viewPane.classList.toggle('d-none', next === 'edit'); }
      form.setAttribute('data-fb-mode', next);
      if (next === 'edit' && editPane) {
        var first = editPane.querySelector('input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled]):not([readonly])');
        if (first) { first.focus(); }
      }
    }

    // ---- 1. validate on change ---------------------------------------------------------
    form.addEventListener('change', function (e) {
      var f = fieldOf(e.target);
      if (!f || mode !== 'edit') { return; }
      var m = validateField(f);
      showError(f, m);
      if (m) { notify('error', esc(m)); }
    });
    // clear the red state as soon as the user fixes the value (silent)
    form.addEventListener('input', function (e) {
      var f = fieldOf(e.target);
      if (f && e.target.classList.contains('is-invalid')) { showError(f, validateField(f)); }
    });

    // ---- 2. submit ---------------------------------------------------------------------
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (busy) { return; }

      var errors = validateAll();
      if (errors.length) {
        notify('error', errors.map(function (x) { return esc(x.message); }).join('<br>'), cfg.messages.fixErrors);
        var firstEl = els(errors[0].field)[0];
        if (firstEl && firstEl.focus) { firstEl.focus(); }
        return;
      }

      var body = new FormData(form);
      cfg.fields.forEach(function (f) {
        if (f.single) {
          var el = els(f)[0];
          if (el && !el.disabled && !el.checked) { body.set(f.inputName, f.offValue); }
        }
      });
      if (cfg.method !== 'POST') { body.set('_method', cfg.method); }

      setBusy(true);
      try {
        var r = await send(cfg.urls.submit || form.getAttribute('action') || window.location.href, body);
        if (r.ok) {
          notify('success', esc(r.data.message || cfg.messages.saved));
          form.dispatchEvent(new CustomEvent('fb:saved', { bubbles: true, detail: r.data }));
          var to = r.data.redirect || cfg.urls.afterSave;
          setTimeout(function () {
            if (to) { window.location.href = to; }
            else if (cfg.mode === 'create') { form.reset(); clearErrors(); }
            else { window.location.reload(); }
          }, 800);
        } else {
          var list = r.data.errors ? applyServerErrors(r.data.errors) : [];
          if (!list.length) { list = [r.data.message || cfg.messages.saveFailed]; }
          notify('error', list.map(esc).join('<br>'), r.data.errors ? cfg.messages.fixErrors : '');
        }
      } catch (err) {
        notify('error', esc(cfg.messages.saveFailed));
      } finally {
        setBusy(false);
      }
    });

    // ---- 3. delete ---------------------------------------------------------------------
    async function confirmDelete() {
      if (window.Swal && typeof window.Swal.fire === 'function') {
        var res = await window.Swal.fire({
          title: cfg.messages.confirmDelete, icon: 'warning', showCancelButton: true,
          confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'
        });
        return !!res.isConfirmed;
      }
      return window.confirm(cfg.messages.confirmDelete);
    }

    async function doDelete() {
      if (busy || !cfg.urls.delete || !(await confirmDelete())) { return; }
      var body = new FormData();
      body.append(cfg.idField, cfg.id);
      if (cfg.csrf && csrfInput()) { body.append(cfg.csrf.name, csrfInput().value); }
      if (cfg.deleteMethod !== 'POST') { body.append('_method', cfg.deleteMethod); }
      setBusy(true);
      try {
        var r = await send(cfg.urls.delete, body);
        if (r.ok) {
          notify('success', esc(r.data.message || cfg.messages.deleted));
          form.dispatchEvent(new CustomEvent('fb:deleted', { bubbles: true, detail: r.data }));
          var to = r.data.redirect || cfg.urls.afterDelete || cfg.urls.list;
          setTimeout(function () { if (to) { window.location.href = to; } else { window.history.back(); } }, 800);
        } else {
          notify('error', esc(r.data.message || cfg.messages.deleteFailed));
        }
      } catch (err) {
        notify('error', esc(cfg.messages.deleteFailed));
      } finally {
        setBusy(false);
      }
    }

    // ---- 4. edit / cancel / delete buttons ---------------------------------------------
    form.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-fb-action]');
      if (!btn || !form.contains(btn)) { return; }
      var action = btn.getAttribute('data-fb-action');
      e.preventDefault();
      if (action === 'edit') { setMode('edit'); }
      else if (action === 'cancel') { form.reset(); clearErrors(); setMode('view'); }
      else if (action === 'delete') { doDelete(); }
    });

    // ---- 5. reset: restore the original values (native reset uses the rendered defaults) ----
    form.addEventListener('reset', function () { setTimeout(clearErrors, 0); });

    // Public handle, e.g. window.FormBuilder['fb-form'].validateAll()
    window.FormBuilder = window.FormBuilder || {};
    window.FormBuilder[cfg.formId] = { form: form, setMode: setMode, validateField: validateField, validateAll: validateAll };
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})(__FB_CONFIG__);
JS;

        return '<script>' . "\n" . str_replace('__FB_CONFIG__', $json, $js) . "\n" . '</script>';
    }
}