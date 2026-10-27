<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\ChartOfAccountStatus;
use App\Models\AccountAssignment\AccountAssignment;
use App\Models\ChartOfAccount\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // code, name, type, is_posting, parent_code
            ['1', 'Assets', AccountType::Asset, false, null],
            ['1.1', 'Current Assets', AccountType::Asset, false, '1'],
            ['1.1.1', 'Bank / Cash', AccountType::Asset, true, '1.1'],
            ['1.2', 'Receivables', AccountType::Asset, false, '1'],
            ['1.2.1', 'Accounts Receivable', AccountType::Asset, true, '1.2'],
            ['2', 'Liabilities', AccountType::Liability, false, null],
            ['2.1', 'Customer Deposits', AccountType::Liability, false, '2'],
            ['2.1.1', 'Customer Deposits', AccountType::Liability, true, '2.1'],
            ['2.2', 'Payables', AccountType::Liability, false, '2'],
            ['2.2.1', 'Accounts Payable', AccountType::Liability, true, '2.2'],
            ['3', 'Equity', AccountType::Equity, false, null],
            ['3.1', 'Owner Equity', AccountType::Equity, false, '3'],
            ['3.1.1', 'Equity / Retained Earnings', AccountType::Equity, true, '3.1'],
            ['4', 'Revenue', AccountType::Revenue, false, null],
            ['4.1', 'Operating Revenue', AccountType::Revenue, false, '4'],
            ['4.1.1', 'Rental Revenue', AccountType::Revenue, true, '4.1'],
            ['4.1.2', 'Extras / Chauffeur Revenue', AccountType::Revenue, true, '4.1'],
            ['4.1.3', 'Extra Days / Damage Revenue', AccountType::Revenue, true, '4.1'],
            ['5', 'Expenses', AccountType::Expense, false, null],
            ['5.1', 'Operating Expenses', AccountType::Expense, false, '5'],
            ['5.1.1', 'Parts Expense', AccountType::Expense, true, '5.1'],
            ['5.1.2', 'Fuel Expense', AccountType::Expense, true, '5.1'],
            ['5.1.3', 'Insurance Expense', AccountType::Expense, true, '5.1'],
            ['5.1.4', 'Services Expense', AccountType::Expense, true, '5.1'],
            ['5.1.5', 'Other Expense', AccountType::Expense, true, '5.1'],
        ];

        $byCode = [];

        foreach ($accounts as [$code, $name, $type, $isPosting, $parentCode]) {
            $parentId = $parentCode ? ($byCode[$parentCode]->id ?? null) : null;

            $account = ChartOfAccount::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'account_type' => $type->value,
                    'normal_balance' => $type->normalBalance()->value,
                    'parent_id' => $parentId,
                    'is_posting' => $isPosting,
                    'status' => ChartOfAccountStatus::Active->value,
                    'current_balance' => ChartOfAccount::query()->where('code', $code)->value('current_balance') ?? 0,
                ],
            );

            $byCode[$code] = $account;
        }

        $assignments = [
            ['booking_confirm', 'receivable', '1.2.1'],
            ['booking_confirm', 'rental_revenue', '4.1.1'],
            ['booking_confirm', 'extras_revenue', '4.1.2'],
            ['booking_payment', 'cash', '1.1.1'],
            ['booking_payment', 'receivable', '1.2.1'],
            ['booking_return_charges', 'receivable', '1.2.1'],
            ['booking_return_charges', 'other_revenue', '4.1.3'],
            ['invoice_issue', 'receivable', '1.2.1'],
            ['invoice_issue', 'revenue', '4.1.1'],
            ['invoice_payment', 'cash', '1.1.1'],
            ['invoice_payment', 'receivable', '1.2.1'],
            ['expense_post', 'expense', '5.1.5'],
            ['expense_post', 'cash', '1.1.1'],
            ['expense_post', 'payable', '2.2.1'],
            ['supplier_pay', 'payable', '2.2.1'],
            ['supplier_pay', 'cash', '1.1.1'],
        ];

        foreach ($assignments as [$action, $slot, $code]) {
            AccountAssignment::query()->updateOrCreate(
                ['action' => $action, 'slot' => $slot],
                [
                    'chart_of_account_id' => $byCode[$code]->id,
                    'status' => ChartOfAccountStatus::Active->value,
                ],
            );
        }
    }
}
