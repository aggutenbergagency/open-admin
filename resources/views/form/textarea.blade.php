@include("admin::form._header")

	<textarea name="{{$name}}" class="form-control {{$class}}" rows="{{ $rows }}" placeholder="{{ $placeholder }}" {!! $attributes !!} >{{ old($name, $value) }}</textarea>

	{!! $append !!}

@include("admin::form._footer")
