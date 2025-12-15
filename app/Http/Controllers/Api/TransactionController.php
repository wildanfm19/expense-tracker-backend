<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // Get all transactions (dengan sorting by date terbaru)
    public function index(Request $request)
    {
        $query = $request->user()->transactions();

        // Filter by date 
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by type 
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category 
        if ($request->has('category')) {
            $query->where('category', 'LIKE', '%' . $request->category . '%');
        }

        $transactions = $query
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    // Create new transaction
    public function store(Request $request)
{
    $request->validate([
        'type' => 'required|in:income,expense',
        'amount' => 'required|integer|min:1',  // Rupiah format - whole numbers only
        'category' => 'required|string|max:255',
        'description' => 'nullable|string',
        'transaction_date' => 'required|date',
    ]);

    $transaction = Transaction::create([
        'user_id' => $request->user()->id,
        'type' => $request->type,
        'amount' => $request->amount,
        'category' => $request->category,
        'description' => $request->description,
        'transaction_date' => $request->transaction_date,
    ]);

    return response()->json([
        'message' => 'Transaction created successfully',
        'transaction' => $transaction,
    ], 201);
}

    // Get single transaction
    public function show(Request $request, $id)
    {
        $transaction = $request->user()
            ->transactions()
            ->findOrFail($id);

        return response()->json($transaction);
    }

    // Update transaction
    public function update(Request $request, $id)
    {
        $transaction = $request->user()
            ->transactions()
            ->findOrFail($id);

        $request->validate([
            'type' => 'sometimes|in:income,expense',
            'amount' => 'sometimes|integer|min:1',  // Rupiah format - whole numbers only
            'category' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'transaction_date' => 'sometimes|date',
        ]);

        $transaction->update($request->all());

        return response()->json([
            'message' => 'Transaction updated successfully',
            'transaction' => $transaction,
        ]);
    }

    // Delete transaction
    public function destroy(Request $request, $id)
    {
        $transaction = $request->user()
            ->transactions()
            ->findOrFail($id);

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully'
        ]);
    }

    // Get summary
    public function summary(Request $request)
    {
        $query = $request->user()->transactions();

        // Filter by date 
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        
        $expenseByCategory = $request->user()
            ->transactions()
            ->where('type', 'expense')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        $incomeByCategory = $request->user()
            ->transactions()
            ->where('type', 'income')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance' => $netBalance,
            'expense_by_category' => $expenseByCategory,
            'income_by_category' => $incomeByCategory,
        ]);
    }
}