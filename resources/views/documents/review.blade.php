<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="h-screen overflow-hidden bg-[#edf1eb] text-[#13291f]">
<main class="flex h-screen flex-col">
    <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white px-5 py-3">
        <h1 class="text-lg font-semibold">Review {{ $kind==='invoices'?'invoice':'quotation' }}</h1>
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('documents.store',$kind) }}" class="flex items-center gap-3">
                @csrf
                @foreach(Illuminate\Support\Arr::dot($data) as $key=>$value)
                    <input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}" />
                @endforeach
                <button type="submit" formaction="{{ route('documents.resume',$kind) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm">Back to edit</button>
                <button type="submit" class="rounded-lg bg-[#103b26] px-6 py-2 text-sm text-white">Save</button>
            </form>
        </div>
    </header>
    <iframe title="{{ $kind==='invoices'?'Invoice':'Quotation' }} PDF preview" src="data:application/pdf;base64,{{ $pdfData }}#zoom=100&amp;toolbar=0&amp;navpanes=0" class="min-h-0 w-full flex-1 border-0"></iframe>
</main>
</body></html>
