<?php

/**
 * app/Helpers/bootstrap_form_helper.php
 *
 * Load with:  helper('bootstrap_form');
 * or globally in BaseController:  protected $helpers = ['bootstrap_form'];
 *
 * Main function: bs_field($type, $name, $label, $opts = [])
 *
 * Value priority:  old() input (after failed validation)  >  'value' (update mode)  >  'default' (insert mode)
 *
 * Options ($opts):
 *   id             string       Defaults to $name (sanitised)
 *   default        mixed        Value used in insert mode
 *   value          mixed        Existing value used in update mode
 *   choices        array        [value => text] for select / radio / checkbox groups
 *                               (select supports optgroups: ['Group' => [value => text]])
 *   placeholder    string       Defaults to the label (select: "-- label --")
 *   required       bool
 *   multiple       bool         <select multiple>
 *   inline         bool         Inline radios / checkboxes
 *   checkbox_value string       Value of a single checkbox (default '1')
 *   help           string       Small help text below the input
 *   error          string       Error message; auto-read from session('errors')[$name] if omitted
 *   attrs          array        Extra attributes, e.g. ['rows' => 5, 'min' => 0, 'data-x' => 'y']
 *   wrapper_class  string       Default 'mb-3'
 *   label_class    string       Default 'form-label'
 *   input_class    string       Overrides the default control class
 */

if (! function_exists('bs_attrs')) {
    /**
     * Turns ['name' => 'a', 'required' => true, 'disabled' => false] into ' name="a" required'
     */
    function bs_attrs(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $key => $val) {
            if ($val === null || $val === false) {
                continue;
            }
            $html .= $val === true
                ? ' ' . $key
                : ' ' . $key . '="' . esc((string) $val, 'attr') . '"';
        }

        return $html;
    }
}

if (! function_exists('bs_is_selected')) {
    function bs_is_selected($current, $value): bool
    {
        if ($current === null) {
            return false;
        }
        if (is_array($current)) {
            return in_array((string) $value, array_map('strval', $current), true);
        }

        return (string) $current === (string) $value;
    }
}

if (! function_exists('bs_options')) {
    /**
     * Renders <option> (and <optgroup>) tags.
     */
    function bs_options(array $choices, $current, string $indent = '        '): string
    {
        $html = '';
        foreach ($choices as $value => $text) {
            if (is_array($text)) {
                $html .= $indent . '<optgroup label="' . esc((string) $value, 'attr') . '">' . "\n"
                    . bs_options($text, $current, $indent . '    ')
                    . $indent . "</optgroup>\n";
                continue;
            }
            $html .= $indent . '<option value="' . esc((string) $value, 'attr') . '"'
                . (bs_is_selected($current, $value) ? ' selected' : '')
                . '>' . esc((string) $text) . "</option>\n";
        }

        return $html;
    }
}

