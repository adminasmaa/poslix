<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function beforeClose(Request $request, $location_id)
    {
        $lastOpenRegistration = \DB::table('cash_registers')
            ->where('location_id', $location_id)
            ->where('user_id', auth()->user()->id)
            ->latest('id')
            ->first();
        if ($lastOpenRegistration && $lastOpenRegistration->status != 'open') {
            return customResponse('No open registration found to close', 400);
        }
        $lastOpenRegistrationTime = $lastOpenRegistration->created_at ?? now(); // if no registration found, then use current time
        $transactions = Transaction::where('location_id', $location_id)
            ->where('transactions.created_by', auth()->user()->id)
            ->where(function ($query) use ($lastOpenRegistrationTime) {
                $query->where('transactions.created_at', '>=', $lastOpenRegistrationTime);
//                    ->orWhereDate('transactions.created_at', now());
            })
            ->join('transaction_payments', 'transactions.id', '=', 'transaction_payments.transaction_id')
            ->where('transaction_payments.created_by', auth()->user()->id)
            ->selectRaw('sum(transaction_payments.amount) as total, transaction_payments.payment_type as payment_type')
            ->groupBy('transaction_payments.payment_type')
            ->get();
        $data = [
            'cash' => 0,
            'card' => 0,
            'cheque' => 0,
            'bank' => 0,
        ];
        foreach ($transactions as $transaction) {
            if ($transaction->payment_type == 'cash') {
                $data['cash'] = $transaction->total + 0;
            } else if ($transaction->payment_type == 'card') {
                $data['card'] = $transaction->total + 0;
            } else if ($transaction->payment_type == 'cheque') {
                $data['cheque'] = $transaction->total + 0;
            } else if ($transaction->payment_type == 'bank') {
                $data['bank'] = $transaction->total + 0;
            }
        }
        if (isset($request->hand_cash)) {
            $data['cash'] = $data['cash'] + $request->hand_cash + 0;
        }
        return customResponse($data, 200);
    }

    public function closeRegistration(Request $request, $location_id)
    {
        $lastOpenRegistration = \DB::table('cash_registers')
            ->where('location_id', $location_id)
            ->where('user_id', auth()->user()->id)
            ->latest('id')
            ->first();
        if ($lastOpenRegistration && $lastOpenRegistration->status != 'open') {
            return customResponse('No open registration found to close', 400);
        }
        $lastOpenRegistrationTime = $lastOpenRegistration->created_at ?? now(); // if no registration found, then use current time
        $transactions = Transaction::where('location_id', $location_id)
            ->where('transactions.created_by', auth()->user()->id)
            ->where(function ($query) use ($lastOpenRegistrationTime) {
                $query->where('transactions.created_at', '>=', $lastOpenRegistrationTime);
//                    ->orWhereDate('transactions.created_at', now());
            })
            ->join('transaction_payments', 'transactions.id', '=', 'transaction_payments.transaction_id')
            ->where('transaction_payments.created_by', auth()->user()->id)
            ->selectRaw('sum(transaction_payments.amount) as total, transaction_payments.payment_type as payment_type')
            ->groupBy('transaction_payments.payment_type')
            ->get();
        $data = [
            'cash' => 0,
            'card' => 0,
            'cheque' => 0,
            'bank' => 0,
            'hand_cash' => 0,
        ];
        foreach ($transactions as $transaction) {
            if ($transaction->payment_type == 'cash') {
                $data['cash'] = $transaction->total;
            } else if ($transaction->payment_type == 'card') {
                $data['card'] = $transaction->total;
            } else if ($transaction->payment_type == 'cheque') {
                $data['cheque'] = $transaction->total;
            } else if ($transaction->payment_type == 'bank') {
                $data['bank'] = $transaction->total;
            }
        }
        if (isset($request->hand_cash)) {
            $data['cash'] = $data['cash'] + $request->hand_cash;
        }
        $close = \DB::table('cash_registers')
            ->insert([
                'location_id' => $location_id,
                'user_id' => auth()->user()->id,
                'status' => 'close',
                'closed_at' => now(),
                'closing_amount' => isset($request->hand_cash) ? $data['hand_cash'] : 0,
                'total_card_slips' => $data['card'],
                'total_cash' => $data['cash'],
                'total_cheques' => $data['cheque'],
                'total_bank' => $data['bank'],
                'closing_note' => isset($request->note) ? $request->note : null,
                'created_at' => now(),
            ]);
        if (!$close) {
            return customResponse('Error while closing registration', 400);
        }
        return customResponse('Registration closed!', 200);
    } // end of closeRegistration

    public function openRegistration(Request $request, $location_id)
    {
        $open = \DB::table('cash_registers')
            ->insert([
                'location_id' => $location_id,
                'user_id' => auth()->user()->id,
                'status' => 'open',
                'created_at' => now(),
                'closing_amount' => isset($request->hand_cash) ? $request->hand_cash : 0,
            ]);
        if (!$open) {
            return customResponse('Error while opening registration', 400);
        }
        return customResponse('Registration opened! 👍', 200);
    }
}
