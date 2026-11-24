@if($errors->any())
    <div role="alert" class="mb-8 rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
        <p class="font-semibold">Please check the following:</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
