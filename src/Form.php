<?php

namespace OpenAdmin\Admin;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use OpenAdmin\Admin\Exception\Handler;
use OpenAdmin\Admin\Form\Builder;
use OpenAdmin\Admin\Form\Concerns\HandleCascadeFields;
use OpenAdmin\Admin\Form\Concerns\HasFields;
use OpenAdmin\Admin\Form\Concerns\HasFormAttributes;
use OpenAdmin\Admin\Form\Concerns\HasFormFlags;
use OpenAdmin\Admin\Form\Concerns\HasHooks;
use OpenAdmin\Admin\Form\Field;
use OpenAdmin\Admin\Form\Field\HasMany;
use OpenAdmin\Admin\Form\Layout\Layout;
use OpenAdmin\Admin\Form\Row;
use OpenAdmin\Admin\Form\Tab;
use OpenAdmin\Admin\Grid\Tools\BatchEdit;
use OpenAdmin\Admin\Traits\ShouldSnakeAttributes;
use Spatie\EloquentSortable\Sortable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Form.
 */
class Form implements Renderable
{
    use HasHooks;
    use HasFields;
    use HasFormAttributes;
    use HandleCascadeFields;
    use ShouldSnakeAttributes;
    use HasFormFlags;

    /**
     * Eloquent model of the form.
     *
     * @var Model
     */
    public $model;

    /**
     * @var Validator
     */
    public $validator;

    /**
     * @var Builder
     */
    public $builder;

    /**
     * Data for save to current model from input.
     *
     * @var array
     */
    protected $updates = [];

    /**
     * Data for save to model's relations from input.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Refrence to model's relations fields.
     *
     * @var array
     */
    protected $relation_fields = [];

    /**
     * Refrence to model's relations actual fields.
     *
     * @var array
     */
    protected $relations_fields_ref = [];

    /**
     * Refrence to fields that must be prepared before update.
     *
     * @var array
     */
    protected $must_prepare = [];

    /**
     * Input data.
     *
     * @var array
     */
    protected $inputs = [];

    /**
     * @var Layout
     */
    protected $layout;

    /**
     * Ignored saving fields.
     *
     * @var array
     */
    protected $ignored = [];

    /**
     * Collected field assets.
     *
     * @var array
     */
    protected static $collectedAssets = [];

    /**
     * @var Tab
     */
    protected $tab = null;

    /**
     * Field rows in form.
     *
     * @var array
     */
    public $rows = [];

    /**
     * @var bool
     */
    protected $isSoftDeletes = false;

    /**
     * Show the footer fixed at the bottom of the screen.
     *
     * @var bool
     */
    public $fixedFooter = true;

    /**
     * Overwrite the resource url if needed.
     *
     * @var string
     */
    public $resourceUrl = false;

    /**
     * Create a new form instance.
     *
     * @param         $model
     * @param Closure $callback
     */
    public function __construct($model, Closure $callback = null)
    {
        $this->model = $model;

        $this->builder = new Builder($this);

        $this->initLayout();

        if ($callback instanceof Closure) {
            $callback($this);
        }

        $this->isSoftDeletes = in_array(SoftDeletes::class, class_uses_deep($this->model), true);

        $this->initFormAttributes();
        $this->callInitCallbacks();
    }

    /**
     * @param Field $field
     *
     * @return $this
     */
    public function pushField(Field $field): self
    {
        $field->setForm($this);

        if (!empty($field->must_prepare)) {
            $this->must_prepare[] = $field->column();
        }

        $width = $this->builder->getWidth();
        $field->setWidth($width['field'], $width['label']);

        $this->fields()->push($field);
        $this->layout->addField($field);

        if (method_exists($field, 'initForForm')) {
            $field->initForForm();
        }

        return $this;
    }

    /**
     * @return Model|\OpenAdmin\Admin\Actions\Interactor\Form
     */
    public function model(): Model|Actions\Interactor\Form
    {
        return $this->model;
    }