if (! function_exists('bs_field')) {
    function bs_field(string $type, string $name, string $label, array $opts = []): string
    {
        $o = array_merge([
            'id'             => null,
            'default'        => null,
            'value'          => null,
            'choices'        => [],
            'placeholder'    => null,
            'required'       => false,
            'multiple'       => false,
            'inline'         => false,
            'checkbox_value' => '1',
            'help'           => null,
            'error'          => null,
            'attrs'          => [],
            'wrapper_class'  => 'mb-3',
            'label_class'    => 'form-label',
            'input_class'    => null,
        ], $opts);

        $type    = strtolower($type);
        $baseKey = rtrim($name, '[]');                       // "tags[]" => "tags"
        $id      = $o['id'] ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_');

        // ---- Current value: old input > existing value (update) > default (insert) ----
        $current = $o['value'] ?? $o['default'];
        if (function_exists('old') && ! in_array($type, ['password', 'file'], true)) {
            $old = old($baseKey, null, false);               // false = don't HTML-escape here, we escape on output
            if ($old !== null) {
                $current = $old;
            }
        }

        // ---- Validation error ----
        $error = $o['error'];
        if ($error === null) {
            $errors = session('errors');
            $error  = is_array($errors) ? ($errors[$baseKey] ?? null) : null;
        }
        $invalid = $error ? ' is-invalid' : '';

        // ---- Hidden inputs: no wrapper, no label ----
        if ($type === 'hidden') {
            return '<input' . bs_attrs(array_merge([
                    'type'  => 'hidden',
                    'name'  => $name,
                    'id'    => $id,
                    'value' => is_scalar($current) ? (string) $current : null,
                ], $o['attrs'])) . " />\n";
        }

        $placeholder = $o['placeholder'] ?? $label;
        $isGroup     = false;   // radio / checkbox
        $showLabel   = true;
        $labelFor    = $id;

        // =====================================================================
        // Build the control
        // =====================================================================
        switch ($type) {

            case 'textarea':
                $control = '<textarea' . bs_attrs(array_merge([
                        'name'        => $name,
                        'id'          => $id,
                        'class'       => trim(($o['input_class'] ?? 'form-control') . $invalid),
                        'placeholder' => $placeholder,
                        'required'    => $o['required'],
                        'rows'        => 3,
                    ], $o['attrs'])) . '>' . esc(is_scalar($current) ? (string) $current : '') . '</textarea>';
                break;

            case 'select':
                $selectName = ($o['multiple'] && ! str_ends_with($name, '[]')) ? $name . '[]' : $name;
                $control    = '<select' . bs_attrs(array_merge([
                        'name'     => $selectName,
                        'id'       => $id,
                        'class'    => trim(($o['input_class'] ?? 'form-select') . $invalid),
                        'required' => $o['required'],
                        'multiple' => $o['multiple'],
                    ], $o['attrs'])) . ">\n";

                if (! $o['multiple']) {
                    $emptyText = $o['placeholder'] ?? '-- ' . $label . ' --';
                    $control  .= '        <option value=""'
                        . (($current === null || $current === '') ? ' selected' : '')
                        . '>' . esc($emptyText) . "</option>\n";
                }
                $control .= bs_options($o['choices'], $current) . '    </select>';
                break;

            case 'radio':
            case 'checkbox':
                $isGroup = true;
                $single  = empty($o['choices']);             // one lone checkbox / switch
                $choices = $single ? [$o['checkbox_value'] => $label] : $o['choices'];

                $inputName = $name;
                if ($type === 'checkbox' && ! $single && count($choices) > 1 && ! str_ends_with($name, '[]')) {
                    $inputName .= '[]';
                }

                $control = '';
                foreach ($choices as $val => $text) {
                    $cid     = $single ? $id : $id . '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $val);
                    $checked = $single
                        ? ($current !== null && ! in_array($current, ['', 0, '0', false], true))
                        : bs_is_selected($current, $val);

                    $control .= '<div class="form-check' . ($o['inline'] ? ' form-check-inline' : '') . "\">\n"
                        . '    <input' . bs_attrs(array_merge([
                            'class'    => 'form-check-input' . $invalid,
                            'type'     => $type,
                            'name'     => $inputName,
                            'id'       => $cid,
                            'value'    => (string) $val,
                            'checked'  => $checked,
                            // "required" on every checkbox of a group would force all of them
                            'required' => $o['required'] && ($type === 'radio' || $single),
                        ], $o['attrs'])) . " />\n"
                        . '    <label for="' . esc($cid, 'attr') . '" class="form-check-label">' . esc((string) $text) . "</label>\n"
                        . "</div>\n";
                }
                $control = rtrim($control);

                // A lone checkbox already carries its own label
                $showLabel = ! $single;
                $labelFor  = null;                            // group label doesn't point at one input
                break;

            default: // text, email, password, number, date, tel, url, search, file, range, color, ...
                $class = $o['input_class'] ?? match ($type) {
                    'range' => 'form-range',
                    'color' => 'form-control form-control-color',
                    default => 'form-control',
                };

                $attrs = [
                    'type'     => $type,
                    'name'     => $name,
                    'id'       => $id,
                    'class'    => trim($class . $invalid),
                    'required' => $o['required'],
                ];
                if (! in_array($type, ['file', 'range', 'color'], true)) {
                    $attrs['placeholder'] = $placeholder;
                }
                if (! in_array($type, ['password', 'file'], true)) {   // never echo passwords back
                    $attrs['value'] = is_scalar($current) ? (string) $current : null;
                }

                $control = '<input' . bs_attrs(array_merge($attrs, $o['attrs'])) . ' />';
                break;
        }

        // =====================================================================
        // Assemble the wrapper
        // =====================================================================
        $pad = static fn (string $html): string => '    ' . str_replace("\n", "\n    ", $html);

        $out = '<div class="' . esc($o['wrapper_class'], 'attr') . "\">\n";

        if ($showLabel) {
            $out .= '    <label'
                . ($labelFor !== null ? ' for="' . esc($labelFor, 'attr') . '"' : '')
                . ' class="' . esc($o['label_class'], 'attr') . '">'
                . esc($label)
                . ($o['required'] ? ' <span class="text-danger">*</span>' : '')
                . "</label>\n";
        }

        $out .= $pad($control) . "\n";

        if ($error) {
            $out .= '    <div class="invalid-feedback' . ($isGroup ? ' d-block' : '') . '">'
                . esc((string) $error) . "</div>\n";
        }
        if ($o['help']) {
            $out .= '    <div class="form-text">' . esc((string) $o['help']) . "</div>\n";
        }

        return $out . "</div>\n";
    }
}

