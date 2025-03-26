<?php

namespace OpenAdmin\Admin\Form\Field;

use OpenAdmin\Admin\Form\Field;

class Divider extends Field
{
    protected $title;

    public function __construct($title = '')
    {
        $this->title = $title;
    }

    public function render()
    {
        if (empty($this->title)) {
            return '<hr>';
        }

        return <<<HTML
<div style="height: 2.4rem; border-bottom: 1px solid rgba(0,0,0,.05); text-align: left;margin-top: 20px;margin-bottom: 20px;">
  <h4 style="font-size:1.2rem;padding-left:1rem;padding-bototm:0.5rem;">
    {$this->title}
      </h4>
</div>
HTML;
    }
}
