<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Logistic;
use App\LogisticStockOutBulkSugar;
use App\LogisticStockBulkSugarFromCane;
use App\LogisticStockBulkSugarFromRs;
use App\LogisticReturnBulkSugar;
use App\Production;
use App\ProductionMilledSugarCane;
use App\ProductionSugarCane;
use App\ProductionProcessedRs;
use App\ProductionSugarFromRs;
use App\SummarySales;
use App\Sales;
use App\Transaction;
use Auth;
use DB;

class PabrikController extends Controller {
    public $successStatus = 401;

    function __construct() {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function deleteData($flag, $id) {
        if($flag == 'msc') {
            $data = ProductionMilledSugarCane::find($id);
            $date = $data->date;
            // edit summary
            $production = Production::where('date', $date)->first();
            $production->milled_sugar_cane = $production->milled_sugar_cane - $data->volume;
            $production->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'prs') {
            $data = ProductionProcessedRs::find($id);
            $date = $data->date;
            // edit summary
            $production = Production::where('date', $date)->first();
            $production->processed_rs = $production->processed_rs - $data->volume;
            $production->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'sc') {
            $data = ProductionSugarCane::find($id);
            $date = $data->date;
            // edit summary
            $production = Production::where('date', $date)->first();
            $production->sugar_cane = $production->sugar_cane - $data->volume;
            $production->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'sfrs') {
            $data = ProductionSugarFromRS::find($id);
            $date = $data->date;
            // edit summary
            $production = Production::where('date', $date)->first();
            $production->sugar_from_rs = $production->sugar_from_rs - $data->volume;
            $production->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'sbsfc') {
            $data = LogisticStockBulkSugarFromCane::find($id);
            $date = $data->date;
            // edit summary
            $logistics = Logistic::where('date', $date)->first();
            $logistics->stock_bulk_sugar_from_cane = $logistics->stock_bulk_sugar_from_cane - $data->volume;
            $logistics->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'sbsfrs') {
            $data = LogisticStockBulkSugarFromRs::find($id);
            $date = $data->date;
            // edit summary
            $logistics = Logistic::where('date', $date)->first();
            $logistics->stock_bulk_sugar_from_rs = $logistics->stock_bulk_sugar_from_rs - $data->volume;
            $logistics->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'sobs') {
            $data = LogisticStockOutBulkSugar::find($id);
            $date = $data->date;
            // edit summary
            $logistics = Logistic::where('date', $date)->first();
            $logistics->stock_out_bulk_sugar = $logistics->stock_out_bulk_sugar - $data->volume;
            $logistics->save();

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else if($flag == 'return' || $flag == 'rbs') {
            $dataReturn = LogisticReturnBulkSugar::find($id);
            $data['data'] = $dataReturn;
            $date = $dataReturn->date;

            // stock sugar dikurangi
            if($dataReturn->sugar == 'cane'){
                $r = LogisticStockBulkSugarFromCane::where('date', $dataReturn->date)->where('volume', $dataReturn->volume)->first();
            } else {
                $r = LogisticStockBulkSugarFromRs::where('date', $dataReturn->date)->where('volume', $dataReturn->volume)->first();
            }
            $data['sugar'] = $r;
            $r->delete();

            // edit summary
            $logistics = Logistic::where('date', $date)->first();
            $logistics->return_bulk_sugar = $logistics->return_bulk_sugar - $dataReturn->volume;
            $logistics->save();

            // hapus data
            $hapus = $dataReturn->delete();

            return $data;
        } else if($flag == 'sales') {
            $data = Sales::find($id);
            $date = $data->date;

            // hapus data
            $hapus = $data->delete();
            return $data;
        } else {
            return false;
        }
    }

}