/**
 * ---------------------------------------------------------------------------
 * Form wrapper: bs_form($title, $fields, $opts = [])
 * ---------------------------------------------------------------------------
 *
 * $fields is a list where each item is either
 *   - an array  [type, name, label, opts]   (passed to bs_field), or
 *   - a string  of raw HTML                 (e.g. a pre-built bs_field() call, a <hr>, a row layout)
 *
 * Options ($opts):
 *   id            string  Form id; also prefixes the button ids (default 'form')
 *   action        string  Form action URL (omitted if empty)
 *   method        string  get | post | put | patch | delete (put/patch/delete are sent as POST + _method)
 *   enctype       string  Auto 'multipart/form-data' if a file field exists
 *   mode          string  'add' or 'edit' (default 'add')
 *   save_label    string  Default: 'Add' (add mode) / 'Save Changes' (edit mode)
 *   save_icon     string  Font Awesome classes. Default: 'fa-solid fa-plus' / 'fa-solid fa-floppy-disk'
 *   reset_label   string  Default 'Reset'
 *   reset_icon    string  Default 'fa-solid fa-rotate-left'
 *   delete_label  string  Default 'Delete'
 *   delete_icon   string  Default 'fa-solid fa-trash'
 *   show_delete   bool    Default: true in edit mode, false in add mode
 *   show_reset    bool    Default true
 *   hidden        array   [name => value] extra hidden inputs (e.g. the record id)
 *   attrs         array   Extra <form> attributes
 *   card_class    string  Default 'card shadow-sm'
 *
 * Button ids (for your JavaScript): {id}_delete, {id}_reset, {id}_save
 */
