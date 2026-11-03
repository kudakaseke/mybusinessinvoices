<?php

namespace App\Http\Controllers;

use App\DocumentPdf;
use App\DocumentTotals;
use App\Http\Requests\SaveDocumentRequest;
use App\Models\Client;
use App\Models\DocumentDraft;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    private function model(string $kind): string
    {
        abort_unless(in_array($kind, ['invoices', 'quotations'], true), 404);

        return $kind === 'invoices' ? Invoice::class : Quotation::class;
    }

    private function owned(Request $request, string $kind, int $record): Model
    {
        return $this->model($kind)::where('user_id', $request->user()->id)->findOrFail($record);
    }

    public function index(Request $request, string $kind): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $request->validate(['status' => ['nullable', 'in:overdue']]);
        $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
        $records = $this->model($kind)::where('user_id', $request->user()->id)
            ->when($kind === 'invoices' && $request->input('status') === 'overdue', fn ($query) => $query->whereDate('due_date', '<', today('Africa/Johannesburg'))->whereNotIn('status', ['paid', 'settled', 'credited']))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request, $number) {
                $query->where($number, 'like', '%'.$request->string('search').'%')->orWhere('customer_name', 'like', '%'.$request->string('search').'%');
            }))->latest('id')->paginate(15)->withQueryString();
        if ($kind === 'invoices') {
            $records->getCollection()->load(['payments', 'paymentHistory', 'creditNotes', 'refunds']);
        }

        return view('documents.index', compact('records', 'kind', 'number'));
    }

    public function create(Request $request, string $kind): View
    {
        $model = $this->model($kind);
        $user = $request->user();
        $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
        $today = today('Africa/Johannesburg');
        $prefix = ($kind === 'invoices' ? 'INV-' : 'QUO-').$today->format('m/y').'-';
        $highest = 0;
        foreach ($model::where('user_id', $user->id)->where($number, 'like', $prefix.'%')->pluck($number) as $existing) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/D', $existing, $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }
        $next = $prefix.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
        $document = new $model([
            $number => $next, 'issue_date' => $today, 'due_date' => $today,
            'business_name' => $user->business_name ?? $user->name, 'business_email' => $user->business_email ?? $user->email,
            'business_address' => $user->business_address, 'business_phone' => $user->business_phone,
            'prepared_by' => $user->name, 'currency' => $user->base_currency ?? 'USD',
            'vat_rate' => $user->default_tax_rate ?? 0, 'discount_rate' => 0, 'status' => 'outstanding',
        ]);

        return $this->form($request, $kind, $document);
    }

    private function form(Request $request, string $kind, Model $document): View
    {
        $request->validate(['draft' => ['nullable', 'uuid']]);
        $draft = $request->filled('draft')
            ? DocumentDraft::where('user_id', $request->user()->id)->where('kind', $kind)->where('key', $request->input('draft'))->firstOrFail()
            : null;
        $draftKey = $draft?->key ?? $request->old('draft_key') ?? (string) Str::uuid();
        $draftPayload = $draft?->payload ?? [];
        if ($draft) {
            $document->fill(Arr::except($draftPayload, ['items', 'quotation_id']));
        }
        $clients = Client::where('user_id', $request->user()->id)->orderBy('name')->get();
        $products = Product::where('user_id', $request->user()->id)->orderBy('name')->get();
        $items = $document->exists ? $document->items()->get()->map(fn ($item) => Arr::only($item->toArray(), ['product_id', 'description', 'quantity', 'unit_price']))->all() : [['description' => '', 'quantity' => '1', 'unit_price' => '0', 'product_id' => null]];

        $items = $draftPayload['items'] ?? $items;

        return view('documents.form', compact('kind', 'document', 'clients', 'products', 'items', 'draftKey', 'draftPayload'));
    }

    public function edit(Request $request, string $kind, int $record): View
    {
        $this->owned($request, $kind, $record);
        abort(403, 'Saved documents cannot be edited.');
    }

    public function store(SaveDocumentRequest $request, string $kind, DocumentTotals $calculator): RedirectResponse
    {
        return $this->save($request, $kind, new ($this->model($kind)), $calculator);
    }

    public function update(Request $request, string $kind, int $record): RedirectResponse
    {
        $this->owned($request, $kind, $record);
        abort(403, 'Saved documents cannot be edited.');
    }

    public function review(SaveDocumentRequest $request, string $kind, DocumentTotals $calculator, DocumentPdf $pdf): View
    {
        $data = $request->validated();
        $totals = $calculator->calculate($data['items'], $data['vat_rate'], $data['discount_rate']);
        $model = $this->model($kind);
        $document = new $model(array_merge(Arr::except($data, ['items']), Arr::except($totals, ['items'])));
        $itemModel = $kind === 'invoices' ? InvoiceItem::class : QuotationItem::class;
        $document->setRelation('items', collect($totals['items'])->map(fn (array $item) => new $itemModel($item)));
        $pdfData = base64_encode($pdf->render($document, $kind));

        return view('documents.review', compact('document', 'kind', 'data', 'pdfData'));
    }

    public function resume(SaveDocumentRequest $request, string $kind): RedirectResponse
    {
        return redirect()->route('documents.create', $kind)->withInput($request->validated());
    }

    public function download(Request $request, string $kind, int $record, DocumentPdf $pdf): Response
    {
        $document = $this->owned($request, $kind, $record)->load($kind === 'invoices' ? ['items', 'payments', 'creditNotes'] : ['items']);
        $number = $kind === 'invoices' ? $document->invoice_number : $document->quotation_number;
        $filename = ucfirst(rtrim($kind, 's')).'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $number).'.pdf';

        return response($pdf->render($document, $kind), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function save(SaveDocumentRequest $request, string $kind, Model $document, DocumentTotals $calculator): RedirectResponse
    {
        $data = $request->validated();
        $totals = $calculator->calculate($data['items'], $data['vat_rate'], $data['discount_rate']);
        DB::transaction(function () use ($document, $data, $totals, $request) {
            if (! empty($data['quotation_id'])) {
                $quote = Quotation::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($data['quotation_id']);
                if (Invoice::where('quotation_id', $quote->id)->exists()) {
                    throw ValidationException::withMessages(['quotation_id' => 'This quotation has already been converted to an invoice.']);
                }
                $document->quotation_id = $quote->id;
            }
            $document->fill(Arr::except($data, ['items', 'draft_key', 'quotation_id']));
            $document->fill(Arr::except($totals, ['items']));
            if ($document instanceof Invoice) {
                $document->status = DocumentTotals::scaled($document->total) === 0 ? 'paid' : 'outstanding';
            }
            $document->user_id = $request->user()->id;
            $document->save();
            $document->items()->delete();
            $document->items()->createMany($totals['items']);
            if (! empty($data['draft_key'])) {
                DocumentDraft::where('user_id', $request->user()->id)->where('key', $data['draft_key'])->delete();
            }
            Client::where('user_id', $request->user()->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['customer_email'])])->firstOrCreate([], [
                    'user_id' => $request->user()->id,
                    'email' => mb_strtolower($data['customer_email']),
                    'name' => $data['customer_name'],
                    'phone' => $data['customer_phone'] ?? null,
                    'address' => $data['customer_address'] ?? null,
                ]);
        });

        return redirect()->route('documents.show', [$kind, $document->id])->with('message', 'Document saved.');
    }

    public function show(Request $request, string $kind, int $record): View
    {
        $document = $this->owned($request, $kind, $record)->load($kind === 'invoices' ? ['items', 'payments', 'creditNotes', 'refunds', 'paymentHistory'] : ['items']);

        return view('documents.show', compact('kind', 'document'));
    }

    public function updatePayment(Request $request, int $record): RedirectResponse
    {
        $document = $this->owned($request, 'invoices', $record);
        $data = $request->validate([
            'status' => ['required', 'in:paid,outstanding'],
            'amount' => ['required_if:status,paid', 'nullable', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'paid_on' => ['required_if:status,paid', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$document->issue_date->toDateString(), 'before_or_equal:'.today('Africa/Johannesburg')->toDateString()],
            'reference' => ['nullable', 'string', 'max:255'],
            'submission_key' => ['required_if:status,paid', 'nullable', 'uuid'],
        ]);
        if ($data['status'] === 'paid') {
            DB::transaction(function () use ($document, $data): void {
                $invoice = Invoice::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if ($invoice->payments()->withTrashed()->where('submission_key', $data['submission_key'])->exists()) {
                    return;
                }
                if (InvoicePayment::withTrashed()->where('submission_key', $data['submission_key'])->exists()) {
                    throw ValidationException::withMessages(['submission_key' => 'Please reload the invoice and try recording this payment again.']);
                }
                $amount = DocumentTotals::scaled($data['amount']);
                $balance = DocumentTotals::scaled($invoice->balanceDue());
                if ($amount > $balance) {
                    throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the remaining balance of '.$invoice->currency.' '.$invoice->balanceDue().'.']);
                }
                $invoice->payments()->create(Arr::only($data, ['amount', 'paid_on', 'reference', 'submission_key']));
                $invoice->update(['status' => $invoice->settlementStatus()]);
            });
        }

        return redirect()->route('documents.show', ['invoices', $document->id])
            ->with('message', 'Payment status updated.');
    }
}
