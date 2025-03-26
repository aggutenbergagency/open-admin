
@if (!empty($showAsField))
    @include("admin::form._header")
@else
    <div class="row has-many-head {{$column_class}}">
        <h4>{{ $label }}</h4>
    </div>

    <hr class="form-border">
@endif

<div id="has-many-{{$column_class}}" class="{{$uniqueId}} has-many-{{$column_class}} has-many-body">
    @if (!$has_parent)
        <div class="pb-3 pt-2 text-secondary"><i class="icon-info-circle text-secondary"></i>&nbsp;{{__('admin.save_before_add_subs')}}</div>
    @else
        <div class="{{$uniqueId}} has-many-{{$column_class}}-forms">

            @foreach($forms as $pk => $form)
                <div class="{{$uniqueId}} has-many-{{$column}}-form fields-group">

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
                    <hr class="form-border">
                </div>


            @endforeach
        </div>


        <template class="{{$uniqueId}} {{$column_class}}-tpl">
            <div class="{{$uniqueId}} has-many-{{$column_class}}-form fields-group">

                {!! $template !!}

                <div class="form-group form-delete-group">
                    <label class="{{$viewClass['label']}} form-label"></label>
                    <div class="{{$viewClass['field']}}">
                        <div class="{{$uniqueId}} has-many-{{$column}}-remove remove btn btn-danger btn-sm pull-right"><i class="icon-trash"></i>&nbsp;{{ trans('admin.remove') }}</div>
                    </div>
                </div>
                <hr class="form-border">

            </div>
        </template>

        @if($options['allowCreate'])
        <div class="has-many-footer form-group">
            <label class="{{$viewClass['label']}} form-label"></label>
            <div class="{{$viewClass['field']}}">
                <div class="{{$uniqueId}} has-many-{{$column}}-add add btn btn-success btn-sm"><i class="icon-plus"></i>&nbsp;{{ trans('admin.new') }}</div>
            </div>
        </div>
        @endif

    @endif

</div>
@if (!empty($showAsField))
@include("admin::form._footer")
@endif