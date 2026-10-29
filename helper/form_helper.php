
<?php

if (! function_exists('form_builder')) {
    /**
     * Credit: ChatGPT
     *
     * Generate a Bootstrap 5 form from a field definition.
     *
     * @param string $title    Form title.
     * @param string $action   Form submission URL.
     * @param array  $fields   Field definitions.
     * @param string $mode     'edit' or 'view'.
     * @param array  $values   Existing field values, keyed by field name.
     * @param string $subtitle Optional form subtitle.
     *
     * @return string Rendered HTML.
     */
    function form_builder(
        string $title,
        string $action,
        array $fields,
        string $mode = 'edit',
        array $values = [],
        string $subtitle = ''
    ): string {
        if (! in_array($mode, ['edit', 'view'], true)) {
            throw new InvalidArgumentException(
                "Form mode must be 'edit' or 'view'."
            );
        }

        $isView = $mode === 'view';

        // Escape output for the appropriate HTML context.
        $e = static fn ($value, string $context = 'html'): string
        => esc((string) ($value ?? ''), $context);

        // Generate a safe HTML ID from the field name or ID.
        $makeId = static function (string $value): string {
            $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);
            $id = trim($id ?? '', '-_');

            return $id !== '' ? $id : 'field';
        };

        // Normalize a field's options into value/label pairs.
        $normalizeOptions = static function (array $options): array {
            $result = [];

            foreach ($options as $key => $option) {
                if (is_array($option)) {
                    if (! array_key_exists('value', $option)) {
                        continue;
                    }

                    $result[] = [
                        'value' => $option['value'],
                        'label' => $option['label']
                            ?? $option['value'],
                    ];
                } else {
                    // Also support ['eng' => 'Engineering'].
                    $result[] = [
                        'value' => $key,
                        'label' => $option,
                    ];
                }
            }

            return $result;
        };

        // Read an existing value, preserving arrays for checkboxes.
        $getValue = static function (
            string $name,
            array $values
        ) {
            return array_key_exists($name, $values)
                ? $values[$name]
                : '';
        };

        // Compare scalar values consistently.
        $sameValue = static function ($a, $b): bool {
            if (! is_scalar($a) || ! is_scalar($b)) {
                return false;
            }

            return (string) $a === (string) $b;
        };

        // Render a readable value in view mode.
        $displayValue = static function (
            $value,
            array $options = [],
            bool $multiple = false
        ) use ($e, $normalizeOptions, $sameValue): string {
            $selectedValues = is_array($value)
                ? $value
                : [$value];

            $labels = [];

            foreach ($normalizeOptions($options) as $option) {
                foreach ($selectedValues as $selected) {
                    if ($sameValue($selected, $option['value'])) {
                        $labels[] = (string) $option['label'];
                        break;
                    }
                }
            }

            if ($options !== []) {
                return $e(implode(', ', $labels));
            }

            if (is_array($value)) {
                return $e(implode(', ', array_map(
                    static fn ($item) => (string) $item,
                    $value
                )));
            }

            return $e($value);
        };

        $html = '';

        // Only edit mode creates a submitting form.
        if (! $isView) {
            $html .= '<form action="' . $e($action, 'attr')
                . '" method="post">';
        }

        $html .= '<div class="card mb-3">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="card-title">'
            . $e($title)
            . '</h5>';

        if ($subtitle !== '') {
            $html .= '<p class="card-subtitle mb-0">'
                . $e($subtitle)
                . '</p>';
        }

        $html .= '</div>';
        $html .= '<div class="card-body">';

        if (! $isView && function_exists('csrf_field')) {
            $html .= csrf_field();
        }

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $label = (string) ($field['label'] ?? $name);
            $type = strtolower((string) ($field['type'] ?? 'text'));
            $id = $makeId((string) ($field['id'] ?? $name));
            $value = $getValue($name, $values);
            $required = ! empty($field['required']);
            $help = (string) ($field['help'] ?? '');
            $prefix = $field['prefix'] ?? '';
            $suffix = $field['suffix'] ?? '';
            $options = $field['options'] ?? [];
            $options = is_array($options) ? $options : [];

            $requiredAttr = $required ? ' required' : '';
            $requiredClass = $required ? ' required' : '';

            // Use an allowlist so arbitrary input types cannot be injected.
            $allowedTypes = [
                'text', 'tel', 'email', 'password', 'number',
                'url', 'search', 'date', 'datetime-local',
                'time', 'month', 'week', 'range', 'color',
            ];

            if (! in_array(
                    $type,
                    [
                        'textarea', 'select', 'switch',
                        'radio', 'checkboxes',
                    ],
                    true
                ) && ! in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }

            $html .= '<div class="mb-3">';

            /*
             * Switch: Y/N.
             */
            if ($type === 'switch') {
                if ($isView) {
                    $checked = $sameValue($value, 'Y');

                    $html .= '<div class="form-check form-switch">';
                    $html .= '<input class="form-check-input"'
                        . ' type="checkbox" disabled'
                        . ($checked ? ' checked' : '')
                        . '>';
                    $html .= '<label class="form-check-label">'
                        . $e($label)
                        . '</label>';
                    $html .= '<div class="form-text">'
                        . ($checked ? 'Yes' : 'No')
                        . '</div>';
                    $html .= '</div>';
                } else {
                    $checked = $sameValue($value, 'Y');

                    $html .= '<div class="form-check form-switch">';
                    $html .= '<input type="hidden" name="'
                        . $e($name, 'attr')
                        . '" value="N">';

                    $html .= '<input class="form-check-input"'
                        . ' type="checkbox"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . ' value="Y"'
                        . ($checked ? ' checked' : '')
                        . $requiredAttr
                        . '>';

                    $html .= '<label class="form-check-label'
                        . $requiredClass
                        . '" for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';
                    $html .= '</div>';
                }

                /*
                 * Radio buttons and checkbox groups.
                 */
            } elseif (in_array($type, ['radio', 'checkboxes'], true)) {
                $isCheckboxes = $type === 'checkboxes';

                $selectedValues = is_array($value)
                    ? $value
                    : ($value !== '' ? [$value] : []);

                $html .= '<fieldset class="border-0 p-0 m-0">';

                $html .= '<legend class="form-label fs-6'
                    . $requiredClass . '">'
                    . $e($label)
                    . '</legend>';

                if ($isView) {
                    $html .= '<div class="form-control-plaintext">';

                    $html .= $displayValue(
                        $selectedValues,
                        $options,
                        $isCheckboxes
                    );

                    $html .= '</div>';
                } else {
                    // A fieldset ID prefix prevents collisions between options.
                    $optionIndex = 0;

                    foreach ($normalizeOptions($options) as $option) {
                        $optionId = $id . '-' . $optionIndex++;
                        $checked = false;

                        foreach ($selectedValues as $selected) {
                            if ($sameValue($selected, $option['value'])) {
                                $checked = true;
                                break;
                            }
                        }

                        $inputType = $isCheckboxes
                            ? 'checkbox'
                            : 'radio';

                        $inputName = $isCheckboxes
                            ? $name . '[]'
                            : $name;

                        $html .= '<div class="form-check mb-2">';

                        $html .= '<input class="form-check-input"'
                            . ' type="' . $inputType . '"'
                            . ' id="' . $e($optionId, 'attr') . '"'
                            . ' name="' . $e($inputName, 'attr') . '"'
                            . ' value="' . $e($option['value'], 'attr') . '"'
                            . ($checked ? ' checked' : '')
                            . ($required && $optionIndex === 1
                                ? ' required' : '')
                            . '>';

                        $html .= '<label class="form-check-label"'
                            . ' for="' . $e($optionId, 'attr') . '">'
                            . $e($option['label'])
                            . '</label>';

                        $html .= '</div>';
                    }
                }

                $html .= '</fieldset>';

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }

                /*
                 * Select dropdown.
                 */
            } elseif ($type === 'select') {
                $html .= '<div class="form-floating">';

                if ($isView) {
                    $html .= '<div class="form-control-plaintext">'
                        . $displayValue($value, $options)
                        . '</div>';
                    $html .= '<label for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';
                } else {
                    $html .= '<select class="form-select"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . $requiredAttr
                        . '>';

                    if (empty($field['required'])) {
                        $html .= '<option value="">Select '
                            . $e($label)
                            . '</option>';
                    }

                    foreach ($normalizeOptions($options) as $option) {
                        $selected = $sameValue(
                            $value,
                            $option['value']
                        );

                        $html .= '<option'
                            . ' value="' . $e($option['value'], 'attr') . '"'
                            . ($selected ? ' selected' : '')
                            . '>'
                            . $e($option['label'])
                            . '</option>';
                    }

                    $html .= '</select>';

                    $html .= '<label for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';
                }

                $html .= '</div>';

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }

                /*
                 * Textarea.
                 */
            } elseif ($type === 'textarea') {
                $height = max(60, (int) ($field['height'] ?? 120));

                if ($isView) {
                    $html .= '<label class="form-label"'
                        . ' for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';

                    $html .= '<div class="form-control-plaintext"'
                        . ' style="white-space: pre-wrap;">'
                        . $e($value)
                        . '</div>';
                } else {
                    $html .= '<div class="form-floating">';

                    $html .= '<textarea class="form-control"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . ' placeholder="' . $e($label, 'attr') . '"'
                        . ' style="height: ' . $height . 'px;"'
                        . $requiredAttr . '>'
                        . $e($value)
                        . '</textarea>';

                    $html .= '<label for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';

                    $html .= '</div>';
                }

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }

                /*
                 * Inputs with prefix/suffix.
                 */
            } elseif ($prefix !== '' || $suffix !== '') {
                $html .= '<label class="form-label'
                    . $requiredClass . '"'
                    . ' for="' . $e($id, 'attr') . '">'
                    . $e($label)
                    . '</label>';

                if ($isView) {
                    $html .= '<div class="form-control-plaintext">';

                    $html .= $e($prefix)
                        . $displayValue($value)
                        . $e($suffix);

                    $html .= '</div>';
                } else {
                    $html .= '<div class="input-group">';

                    if ($prefix !== '') {
                        $html .= '<span class="input-group-text">'
                            . $e($prefix)
                            . '</span>';
                    }

                    $html .= '<input class="form-control"'
                        . ' type="' . $e($type, 'attr') . '"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . ' placeholder="' . $e($label, 'attr') . '"'
                        . ' value="' . $e($value, 'attr') . '"'
                        . $requiredAttr;

                    foreach (['min', 'max', 'step', 'maxlength', 'minlength'] as $attr) {
                        if (isset($field[$attr])) {
                            $html .= ' ' . $attr . '="'
                                . $e($field[$attr], 'attr') . '"';
                        }
                    }

                    $html .= '>';

                    if ($suffix !== '') {
                        $html .= '<span class="input-group-text">'
                            . $e($suffix)
                            . '</span>';
                    }

                    $html .= '</div>';
                }

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }

                /*
                 * Range, color, and other non-floating inputs.
                 */
            } elseif (in_array($type, ['range', 'color'], true)) {
                $html .= '<label class="form-label'
                    . $requiredClass . '"'
                    . ' for="' . $e($id, 'attr') . '">'
                    . $e($label)
                    . '</label>';

                if ($isView) {
                    $html .= '<div class="form-control-plaintext">'
                        . $displayValue($value)
                        . '</div>';
                } else {
                    $html .= '<input class="form-control"'
                        . ' type="' . $e($type, 'attr') . '"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . ' value="' . $e($value, 'attr') . '"'
                        . $requiredAttr;

                    foreach (['min', 'max', 'step'] as $attr) {
                        if (isset($field[$attr])) {
                            $html .= ' ' . $attr . '="'
                                . $e($field[$attr], 'attr') . '"';
                        }
                    }

                    $html .= '>';
                }

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }

                /*
                 * Standard text-like inputs.
                 */
            } else {
                $html .= '<div class="form-floating">';

                if ($isView) {
                    // Never expose a stored password in view mode.
                    $display = $type === 'password' && $value !== ''
                        ? '••••••'
                        : $value;

                    $html .= '<div class="form-control-plaintext">'
                        . $e($display)
                        . '</div>';

                    $html .= '<label for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';
                } else {
                    $html .= '<input class="form-control"'
                        . ' type="' . $e($type, 'attr') . '"'
                        . ' id="' . $e($id, 'attr') . '"'
                        . ' name="' . $e($name, 'attr') . '"'
                        . ' placeholder="' . $e($label, 'attr') . '"'
                        . ' value="' . $e($value, 'attr') . '"'
                        . $requiredAttr;

                    foreach (['min', 'max', 'step', 'maxlength', 'minlength'] as $attr) {
                        if (isset($field[$attr])) {
                            $html .= ' ' . $attr . '="'
                                . $e($field[$attr], 'attr') . '"';
                        }
                    }

                    $html .= '>';

                    $html .= '<label class="'
                        . trim($requiredClass)
                        . '" for="' . $e($id, 'attr') . '">'
                        . $e($label)
                        . '</label>';
                }

                $html .= '</div>';

                if ($help !== '') {
                    $html .= '<div class="form-text">'
                        . $e($help)
                        . '</div>';
                }
            }

            $html .= '</div>';
        }

        if (! $isView) {
            $html .= '<div class="mb-3 text-end">';
            $html .= '<button type="submit"'
                . ' class="btn btn-outline-primary">Save</button>';
            $html .= '</div>';
        }

        $html .= '</div>'; // card-body
        $html .= '</div>'; // card

        if (! $isView) {
            $html .= '</form>';
        }

        return $html;
    }
}