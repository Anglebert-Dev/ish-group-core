<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Models\Expense\Expense;
use App\Models\Invoice\Invoice;
use App\Models\Payment\Payment;
use App\Models\Supplier\Supplier;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    public function test_staff_can_open_finance_screens(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();

        $this->get(route('finance.index'))->assertOk();
        $this->get(route('finance.invoices.index'))->assertOk();
        $this->get(route('finance.payments.index'))->assertOk();
        $this->get(route('finance.expenses.index'))->assertOk();
        $this->get(route('finance.suppliers.index'))->assertOk();
        $this->get(route('finance.chart-of-accounts.index'))->assertOk();
        $this->get(route('finance.account-assignments.index'))->assertOk();
        $this->get(route('finance.ledger.index'))->assertOk();
        $this->get(route('finance.statements.index'))->assertOk();
        $this->get(route('finance.statements.trial-balance'))->assertOk();
        $this->get(route('finance.statements.profit-loss'))->assertOk();
        $this->get(route('finance.statements.balance-sheet'))->assertOk();
    }

    public function test_staff_can_create_a_chart_of_account(): void
    {
        $this->actingAsStaff();

        $this->post(route('finance.chart-of-accounts.store'), [
            'code' => '6.1.1',
            'name' => 'Marketing',
            'account_type' => AccountType::Expense->value,
            'is_posting' => '1',
        ])->assertRedirect();

        $account = ChartOfAccount::query()->where('code', '6.1.1')->first();
        $this->assertNotNull($account);
        $this->assertSame(AccountType::Expense, $account->account_type);
        $this->assertSame('debit', $account->normal_balance->value);
    }

    public function test_staff_can_create_a_supplier(): void
    {
        $this->actingAsStaff();

        $this->post(route('finance.suppliers.store'), [
            'name' => 'Kigali Fuels',
            'type' => SupplierType::Fuel->value,
            'phone' => '+250788444444',
            'status' => SupplierStatus::Active->value,
        ])->assertRedirect(route('finance.suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Kigali Fuels',
            'type' => SupplierType::Fuel->value,
        ]);
    }

    public function test_staff_can_create_issue_and_cancel_an_invoice(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $customer = $this->makeCustomer();

        $this->post(route('finance.invoices.store'), [
            'invoice_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'notes' => 'Rental invoice',
            'lines' => [
                [
                    'description' => 'Economy rental 3 days',
                    'quantity' => 3,
                    'unit_price' => '50000',
                ],
            ],
        ])->assertRedirect();

        $invoice = Invoice::query()->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('150000.00', (string) $invoice->total);
        $this->assertSame(1, $invoice->lines()->count());

        $this->post(route('finance.invoices.issue', $invoice))->assertRedirect();
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertDatabaseHas('general_ledgers', [
            'reference' => 'INV-ISSUE-'.$invoice->uuid,
        ]);

        $this->post(route('finance.invoices.cancel', $invoice))->assertRedirect();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
    }

    public function test_staff_can_record_a_payment_against_an_issued_invoice(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();
        $customer = $this->makeCustomer();

        $this->post(route('finance.invoices.store'), [
            'invoice_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'lines' => [
                [
                    'description' => 'Rental',
                    'quantity' => 1,
                    'unit_price' => '20000',
                ],
            ],
        ]);

        $invoice = Invoice::query()->first();
        $this->post(route('finance.invoices.issue', $invoice));

        $this->post(route('finance.payments.store'), [
            'payment_date' => now()->toDateString(),
            'amount' => '20000',
            'method' => PaymentMethod::Cash->value,
            'invoice_id' => $invoice->id,
            'description' => 'Cash receipt',
        ])->assertRedirect();

        $payment = Payment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('20000.00', (string) $payment->amount);
    }

    public function test_staff_can_create_a_draft_expense(): void
    {
        $this->seedChartOfAccounts();
        $this->actingAsStaff();

        $supplier = new Supplier([
            'name' => 'Parts Co',
            'type' => SupplierType::Parts->value,
            'status' => SupplierStatus::Active->value,
        ]);
        $supplier->save();

        $this->post(route('finance.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => '15000',
            'supplier_id' => $supplier->id,
            'description' => 'Oil filter',
            'post_now' => '0',
        ])->assertRedirect(route('finance.expenses.index'));

        $expense = Expense::query()->first();
        $this->assertNotNull($expense);
        $this->assertSame('15000.00', (string) $expense->amount);
        $this->assertSame($supplier->id, $expense->supplier_id);
    }
}
