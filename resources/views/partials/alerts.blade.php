@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show py-2">
    <i class="bi bi-check-circle"></i> {{ session('success') }}
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show py-2">
    <i class="bi bi-exclamation-triangle"></i>
    <ul class="mb-0 ps-3">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
