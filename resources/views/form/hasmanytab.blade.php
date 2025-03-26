@if (!empty($showAsField))
    @include("admin::form._header")
@else
    <div class="row has-many-head {{$column_class}}">
        <h4>{{ $label }}</h4>
    </div>

    <hr style="margin-top: 0px;" class="form-border m-0">
@endif


<div id="has-many-{{$column}}" class="{{$uniqueId}} has-many-{{$column}} nav-tabs-custom">
    @if (!$has_parent)
        <div class="pb-3 pt-4 text-secondary"><i class="icon-info-circle text-secondary"></i>&nbsp;{{__('admin.save_before_add_subs')}}</div>
    @else
        <ul class="nav nav-tabs">
            @foreach($forms as $pk => $form)
                <li id="tab_{{ $relationName . '_' . $pk }}" class="nav-item">
                    <a class="nav-link @if ($form == reset($forms)) active @endif " href="#{{ $relationName . '_' . $pk }}" data-bs-toggle="tab">
                        {{ $pk }} <i class="icon-exclamation-circle text-red hide"></i>
                    </a>
                </li>
            @endforeach
            <li class="nav-item add-tab">
                <button type="button" class="{{$uniqueId}} has-many-{{$column}}-add add btn btn-light btn-sm"><i class="icon-plus-circle" style="font-size: large;"></i></button>
            </li>

        </ul>

        <div class="{{$uniqueId}} tab-content has-many-{{$column}}-forms">

            @foreach($forms as $pk => $form)
                <div class="{{$uniqueId}} tab-pane fields-group has-many-{{$column}}-form @if ($form == reset($forms)) active @endif" id="{{ $relationName . '_' . $pk }}">

                    @foreach($form->fields() as $field)
                        @php
                            $field->setParent($column,$pk);
                        @endphp
                        {!! $field->render() !!}
                    @endforeach

                    @if($options['allowDelete'])
                    <div class="form-group form-delete-group">
                        <label class="{{$viewClass['label']}} form-label"></label>
                        <div class="{{$viewClass['field']}}">
                            <div class="{{$uniqueId}} has-many-{{$column}}-remove remove btn btn-danger btn-sm pull-right"><i class="icon-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>

        <template class="{{$uniqueId}} {{$column}}-tab-tpl">
            <li class="new nav-item" id="tab_{{ $relationName . '_new_' . \OpenAdmin\Admin\Form\NestedForm::DEFAULT_KEY_NAME }}">
                <a class="nav-link" href="#{{ $relationName . '_new_' . \OpenAdmin\Admin\Form\NestedForm::DEFAULT_KEY_NAME }}" data-bs-toggle="tab">
                    &nbsp;New {{ \OpenAdmin\Admin\Form\NestedForm::DEFAULT_KEY_NAME }} <i class="icon-exclamation-circle text-red hide"></i>
                </a>
            </li>
        </template>
        <template  class="{{$uniqueId}} {{$column}}-tpl">
            <div class="tab-pane fields-group new" id="{{ $relationName . '_new_' . \OpenAdmin\Admin\Form\NestedForm::DEFAULT_KEY_NAME }}">
                {!! $template !!}
                @if($options['allowDelete'])
                <div class="form-group form-delete-group">
                    <label class="{{$viewClass['label']}} form-label"></label>
                    <div class="{{$viewClass['field']}}">
                        <div class="{{$uniqueId}} has-many-{{$column}}-remove remove btn btn-danger btn-sm pull-right"><i class="icon-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                    </div>
                </div>
                @endif
            </div>
        </template>
        </div>
    @endif

@if (!empty($showAsField))
@include("admin::form._footer")
@endif
