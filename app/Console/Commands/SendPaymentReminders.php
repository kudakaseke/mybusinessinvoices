<?php

namespace App\Console\Commands;

use App\DocumentDelivery;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SendPaymentReminders extends Command
{
    protected $signature = 'invoices:remind';

    protected $description = 'Send weekly overdue reminders for businesses that opted in';

    public function handle(DocumentDelivery $delivery): int
    {
        $sent = 0;
        $failed = 0;
        Invoice::whereHas('user', fn ($query) => $query->where('payment_reminders_enabled', true)->whereNotNull('email_verified_at'))
            ->whereDate('due_date', '<', today('Africa/Johannesburg'))
            ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays(7)))
            ->with(['items', 'payments', 'creditNotes', 'refunds'])->chunkById(100, function ($invoices) use ($delivery, &$sent, &$failed): void {
                foreach ($invoices as $invoice) {
                    if (! $invoice->isOverdue()) {
                        continue;
                    }
                    try {
                        $delivery->send($invoice, 'invoices', reminder: true);
                        $sent++;
                    } catch (ValidationException $exception) {
                        $failed++;
                    }
                }
            });
        $this->info('Reminders sent: '.$sent.'. Failed: '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
