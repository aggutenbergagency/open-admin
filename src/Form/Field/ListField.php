<?php

namespace OpenAdmin\Admin\Form\Field;

use Illuminate\Support\Arr;
use OpenAdmin\Admin\Admin;
use OpenAdmin\Admin\Form\Field;
use OpenAdmin\Admin\Form\Field\Traits\Sortable;

class ListField extends Field
{
    use Sortable;
    /**
     * @var array
     */
    protected $value = [''];

    /**
     * Fill data to the field.
     *
     * @param array $data
     *
     * @return void
     */
    public function fill($data)
    {
        $this->data = $data;

        $this->value = Arr::get($data, $this->column, $this->value);
        if (!is_array($this->value)) {
            $this->value = json_decode($this->value);
        }
        if (empty($this->value)) {
            $this->value = [''];
        }

        $this->formatValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator(array $input)
    {
        if ($this->validator) {
            return $this->validator->call($this, $input);
        }

        if (!is_string($this->column)) {
            return false;
        }

        $rules = $attributes = [];

        if (!$fieldRules = $this->getRules()) {
            return false;
        }

        if (!Arr::has($input, $this->column)) {
            return false;
        }

        $rules["{$this->column}.*"]      = $fieldRules;
        $attributes["{$this->column}.*"] = __('Value');

        $rules["{$this->column}"][] = 'array';

        $attributes["{$this->column}"] = $this->label;

        return validator($input, $rules, $this->getValidationMessages(), $attributes);
    }

    /**
     * {@inheritdoc}
     */
    protected function setupScript()
    {
        $this->script = <<<JS
(function() {
    if (typeof updateListInputNames !== 'function') {
        const tabPanes = document.querySelectorAll('.tab-pane[id^="translations_"]');
        tabPanes.forEach(tabPane => {
            const column = '{$this->column}';
            const addBtn = tabPane.querySelector('.' + column + '-add');
            const listTable = tabPane.querySelector('tbody.list-' + column + '-table');

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    var tpl = document.querySelector('template.' + column + '-tpl').innerHTML;
                    var clone = htmlToElement(tpl);
                    listTable.appendChild(clone);
                });
            }

            if (listTable) {
                listTable.addEventListener('click', function (event) {
                    if (event.target.classList.contains(column + '-remove')){
                        event.target.closest('tr').remove();
                    }
                });
            }
        });

        // Function to update input names for all tabs
        function updateListInputNames(column) {
            const tabPanes = document.querySelectorAll('.tab-pane[id^="translations_"]');
            tabPanes.forEach(tabPane => {
                if (tabPane && typeof tabPane.querySelectorAll === 'function') {
                    const locale = tabPane.id.split('_')[1]; // Extract locale from tab id
                    const inputs = tabPane.querySelectorAll('tbody.list-' + column + '-table input');
                    inputs.forEach(input => {
                        input.name = 'translations[' + locale + '][' + column + '][]';
                    });
                }
            });
        }

        // Call updateListInputNames on tab change
        const tabLinks = document.querySelectorAll('.nav-link');
        tabLinks.forEach(tabLink => {
            tabLink.addEventListener('shown.bs.tab', function () {
                const tabPaneId = this.getAttribute('href').substring(1);
                const tabPane = document.getElementById(tabPaneId);
                if (tabPane) {
                    updateListInputNames('{$this->column}');
                }
            });
        });

        // Initial call to set input names on page load
        const firstTabPane = document.querySelector('.tab-pane[id^="translations_"]');
        if (firstTabPane) {
            updateListInputNames('{$this->column}');
        }
    }
})();
JS;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($value)
    {
        $value = (array) parent::prepare($value);

        $values = array_values($value);
        if (count($values) == 1 && empty($values[0])) {
            return [];
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        $this->addSortable('tbody.list-', '-table');
        view()->share('options', $this->options);

        $this->setupScript();

        Admin::style('td .form-group {margin-bottom: 0 !important;}');

        return parent::render();
    }
}
