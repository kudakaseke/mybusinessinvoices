@if(session('message'))<div role="status" class="mb-5 rounded-xl bg-emerald-100 p-4 text-sm text-emerald-900">{{ session('message') }}</div>@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
