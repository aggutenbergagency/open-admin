<?php

namespace OpenAdmin\Admin\Form\Field\Traits;

use OpenAdmin\Admin\Admin;

trait Sortable
{
    /**
     * Set sortable.
     *
     * @return $this
     */
    public function sortable($set = true)
    {
        $this->options['sortable'] = $set;

        return $this;
    }

    public function addSortable($pref = '', $suf = '')
    {
        if (!empty($this->options['sortable'])) {
            $selector = $this->column_class ?? $this->column;
            $selector = $pref.$selector.$suf;

            $onEnd = '';
            if (is_string($this->options['sortable'])) {
                $field = $this->options['sortable'];
                $onEnd = <<<JS
                    ,onEnd: function (evt) {
                        document.querySelectorAll('{$selector} .{$field}').forEach((el, i) => {
                            el.value = i
                        })
                    },
                JS;
            }

            $script = <<<JS
                var sortable = new Sortable(document.querySelector('{$selector}'), {
                    animation:150,
                    handle: ".handle"
                    {$onEnd}

                });
            JS;
            Admin::script($script);
        }
    }
}
