@if (!empty($showAsField))
    @include("admin::form._header")
@else
    <div class="row has-many-head {{$column_class}}">
        <h4>{{ $label }}</h4>
    </div>
    <hr style="margin-top: 0px;" class="form-border m-0">
@endif

<div id="has-many-{{$column}}">
    @if (!$has_parent)
        <div class="pb-3 pt-2 text-secondary"><i class="icon-info-circle text-secondary"></i>&nbsp;{{__('admin.save_before_add_subs')}}</div>
    @else
        <table class="{{$uniqueId}} has-many-{{$column}} table table-with-fields vertical-align-{{$verticalAlign}}">
            <thead>
            <tr>
                @if(!empty($options['sortable']))
                    <th></th>
                @endif

                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach

                <th class="hidden"></th>

                @if($options['allowDelete'])
                    <th></th>
                @endif
            </tr>
            </thead>
            <tbody class="{{$uniqueId}} has-many-{{$column}}-forms">
            @foreach($forms as $pk => $form)

                <tr class="{{$uniqueId}} has-many-{{$column}}-form fields-group">

                    @if(!empty($options['sortable']))
                    <td width="20"><span class="icon-arrows-alt-v btn btn-light handle"></span></td>
                    @endif

                    <?php $hidden = ''; ?>

                    @foreach($form->fields() as $field)
                        @php
                            $field->setParent($column,$pk);
                        @endphp
                        @if (is_a($field, \OpenAdmin\Admin\Form\Field\Hidden::class))
                            <?php $hidden .= $field->render(); ?>
                            @continue
                        @endif

                        <td>{!! $field->setLabelClass(['hidden'])->setWidth(12, 0)->render() !!}</td>
                    @endforeach

                    <td class="hidden">{!! $hidden !!}<input type="hidden" class="parentId" name="{{$column}}_id" value="{{ $pk }}"></td>

                    @if($options['allowDelete'])
                        <td class="form-group">
                            <div>
                                <div class="{{$uniqueId}} has-many-{{$column}}-remove remove btn btn-danger btn-sm pull-right"><i class="icon-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>

        <template class="{{$uniqueId}} {{$column}}-tpl">
            <tr class="{{$uniqueId}} has-many-{{$column}}-form fields-group">

                @if(!empty($options['sortable']))
                    <td width="20"><span class="icon-arrows-alt-v btn btn-light handle"></span></td>
                @endif

                {!! $template !!}

                <td class="form-group">
                    <div>
                        <div class="{{$uniqueId}} has-many-{{$column}}-remove remove btn btn-danger btn-sm pull-right"><i class="icon-trash">&nbsp;</i>{{ trans('admin.remove') }}</div>
                    </div>
                </td>
            </tr>
        </template>
    @endif

    @if($options['allowCreate'])
        <div class="form-group">
            <div class="{{$viewClass['field']}}">
                <div class="{{$uniqueId}} has-many-{{$column}}-add add btn btn-success btn-sm {{!$has_parent ? 'disabled' :''}}"><i class="icon-plus"></i>&nbsp;{{ trans('admin.new') }}</div>
            </div>
        </div>
    @endif

</div>

@if (!empty($showAsField))
    @include("admin::form._footer")
@endif