@if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
@if(session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
@if($errors->any()) <div class="alert alert-danger"><ul class="m-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div> @endif
