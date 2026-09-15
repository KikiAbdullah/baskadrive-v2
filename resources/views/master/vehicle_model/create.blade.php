<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Tambah {{ $title }}</h6>
    </div>

    <div class="card-body  mt-3">
        <form method="POST" action="{{ route($url['store']) }}" class="js-crud-create" enctype="multipart/form-data">
            @csrf
            @include($form)
            <div class="d-flex justify-content-end align-items-center">
                <button type="submit" class="btn btn-primary waves-effect waves-light">
                    <i class="ri-send-plane-line me-2"></i>Submit
                </button>
            </div>
        </form>
    </div>
</div>