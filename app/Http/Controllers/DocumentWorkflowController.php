<?php

namespace App\Http\Controllers;

use App\DocumentDelivery;
use App\Models\DocumentDraft;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentWorkflowController extends Controller
{
    public function drafts(Request $request): View
    {
        $drafts = DocumentDraft::where('user_id', $request->user()->id)
            ->when(! $request->user()->quotations_enabled, fn ($query) => $query->where('kind', 'invoices'))
            ->latest('updated_at')->paginate(20);

        return view('documents.drafts', compact('drafts'));
    }

    public function saveDraft(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'uuid'], 'payload' => ['required', 'array', 'max:40']]);
        $rules = [
            'items' => ['nullable', 'array', 'max:100'],
            'items.*' => ['array:product_id,description,quantity,unit_price'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['nullable', 'string', 'max:40'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('user_id', $request->user()->id)],
            'quotation_id' => ['nullable', 'integer', Rule::exists('quotations', 'id')->where('user_id', $request->user()->id)],
        ];
        foreach (['invoice_number', 'quotation_number', 'business_name', 'business_email', 'business_phone', 'customer_name', 'customer_email', 'customer_phone', 'prepared_by', 'payment_account_name', 'payment_account_number', 'payment_account_currency', 'payment_bank_name', 'issue_date', 'due_date', 'currency', 'vat_rate', 'discount_rate', 'status'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        foreach (['business_address', 'customer_address', 'notes'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:10000'];
        }
        $rules['issue_date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['due_date'] = ['nullable', 'date_format:Y-m-d'];
        $rules['currency'] = ['nullable', 'in:USD,ZWG'];
        $rules['vat_rate'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        $rules['discount_rate'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        $payload = Validator::make($data['payload'], $rules)->validate();
        DB::transaction(function () use ($request, $data, $payload, $kind): void {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DocumentDraft::where('key', $data['key'])->first();
            abort_if($existing && ($existing->user_id !== $request->user()->id || $existing->kind !== $kind), 404);
            DocumentDraft::updateOrCreate(['key' => $data['key']], ['user_id' => $request->user()->id, 'kind' => $kind, 'payload' => $payload]);
        });

        return response()->json(['key' => $data['key'], 'message' => 'Draft saved', 'saved_at' => now()->toIso8601String()]);
    }

    public function deleteDraft(Request $request, string $draft): RedirectResponse
    {
        DocumentDraft::where('user_id', $request->user()->id)->where('key', $draft)->firstOrFail()->delete();

        return back()->with('message', 'Draft deleted.');
    }

    public function convert(Request $request, int $record): RedirectResponse
    {
        return DB::transaction(function () use ($request, $record): RedirectResponse {
            $quote = Quotation::where('user_id', $request->user()->id)->with('items')->lockForUpdate()->findOrFail($record);
            $invoice = Invoice::where('user_id', $request->user()->id)->where('quotation_id', $quote->id)->first();
            if ($invoice) {
                return redirect()->route('documents.show', ['invoices', $invoice->id]);
            }
            $draft = DocumentDraft::where('user_id', $request->user()->id)->where('kind', 'invoices')->where('payload->quotation_id', $quote->id)->first();
            if (! $draft) {
                $payload = Arr::except($quote->toArray(), ['id', 'user_id', 'quotation_number', 'subtotal', 'total', 'vat_amount', 'discount_amount', 'created_at', 'updated_at', 'notes', 'items']);
                $payload['quotation_id'] = $quote->id;
                $payload['status'] = 'outstanding';
                $payload['issue_date'] = today('Africa/Johannesburg')->toDateString();
                $payload['due_date'] = today('Africa/Johannesburg')->addDays(14)->toDateString();
                $payload['items'] = $quote->items->map(fn ($item): array => Arr::only($item->toArray(), ['product_id', 'description', 'quantity', 'unit_price']))->all();
                $draft = DocumentDraft::create(['user_id' => $request->user()->id, 'kind' => 'invoices', 'key' => (string) Str::uuid(), 'payload' => $payload]);
            }

            return redirect()->route('documents.create', ['invoices', 'draft' => $draft->key])->with('message', 'Quotation copied into an invoice draft. Review and save it to finish.');
        });
    }

    public function email(Request $request, string $kind, int $record, DocumentDelivery $delivery): RedirectResponse
    {
        $model = $kind === 'invoices' ? Invoice::class : Quotation::class;
        $document = $model::where('user_id', $request->user()->id)->findOrFail($record);
        $delivery->send($document, $kind);

        return back()->with('message', 'Document emailed to '.$document->customer_email.' with the PDF attached.');
    }

    public function remind(Request $request, int $record, DocumentDelivery $delivery): RedirectResponse
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->findOrFail($record);
        $delivery->send($invoice, 'invoices', reminder: true);

        return back()->with('message', 'Payment reminder sent with the invoice PDF.');
    }
}