    /**
     * @return Builder
     */
    public function builder(): Builder
    {
        return $this->builder;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function fields()
    {
        return $this->builder()->fields();
    }

    /**
     * Generate a edit form.
     *
     * @param $id
     *
     * @return $this
     */
    public function edit($id): self
    {
        $this->builder->setMode(Builder::MODE_EDIT);
        $this->builder->setResourceId($id);

        $this->setFieldValue($id);

        return $this;
    }

    /**
     * Use tab to split form.
     *
     * @param string  $title
     * @param Closure $content
     * @param bool    $active
     *
     * @return $this
     */
    public function tab($title, Closure $content, bool $active = false): self
    {
        $this->setTab()->append($title, $content, $active);

        return $this;
    }

    /**
     * Get Tab instance.
     *
     * @return Tab
     */
    public function getTab()
    {
        return $this->tab;
    }

    /**
     * Set Tab instance.
     *
     * @return Tab
     */
    public function setTab(): Tab
    {
        if ($this->tab === null) {
            $this->tab = new Tab($this);
        }

        return $this->tab;
    }

    /**
     * Destroy data entity and remove files.
     *
     * @param $id
     *
     * @return mixed
     */
    public function destroy($id)
    {
        try {
            if (($ret = $this->callDeleting($id)) instanceof Response) {
                return $ret;
            }

            collect(explode(',', $id))->filter()->each(function ($id) {
                $builder = $this->model()->newQuery();

                if ($this->isSoftDeletes) {
                    $builder = $builder->withTrashed();
                }

                $model = $builder->with($this->getRelations())->findOrFail($id);

                if ($this->isSoftDeletes && $model->trashed()) {
                    $this->deleteFiles($model, true);
                    $model->forceDelete();

                    return;
                }

                $this->deleteFiles($model);
                $model->delete();
            });

            if (($ret = $this->callDeleted()) instanceof Response) {
                return $ret;
            }

            $response = [
                'status'  => true,
                'message' => trans('admin.delete_succeeded'),
            ];
        } catch (\Exception $exception) {
            $response = [
                'status'  => false,
                'message' => $exception->getMessage() ?: trans('admin.delete_failed'),
            ];
        }

        return response()->json($response);
    }

    /**
     * Remove files in record.
     *
     * @param Model $model
     * @param bool  $forceDelete
     */
    protected function deleteFiles(Model $model, $forceDelete = false)
    {
        // If it's a soft delete, the files in the data will not be deleted.
        if (!$forceDelete && $this->isSoftDeletes) {
            return;
        }

        $data = $model->toArray();

        $this->fields()->filter(function ($field) {
            return $field instanceof Field\File;
        })->each(function (Field\File $file) use ($data) {
            $file->setOriginal($data);
            $file->destroy();
        });
    }

    /**
     * Store a new record.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\Http\JsonResponse
     */
    public function store()
    {
        $data = \request()->all();

        // Handle validation errors.
        if ($validationMessages = $this->validationMessages($data)) {
            return $this->responseValidationError($validationMessages);
        }

        if (($response = $this->prepare($data)) instanceof Response) {
            return $response;
        }

        DB::transaction(function () {
            $inserts = $this->prepareUpdate($this->updates);

            foreach ($inserts as $column => $value) {
                $this->model->setAttribute($column, $value);
                $this->fixColumnArrayValue($column);
            }

            $this->model->save();

            $this->updateRelation($this->relations);
        });

        if (($response = $this->callSaved()) instanceof Response) {
            return $response;
        }

        if ($response = $this->ajaxResponse(trans('admin.save_succeeded'))) {
            return $response;
        }

        return $this->redirectAfterStore();
    }

    /**
     * Laravel bug: if a model doesn't exists yet (no id) array's can't be saved to a column
     * Either use a modifier on the model or its gets automaticly encoded as json.
     * Prabably fixed in Laravel 8.
     *
     * @param string $column
     */
    public function fixColumnArrayValue($column)
    {
        if (version_compare(app()->version(), '8.0.0', '<')) {
            $this->model->$column = json_encode($this->model->$column);
        }
    }

    /**
     * @param MessageBag $message
     *
     * @return $this|\Illuminate\Http\JsonResponse
     */
    protected function responseValidationError(MessageBag $message)
    {
        if (\request()->ajax() && !\request()->pjax()) {
            return response()->json([
                'status'     => false,
                'validation' => $message,
                'message'    => $message->first(),
            ]);
        }

        return back()->withInput()->withErrors($message);
    }

    /**
     * Get ajax response.
     *
     * @param string $message
     *
     * @return bool|\Illuminate\Http\JsonResponse
     */
    protected function ajaxResponse($message)
    {
        $request = \request();

        // ajax but not pjax
        if ($request->ajax() && !$request->pjax()) {
            return response()->json([
                'status'  => true,
                'message' => $message,
                'display' => $this->applayFieldDisplay(),
            ]);
        }

        return false;
    }

    /**
     * @return array
     */
    protected function applayFieldDisplay()
    {
        $editable = [];

        /** @var Field $field */
        foreach ($this->fields() as $field) {
            if (!\request()->has($field->column())) {
                continue;
            }

            $newValue = $this->model->fresh()->getAttribute($field->column());

            if ($newValue instanceof Arrayable) {
                $newValue = $newValue->toArray();
            }

            if ($field instanceof Field\BelongsTo || $field instanceof Field\BelongsToMany) {
                $selectable = $field->getSelectable();

                if (method_exists($selectable, 'display')) {
                    $display = $selectable::display();

                    $editable[$field->column()] = $display->call($this->model, $newValue);
                }
            }
        }

        return $editable;
    }

    /**
     * Prepare input data for insert or update.
     *
     * @param array $data
     *
     * @return mixed
     */
    protected function prepare($data = [])
    {
        if (($response = $this->callSubmitted()) instanceof Response) {
            return $response;
        }

        $this->inputs = array_merge($this->removeIgnoredFields($data), $this->inputs);

        if (($response = $this->callSaving()) instanceof Response) {
            return $response;
        }

        $this->relations = $this->getRelationInputs($this->inputs);
        $this->getRelations(); // store relations field and

        $this->updates = Arr::except($this->inputs, array_keys($this->relations));
    }

    /**
     * Remove ignored fields from input.
     *
     * @param array $input
     *
     * @return array
     */
    protected function removeIgnoredFields($input): array
    {
        Arr::forget($input, $this->ignored);

        return $input;
    }

    /**
     * Get inputs for relations.
     *
     * @param array $inputs
     *
     * @return array
     */
    protected function getRelationInputs($inputs = []): array
    {
        $relations = [];

        foreach ($inputs as $column => $value) {
            if ((method_exists($this->model, $column)
                || method_exists($this->model, $column = Str::camel($column)))
                && !method_exists(Model::class, $column)
            ) {
                $relation = call_user_func([$this->model, $column]);

                if ($relation instanceof Relations\Relation) {
                    $relations[$column] = $value;
                }
            }
        }

        return $relations;
    }

    /**
     * Handle update.
     *
     * @param int  $id
     * @param null $data
     *
     * @return bool|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|mixed|null|Response
     */
    public function update($id, $data = null)
    {
        $data = ($data) ?: request()->all();

        $isEditable = $this->isEditable($data);

        if (($data = $this->handleColumnUpdates($id, $data)) instanceof Response) {
            return $data;
        }

        /* @var Model $this ->model */
        $model = $this->model();

        if ($this->isSoftDeletes) {
            $model = $model->withTrashed();
        }

        $withRelations = $this->getRelations();

        // prevent relation update for inline edits
        if (!empty($data['_edit_inline'])) {
            $withRelations = array_intersect($withRelations, array_keys($data));
        }

        $this->model = $model->with($withRelations)->findOrFail($id);
        $this->setFieldOriginalValue();

        // Handle validation errors.
        if ($validationMessages = $this->validationMessages($data)) {
            if (!$isEditable) {
                return back()->withInput()->withErrors($validationMessages);
            }

            return response()->json(['errors' => Arr::dot($validationMessages->getMessages())], 422);
        }

        if (($response = $this->prepare($data)) instanceof Response) {
            return $response;
        }

        DB::transaction(function () use ($withRelations) {
            $updates = $this->prepareUpdate($this->updates);
            foreach ($updates as $column => $value) {
                /* @var Model $this ->model */
                $this->model->setAttribute($column, $value);
            }
            $this->model->save();
            if (!empty($withRelations)) {
                $this->updateRelation($this->relations);
            }
        });

        if (($result = $this->callSaved()) instanceof Response) {
            return $result;
        }

        if ($response = $this->ajaxResponse(trans('admin.update_succeeded'))) {
            return $response;
        }

        return $this->redirectAfterUpdate($id);
    }

    /**
     * Get RedirectResponse after store.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterStore()
    {
        $resourcesPath = $this->resource(0);
        $key           = $this->model->getKey();

        return $this->redirectAfterSaving($resourcesPath, $key);
    }

    /**
     * Get RedirectResponse after update.
     *
     * @param mixed $key
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterUpdate($key)
    {
        $resourcesPath = $this->resource(-1);

        return $this->redirectAfterSaving($resourcesPath, $key);
    }

    /**
     * Get RedirectResponse after data saving.
     *
     * @param string $resourcesPath
     * @param string $key
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function redirectAfterSaving($resourcesPath, $key)
    {
        if (request('after-save-url')) {
            // return to custom url
            $url = urldecode(request('after-save-url'));
        } elseif (request('after-save') == 'continue_editing') {
            // continue editing
            $url = rtrim($resourcesPath, '/')."/{$key}/edit";
        } elseif (request('after-save') == 'continue_creating') {
            // continue creating
            $url = rtrim($resourcesPath, '/').'/create';
        } elseif (request('after-save') == 'view') {
            // view resource
            $url = rtrim($resourcesPath, '/')."/{$key}";
        } elseif (request('after-save') == 'exit') {
            // return message
            return trans('admin.save_succeeded');
            exit;
        } elseif (strpos(request('_previous_'), 'ids')) {
            $url = (new BatchEdit(trans('admin.batch_edit')))->buildBatchUrl($resourcesPath);
        } else {
            $url = request(Builder::PREVIOUS_URL_KEY) ?: $resourcesPath;
        }

        admin_toastr(trans('admin.save_succeeded'));

        return redirect($url);
    }

    /**
     * Check if request is from editable.
     *
     * @param array $input
     *
     * @return bool
     */
    protected function isEditable(array $input = []): bool
    {
        return array_key_exists('_editable', $input) || array_key_exists('_edit_inline', $input);
    }

    /**
     * Handle updates for single column.
     *
     * @param int   $id
     * @param array $data
     *
     * @return array|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response|Response
     */
    protected function handleColumnUpdates($id, $data)
    {
        $data = $this->handleEditable($data);

        if ($this->handleOrderable($id, $data)) {
            return response([
                'status'  => true,
                'message' => trans('admin.update_succeeded'),
            ]);
        }

        return $data;
    }

    /**
     * Handle editable update.
     *
     * @param array $input
     *
     * @return array
     */
    protected function handleEditable(array $input = []): array
    {
        if (array_key_exists('_editable', $input)) {
            $name  = $input['name'];
            $value = $input['value'];

            Arr::forget($input, ['pk', 'value', 'name']);
            Arr::set($input, $name, $value);
        }

        return $input;
    }

    /**
     * Handle orderable update.
     *
     * @param int   $id
     * @param array $input
     *
     * @return bool
     */
    protected function handleOrderable($id, array $input = [])
    {
        if (array_key_exists('_orderable', $input)) {
            $model = $this->model->find($id);

            if ($model instanceof Sortable) {
                $input['_orderable'] == 1 ? $model->moveOrderUp() : $model->moveOrderDown();

                return true;
            }
        }

        return false;
    }

    /**
     * Update relation data.
     *
     * @param array $relationsData
     *
     * @return void
     */
    protected function updateRelation($relationsData, $subRelation = [])
    {
        if (!empty($subRelation)) {
            $relations_fields = $subRelation['relation_fields'];
            $must_prepare     = $subRelation['must_prepare'] ?? [];
            //$model            = $subRelation['model'];
            $model = $subRelation['parent'];
        } else {
            $relations_fields = $this->relation_fields;
            $must_prepare     = $this->must_prepare;
            $model            = $this->model;
        }

        foreach ($relations_fields as $field) {
            if (!isset($relationsData[$field]) && in_array($field, $must_prepare)) {
                $relationsData[$field] = false;
            }
        }

        foreach ($relationsData as $name => $values) {
            if (!method_exists($model, $name)) {
                continue;
            }

            $relation = $model->$name();

            $isRelationUpdate = true;
            $prepared         = $this->prepareUpdate([$name => $values], $isRelationUpdate, $subRelation);

            if (empty($prepared)) {
                continue;
            }

            switch (true) {
                case $relation instanceof Relations\BelongsToMany:
                case $relation instanceof Relations\MorphToMany:
                    if (isset($prepared[$name])) {
                        $relation->sync($prepared[$name]);
                    }
                    break;
                case $relation instanceof Relations\HasOne:
                case $relation instanceof Relations\MorphOne:
                    $related = $model->getRelationValue($name) ?: $relation->getRelated();

                    foreach ($prepared[$name] as $column => $value) {
                        $related->setAttribute($column, $value);
                    }

                    // save child
                    $relation->save($related);
                    break;
                case $relation instanceof Relations\BelongsTo:
                case $relation instanceof Relations\MorphTo:
                    $related = $model->getRelationValue($name) ?: $relation->getRelated();

                    foreach ($prepared[$name] as $column => $value) {
                        $related->setAttribute($column, $value);
                    }

                    // save parent
                    $related->save();

                    // save child (self)
                    $relation->associate($related)->save();
                    break;
                case $relation instanceof Relations\HasMany:
                case $relation instanceof Relations\MorphMany:
                    if (!empty($prepared[$name])) {
                        foreach ($prepared[$name] as $relationValues) {
                            /** @var Relations\HasOneOrMany $relation */
                            $relation = $model->$name();

                            $keyName = $relation->getRelated()->getKeyName();
                            $key     = Arr::get($relationValues, $keyName);

                            /** @var Model $child */
                            $child              = $relation->findOrNew($key);
                            $fieldsWithRelation = $this->getSubRelationsField($name);

                            if (Arr::get($relationValues, static::REMOVE_FLAG_NAME) == 1) {
                                $child->delete();
                                continue;
                            }

                            Arr::forget($relationValues, static::REMOVE_FLAG_NAME);
                            Arr::forget($relationValues, $fieldsWithRelation);

                            if (!empty($relationValues)) {
                                $child->fill($relationValues);
                                $child->save();
                            }

                            foreach ($fieldsWithRelation as $relationSubField) {
                                // get the unprepared data
                                if ($key) {
                                    $subRelationsValues = [$relationSubField => Arr::get($relationsData[$name][$key], $relationSubField)];
                                    $this->processSubRelations($child, $relation, $name, $relationSubField, $subRelationsValues);
                                }
                            }
                        }
                    }
                    break;
            }
        }
        // dd("exit");
        // if exist before end
        // db transation will not run
    }

    public function getSubRelationsField($relationName)
    {
        $subRelations = array_filter($this->relation_fields, function ($relation) use ($relationName) {
            return strpos($relation, $relationName.'.') !== false;
        });

        $subRelationFields = array_map(function ($subRelation) use ($relationName) {
            return str_replace($relationName.'.', '', $subRelation);
        }, $subRelations);

        return array_values($subRelationFields);
    }

    protected function processSubRelations($parent, $relationModel, $relationName, $relationSubField, $subRelationsValues)
    {
        $subModel = $relationModel->getRelated()->$relationSubField()->getRelated();

        if (!isset($subRelationsValues[$relationSubField])) {
            return;
        }
        $this->updateRelation(
            [$relationSubField => $subRelationsValues[$relationSubField]],
            [
                'parent'          => $parent,
                'relation_name'   => $relationName.'.'.$relationSubField,
                'relation_fields' => [$relationSubField],
                'must_prepare'    => [],
                'model'           => $subModel,
            ]
        );
    }

    /**
     * Prepare input data for update.
     *
     * @param array $updates
     * @param bool  $isRelationUpdate for skipping fields that have or dont have a relation
     *
     * @return array
     */
    protected function prepareUpdate(array $updates, $isRelationUpdate = false, $subRelation = null): array
    {
        $prepared = [];
        $fields   = $fields ?? $this->fields();

        if ($subRelation) {
            $fields = [$this->relations_fields_ref[$subRelation['relation_name']]];
        }

        /** @var Field $field */
        foreach ($fields as $field) {
            $columns = $field->column();

            if (!$isRelationUpdate && (in_array($columns, $this->relation_fields) || $field->hasRelation())) {
                // skip fields that have a relation
                continue;
            }

            if ($isRelationUpdate && !(in_array($columns, $this->relation_fields) || $field->hasRelation() || $subRelation)) {
                // skip fields that have not relation
                continue;
            }

            $value = $this->getDataByColumn($updates, $columns);
            $value = $field->prepare($value);

            if ($isRelationUpdate && method_exists($field, 'prepare_relation')) {
                $value = $field->prepare_relation($value);
            }

            // only process values if not false
            if ($value !== false) {
                if (is_array($columns)) {
                    foreach ($columns as $name => $column) {
                        $col_value = $value[$name];
                        if (is_array($col_value)) {
                            $col_value = $this->filterFalseValues($col_value);
                        }
                        Arr::set($prepared, $column, $col_value);
                    }
                } elseif (is_string($columns)) {
                    if (is_array($value)) {
                        $value = $this->filterFalseValues($value);
                    }
                    Arr::set($prepared, $columns, $value);
                }
            }
        }

        return $prepared;
    }

    protected function filterFalseValues($value)
    {
        foreach ($value as &$row) {
            if (is_array($row)) {
                $row = array_filter($row, function ($val) {
                    return $val !== false;
                });
            }
        }

        return $value;
    }

    /**
     * @param string|array $columns
     * @param bool         $containsDot
     *
     * @return bool
     */
    protected function isInvalidColumn($columns, $containsDot = false): bool
    {
        foreach ((array) $columns as $column) {
            if ((!$containsDot && Str::contains($column, '.'))
                || ($containsDot && !Str::contains($column, '.'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare input data for insert.
     *
     * @param $inserts
     *
     * @return array
     */
    protected function prepareInsert($inserts): array
    {
        if ($this->isHasOneRelation($inserts)) {
            $inserts = Arr::dot($inserts);
        }

        foreach ($inserts as $column => $value) {
            if (($field = $this->getFieldByColumn($column)) === null) {
                unset($inserts[$column]);
                continue;
            }
            $inserts[$column] = $field->prepare($value);
        }

        $prepared = [];

        foreach ($inserts as $key => $value) {
            if ($value !== false) {
                Arr::set($prepared, $key, $value);
            }
        }

        return $prepared;
    }

    /**
     * Is input data is has-one relation.
     *
     * @param array $inserts
     *
     * @return bool
     */
    protected function isHasOneRelation($inserts): bool
    {
        $first = current($inserts);

        if (!is_array($first)) {
            return false;
        }

        if (is_array(current($first))) {
            return false;
        }

        return Arr::isAssoc($first);
    }

    /**
     * Ignore fields to save.
     *
     * @param string|array $fields
     *
     * @return $this
     */
    public function ignore($fields): self
    {
        $this->ignored = array_merge($this->ignored, (array) $fields);

        return $this;
    }

    /**
     * @param array        $data
     * @param string|array $columns
     *
     * @return array|mixed
     */
    protected function getDataByColumn($data, $columns)
    {
        if (is_string($columns)) {
            return Arr::get($data, $columns, false);
        }

        if (is_array($columns)) {
            $value = [];
            foreach ($columns as $name => $column) {
                if (!Arr::has($data, $column)) {
                    continue;
                }
                $value[$name] = Arr::get($data, $column, false);
            }

            return $value;
        }

        // if not found return false
        // false values won't be save
        return false;
    }

    /**
     * Find field object by column.
     *
     * @param $column
     *
     * @return mixed
     */
    protected function getFieldByColumn($column, $form = null)
    {
        $form = $form ?? $this;

        return $form->fields()->first(
            function (Field $field) use ($column) {
                if (is_array($field->column())) {
                    return in_array($column, $field->column());
                }

                return $field->column() == $column;
            }
        );
    }

    /**
     * Find field object by column.
     *
     * @param $column
     *
     * @return mixed
     */
    public function callFieldByColumn($column, Closure $callback)
    {
        $this->fields()->each(function (Field $field) use ($column, $callback) {
            if (is_array($field->column())) {
                if (in_array($column, $field->column())) {
                    $callback($field);

                    return;
                }
            }
            if ($field->column() == $column) {
                $callback($field);

                return;
            }
        });
    }

    /**
     * Set original data for each field.
     *
     * @return void
     */
    protected function setFieldOriginalValue()
    {
        $values = $this->model->toArray();

        $this->fields()->each(function (Field $field) use ($values) {
            $field->setOriginal($values);
        });
    }

    /**
     * Set all fields value in form.
     *
     * @param $id
     *
     * @return void
     */
    protected function setFieldValue($id)
    {
        $relations = $this->getRelations();

        $model = $this->model();

        if ($this->isSoftDeletes) {
            $model = $model->withTrashed();
        }

        $this->model = $model->with($relations)->findOrFail($id);

        $this->callEditing();

        $data = $this->model->toArray();

        $this->fields()->each(function (Field $field) use ($data) {
            if (!in_array($field->column(), $this->ignored, true)) {
                $field->fill($data);
            }
        });
    }

    /**
     * Add a fieldset to form.
     *
     * @param string  $title
     * @param Closure $setCallback
     *
     * @return Field\Fieldset
     */
    public function fieldset(string $title, Closure $setCallback)
    {
        $fieldset = new Field\Fieldset();

        $this->html($fieldset->start($title))->plain();

        $setCallback($this);

        $this->html($fieldset->end())->plain();

        return $this;
    }

    /**
     * Get validation messages.
     *
     * @param array $input
     *
     * @return MessageBag|bool
     */
    public function validationMessages($input)
    {
        $failedValidators = [];

        /** @var Field $field */
        foreach ($this->fields() as $field) {
            if (!$validator = $field->getValidator($input)) {
                continue;
            }
            if (($validator instanceof Validator) && !$validator->passes()) {
                $failedValidators[] = $validator;
            }
        }

        $message = $this->mergeValidationMessages($failedValidators);

        return $message->any() ? $message : false;
    }

    /**
     * Merge validation messages from input validators.
     *
     * @param \Illuminate\Validation\Validator[] $validators
     *
     * @return MessageBag
     */
    protected function mergeValidationMessages($validators): MessageBag
    {
        $messageBag = new MessageBag();

        foreach ($validators as $validator) {
            $messageBag = $messageBag->merge($validator->messages());
        }

        return $messageBag;
    }

    /**
     * Get all relations of model from callable.
     *
     * @return array
     */
    public function getRelations($form = null, $prefix = ''): array
    {
        $relations = $columns = $checkRelations = [];
        $form      = $form ?? $this;

        /* @var Field $field */
        foreach ($form->fields() as $field) {
            if ($field->hasRelation()) {
                $checkRelations[$field->column()] = $field;
            }
            $columns[] = $field->column();
        }

        foreach (Arr::flatten($columns) as $column) {
            if (Str::contains($column, '.')) {
                list($relation) = explode('.', $column);

                if (method_exists($form->model, $relation)
                    && !method_exists(Model::class, $relation)
                    && $form->model->$relation() instanceof Relations\Relation
                ) {
                    $relations[] = $prefix.$relation;
                }
            } elseif (method_exists($form->model, $column) && !method_exists(Model::class, $column)) {
                $relations[] = $prefix.$column;

                $this->relations_fields_ref[$prefix.$column] = $this->getFieldByColumn($column, $form);

                if (isset($checkRelations[$column])) {
                    $field = $checkRelations[$column];

                    if ($field instanceof HasMany) {
                        $sub_model = $form->model->{$column}()->getRelated();
                        $sub_form  = $field->getNestedForm($sub_model);

                        $sub_relations = $this->getRelations($sub_form, $prefix.$column.'.');
                        $relations     = array_merge($relations, $sub_relations);
                    }
                }
            }
        }

        $this->relation_fields = array_unique($relations);

        return $this->relation_fields;
    }

    /**
     * Set action for form.
     *
     * @param string $action
     *
     * @return $this
     */
    public function setAction($action): self
    {
        $this->builder()->setAction($action);

        return $this;
    }

    /**
     * Set field and label width in current form.
     *
     * @param int $fieldWidth
     * @param int $labelWidth
     *
     * @return $this
     */
    public function setWidth($fieldWidth = 8, $labelWidth = 2): self
    {
        $this->fields()->each(function ($field) use ($fieldWidth, $labelWidth) {
            /* @var Field $field  */
            $field->setWidth($fieldWidth, $labelWidth);
        });

        $this->builder()->setWidth($fieldWidth, $labelWidth);

        return $this;
    }

    /**
     * Set field prefix for current form fields.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function setFieldsPrependClass($prefix): self
    {
        $this->fields()->each(function ($field) use ($prefix) {
            /* @var Field $field  */
            $field->setPrependElementClass([$prefix]);
        });

        return $this;
    }

    /**
     * Set field appendix for current form fields.
     *
     * @param int $appendix
     *
     * @return $this
     */
    public function setFieldsAppendClass($suffix): self
    {
        $this->fields()->each(function ($field) use ($suffix) {
            /* @var Field $field  */
            $field->setAppendElementClass([$suffix]);
        });

        return $this;
    }

    /**
     * Set view for form.
     *
     * @param string $view
     *
     * @return $this
     */
    public function setView($view): self
    {
        $this->builder()->setView($view);

        return $this;
    }

    /**
     * Set title for form.
     *
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title = ''): self
    {
        $this->builder()->setTitle($title);

        return $this;
    }

    /**
     * Set a submit confirm.
     *
     * @param string $message
     * @param string $on
     *
     * @return $this
     */
    public function confirm(string $message, $on = null)
    {
        if ($on && !in_array($on, ['create', 'edit'])) {
            throw new \InvalidArgumentException("The second paramater `\$on` must be one of ['create', 'edit']");
        }

        if ($on == 'create' && !$this->isCreating()) {
            return;
        }

        if ($on == 'edit' && !$this->isEditing()) {
            return;
        }

        $this->builder()->confirm($message);

        return $this;
    }

    /**
     * Add a row in form.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function row(Closure $callback): self
    {
        $this->rows[] = new Row($callback, $this);

        return $this;
    }

    /**
     * Tools setting for form.
     *
     * @param Closure $callback
     */
    public function tools(Closure $callback)
    {
        $callback->call($this, $this->builder->getTools());
    }

    /**
     * @param Closure|null $callback
     *
     * @return Form\Tools
     */
    public function header(Closure $callback = null)
    {
        if (func_num_args() === 0) {
            return $this->builder->getTools();
        }

        $callback->call($this, $this->builder->getTools());
    }

    /**
     * Indicates if current form page is creating.
     *
     * @return bool
     */
    public function isCreating(): bool
    {
        return Str::endsWith(\request()->route()->getName(), ['.create', '.store']);
    }

    /**
     * Indicates if current form page is editing.
     *
     * @return bool
     */
    public function isEditing(): bool
    {
        return Str::endsWith(\request()->route()->getName(), ['.edit', '.update']);
    }

    /**
     * Disable form submit.
     *
     * @param bool $disable
     *
     * @return $this
     *
     * @deprecated
     */
    public function disableSubmit(bool $disable = true): self
    {
        $this->builder()->getFooter()->disableSubmit($disable);

        return $this;
    }

    /**
     * Disable form reset.
     *
     * @param bool $disable
     *
     * @return $this
     *
     * @deprecated
     */
    public function disableReset(bool $disable = true): self
    {
        $this->builder()->getFooter()->disableReset($disable);

        return $this;
    }

    /**
     * Disable View Checkbox on footer.
     *
     * @param bool $disable
     *
     * @return $this
     */
    public function disableViewCheck(bool $disable = true): self
    {
        $this->builder()->getFooter()->disableViewCheck($disable);

        return $this;
    }

    /**
     * Disable Editing Checkbox on footer.
     *
     * @param bool $disable
     *
     * @return $this
     */
    public function disableEditingCheck(bool $disable = true): self
    {
        $this->builder()->getFooter()->disableEditingCheck($disable);

        return $this;
    }

    /**
     * Disable Creating Checkbox on footer.
     *
     * @param bool $disable
     *
     * @return $this
     */
    public function disableCreatingCheck(bool $disable = true): self
    {
        $this->builder()->getFooter()->disableCreatingCheck($disable);

        return $this;
    }

    /**
     * Footer setting for form.
     *
     * @param Closure $callback
     *
     * @return Form\Footer
     */
    public function footer(Closure $callback = null)
    {
        if (func_num_args() === 0) {
            return $this->builder()->getFooter();
        }

        $callback($this->builder()->getFooter());
    }

    /**
     * Get current resource route url.
     *
     * @param int $slice
     *
     * @return string
     */
    public function resource($slice = -2): string
    {
        $url      = !empty($this->resourceUrl) ? trim($this->resourceUrl) : trim(\request()->getUri(), '/');
        $segments = explode('/', $url);

        if ($slice !== 0) {
            $segments = array_slice($segments, 0, $slice);
        }

        return implode('/', $segments);
    }

    /**
     * Get set the name of the current resource url (without /admin/).
     *
     * @param string $path
     *
     * @return Form
     */
    public function setResourcePath($path)
    {
        $this->resourceUrl = admin_url($path);

        return $this;
    }

    /**
     * Render the form contents.
     *
     * @return string
     */
    public function render()
    {
        try {
            return $this->builder->render();
        } catch (\Exception $e) {
            return Handler::renderException($e);
        }
    }

    /**
     * Get or set input data.
     *
     * @param string $key
     * @param null   $value
     *
     * @return array|mixed
     */
    public function input($key, $value = null)
    {
        if ($value === null) {
            return Arr::get($this->inputs, $key);
        }

        return Arr::set($this->inputs, $key, $value);
    }

    /**
     * Add a new layout column.
     *
     * @param int     $width
     * @param Closure $closure
     *
     * @return $this
     */
    public function column($width, Closure $closure): self
    {
        $width = $width < 1 ? round(12 * $width) : $width;

        $this->layout->column($width, $closure);

        return $this;
    }

    /**
     * Initialize filter layout.
     */
    protected function initLayout()
    {
        $this->layout = new Layout($this);
    }

    /**
     * Getter.
     *
     * @param string $name
     *
     * @return array|mixed
     */
    public function __get($name)
    {
        return $this->input($name);
    }

    /**
     * Setter.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return array
     */
    public function __set($name, $value)
    {
        return Arr::set($this->inputs, $name, $value);
    }

    /**
     * __isset.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->inputs[$name]);
    }

    /**
     * Generate a Field object and add to form builder if Field exists.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return Field
     */
    public function __call($method, $arguments)
    {
        if ($className = static::findFieldClass($method)) {
            $column = Arr::get($arguments, 0, ''); //[0];

            $element = new $className($column, array_slice($arguments, 1));

            $this->pushField($element);

            return $element;
        }

        admin_error('Error', "Field type [$method] does not exist.");

        return new Field\Nullable();
    }

    /**
     * @return Layout
     */
    public function getLayout(): Layout
    {
        return $this->layout;
    }
}
