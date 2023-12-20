<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\AttachmenFile;
use App\Http\Traits\GeneralTrait;
use App\Models\Location;
use App\Http\Requests\StoreFileRequest;


class ExpenseController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware(["permissions:expanses/view"])->only(["getExpenses", "getExpense"]);
        $this->middleware(["permissions:expanses/insert"])->only(["setExpense"]);
        $this->middleware(["permissions:expanses/insert"])->only(["updateExpense"]);
        $this->middleware(["permissions:expanses/delete"])->only(["deleteExpense"]);
    } // end of __construct

    public function getExpenses($location_id)
    {
        try {
            $expenses = Expense::where('location_id', $location_id)
            ->with('expenseCategory')
            ->get();
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($expenses);
    } // end of getExpenses

    public function getExpense($id)
    {
        try {
            $expense = Expense::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$expense) {
            return customResponse("Expense not found", 404);
        }
        return customResponse($expense);
    } // end of getExpense

    public function setExpense(Request $request, $location_id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
            "amount" => "required|numeric",
            "category_id" => "required|numeric|exists:expenses,id",
            "image" => "required|string",
        ]);
        if ($valdator) {
            return $valdator;
        }
        if (!Location::find($location_id)) {
            return customResponse("Location not found", 404);
        }
        try {
            $expense = Expense::create([
                'name' => $request->name,
                'location_id' => $request->location_id,
                "amount" => $request->amount,
                "expense_id" => $request->category_id,
                "date" => date("Y-m-d H:i:s"),
                "path" => $request->image ?? null,
                "created_by" => auth()->user()->id,
                "created_at" => date("Y-m-d H:i:s"),
            ]);

        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($expense, 200);
    } // end of addExpense

    public function updateExpense(Request $request, $id)
    {
        $valdator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string|max:255',
            "amount" => "required|numeric",
            "category_id" => "required|numeric|exists:expenses,id",
            "image" => "nullable|string"
        ]);
        if ($valdator) {
            return $valdator;
        }
        try {
            $expense = Expense::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$expense) {
            return customResponse("Expense not found", 404);
        }
        try {
            $expense->update([
                'name' => $request->name,
                "amount" => $request->amount,
                "path" => $request->image ?? $expense->path,
                "expense_id" => $request->category_id,
            ]);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse($expense);
    } // end of updateExpense

    public function deleteExpense($id)
    {
        try {
            $expense = Expense::find($id);
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        if (!$expense) {
            return customResponse("Expense not found", 404);
        }
        try {
            $expense->delete();
        } catch (\Exception $e) {
            return customResponse($e->getMessage(), 500);
        }
        return customResponse("Expense deleted successfully");
    } // end of deleteExpense


}