if (! function_exists('bs_form')) {
    function bs_form(string $title, array $fields, array $opts = []): string
    {
        $o = array_merge([
            'id'           => 'form',
            'action'       => '',
            'method'       => 'post',
            'enctype'      => null,
            'mode'         => 'add',
            'save_label'   => null,
            'save_icon'    => null,
            'reset_label'  => 'Reset',
            'reset_icon'   => 'fa-solid fa-rotate-left',
            'delete_label' => 'Delete',
            'delete_icon'  => 'fa-solid fa-trash',
            'show_delete'  => null,
            'show_reset'   => true,
            'hidden'       => [],
            'attrs'        => [],
            'card_class'   => 'card shadow-sm',
        ], $opts);

        $isEdit = strtolower($o['mode']) === 'edit';
        $id     = $o['id'];

        $saveLabel  = $o['save_label'] ?? ($isEdit ? 'Save Changes' : 'Add');
        $saveIcon   = $o['save_icon']  ?? ($isEdit ? 'fa-solid fa-floppy-disk' : 'fa-solid fa-plus');
        $showDelete = $o['show_delete'] ?? $isEdit;

        // ---- Method (spoof PUT / PATCH / DELETE through POST) ----
        $method  = strtolower($o['method']);
        $spoofed = null;
        if (! in_array($method, ['get', 'post'], true)) {
            $spoofed = strtoupper($method);
            $method  = 'post';
        }

        // ---- Fields + auto multipart detection ----
        $hasFile = false;
        $body    = '';
        foreach ($fields as $field) {
            if (is_string($field)) {
                $body .= $field;
                continue;
            }
            if (strtolower((string) $field[0]) === 'file') {
                $hasFile = true;
            }
            $body .= bs_field($field[0], $field[1], $field[2] ?? '', $field[3] ?? []);
        }

        $pad = static fn (string $html, int $n): string =>
            str_repeat('    ', $n) . str_replace("\n", "\n" . str_repeat('    ', $n), rtrim($html));

        // ---- <form> open tag ----
        $formAttrs = array_merge([
            'id'      => $id,
            'action'  => $o['action'] ?: null,
            'method'  => $method,
            'enctype' => $o['enctype'] ?? ($hasFile ? 'multipart/form-data' : null),
        ], $o['attrs']);

        $out = '<form' . bs_attrs($formAttrs) . ">\n";

        // ---- Hidden inputs: CSRF, method spoofing, user supplied ----
        $hidden = '';
        if ($method === 'post' && function_exists('csrf_field')) {
            $hidden .= csrf_field() . "\n";
        }
        if ($spoofed !== null) {
            $hidden .= '<input type="hidden" name="_method" value="' . esc($spoofed, 'attr') . "\" />\n";
        }
        foreach ($o['hidden'] as $hName => $hValue) {
            $hidden .= '<input' . bs_attrs(['type' => 'hidden', 'name' => $hName, 'value' => (string) $hValue]) . " />\n";
        }
        if ($hidden !== '') {
            $out .= $pad($hidden, 1) . "\n";
        }

        // ---- Card ----
        $out .= '    <div class="' . esc($o['card_class'], 'attr') . "\">\n";

        if ($title !== '') {
            $out .= '        <div class="card-header">' . "\n"
                . '            <h5 class="card-title mb-0">' . esc($title) . "</h5>\n"
                . "        </div>\n";
        }

        $out .= "        <div class=\"card-body\">\n" . $pad($body, 3) . "\n        </div>\n";

        // ---- Footer: delete (left) | reset + save (right) ----
        $btn = static fn (string $icon, string $label): string =>
            '<i class="' . esc($icon, 'attr') . ' me-1"></i> ' . esc($label);

        $out .= "        <div class=\"card-footer d-flex justify-content-between align-items-center\">\n";

        // Left: delete
        $out .= "            <div>\n";
        if ($showDelete) {
            $out .= '                <button type="button" id="' . esc($id, 'attr') . '_delete" class="btn btn-danger">'
                . $btn($o['delete_icon'], $o['delete_label']) . "</button>\n";
        }
        $out .= "            </div>\n";

        // Right: reset + save
        $out .= "            <div class=\"d-flex gap-2\">\n";
        if ($o['show_reset']) {
            $out .= '                <button type="reset" id="' . esc($id, 'attr') . '_reset" class="btn btn-outline-secondary">'
                . $btn($o['reset_icon'], $o['reset_label']) . "</button>\n";
        }
        $out .= '                <button type="submit" id="' . esc($id, 'attr') . '_save" class="btn btn-primary">'
            . $btn($saveIcon, $saveLabel) . "</button>\n";
        $out .= "            </div>\n";
        $out .= "        </div>\n";

        $out .= "    </div>\n";
        $out .= "</form>\n";

        return $out;
    }
}