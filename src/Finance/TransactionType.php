<?php

namespace App\Finance;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
}
