<?php

namespace OpenAdmin\Admin\Grid\Column;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use OpenAdmin\Admin\Grid\Column;
use OpenAdmin\Admin\Grid\Model;

trait HasHeader
{
    /**
     * @var Filter
     */
    public $filter;

    /**
     * @var bool|Sorter
     */
    public $sorter = false;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * Add contents to column header.
     *
     * @param string|Renderable|Htmlable $header
     *
     * @return $this
     */
    public function addHeader($header)
    {
        if ($header instanceof Filter) {
            $header->setParent($this);
            $this->filter = $header;
        }
        $this->headers[] = $header;

        return $this;
    }

    /**
     * Add a column sortable to column header.
     *
     * @param string $cast
     *
     * @return Column|string
     */
    protected function addSorter($set = true, $cast = null)
    {
        if ($set) {
            $sortName     = $this->grid->model()->getSortName();
            $this->sorter = new Sorter($sortName, $this->getName(), $cast);
        } else {
            $this->sorter = false;
        }

        return $this;
    }

    /**
     * Add a help tooltip to column header.
     *
     * @param string $message
     *
     * @return $this
     */
    protected function addHelp($message)
    {
        return $this->addHeader(new Help($message));
    }

    /**
     * Add a filter to column header.
     *
     * @return $this
     */
    protected function addFilter($type = null, $formal = null)
    {
        if (is_array($type)) {
            return $this->addHeader(new CheckFilter($type));
        }

        if (is_null($type)) {
            $type = 'equal';
        }

        if (in_array($type, ['equal', 'like', 'date', 'time', 'datetime'])) {
            return $this->addHeader(new InputFilter($type));
        }

        if ($type === 'range') {
            if (is_null($formal)) {
                $formal = 'equal';
            }

            return $this->addHeader(new RangeFilter($formal));
        }

        return $this;
    }

    /**
     * Add a binding based on filter to the model query.
     *
     * @param Model $model
     */
    public function bindFilterQuery(Model $model)
    {
        if ($this->filter) {
            $this->filter->addBinding(request($this->getName()), $model);
        }
    }

    /**
     * Render Column header.
     *
     * @return string
     */
    public function renderHeader()
    {
        if ($this->sorter) {
            if (empty($this->grid->sortColumnsRef[$this->name])) {
                $this->addHeader($this->sorter);
                $this->grid->sortColumnsRef[$this->name] = true;
            }
        }

        return collect($this->headers)->map(function ($item) {
            if ($item instanceof Renderable) {
                return $item->render();
            }

            if ($item instanceof Htmlable) {
                return $item->toHtml();
            }

            return (string) $item;
        })->implode('');
    }
}
