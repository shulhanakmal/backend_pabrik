<?php

namespace App\Http\Controllers\API;

use DB;
use Auth;
use App\Sales;
use App\Logistic;
use App\StokSugar;
use App\Transaction;
use Illuminate\Http\Request;
use App\LogisticReturnBulkSugar;
use App\LogisticStockOutBulkSugar;
use App\Http\Controllers\Controller;
use App\LogisticStockBulkSugarFromRs;
use App\LogisticStockBulkSugarFromCane;

class LogisticController extends Controller {
    public $successStatus = 401;

    function __construct() {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function index() {
        $this->successStatus = 200;
        $success['success'] = true;
        // $success['logistic']   = Logistic::all();
        // $success['stockBulkSugarFromCane']   = LogisticStockBulkSugarFromCane::all();
        // $success['stockBulkSugarFromRs']   = LogisticStockBulkSugarFromRs::all();
        // $success['stockOutBulkSugar']   = LogisticStockOutBulkSugar::all();
        // $success['returnBulkSugar']   = LogisticReturnBulkSugar::all();
        $success['stockBulkSugarFromCane']   = LogisticStockBulkSugarFromCane::orderBy('created_at', 'desc')->get();
        $success['stockBulkSugarFromRs']   = LogisticStockBulkSugarFromRs::orderBy('created_at', 'desc')->get();
        $success['stockOutBulkSugar']   = LogisticStockOutBulkSugar::orderBy('created_at', 'desc')->get();
        $success['returnBulkSugar']   = LogisticReturnBulkSugar::orderBy('created_at', 'desc')->get();
        $success['stok'] = StokSugar::latest()->first();
        // $success['stockBulkSugarFromCane']   = LogisticStockBulkSugarFromCane::where('milled_sugar_cane', '!=', NULL)->get();
        // $success['stockBulkSugarFromRs']   = LogisticStockBulkSugarFromRs::where('processed_rs', '!=', NULL)->get();
        // $success['stockOutBulkSugar']   = LogisticStockOutBulkSugar:: where('sugar_cane', '!=', NULL)->get();
        // $success['returnBulkSugar']   = LogisticReturnBulkSugar::where('sugar_from_rs', '!=', NULL)->get();

        return response()->json($success, $this->successStatus);
    }

    public function getBuyer(Request $request, $date){
        $buyer = new Sales;
        $cari = $buyer->where('date', 'like', '%'.$date.'%')->distinct()->pluck('buyer');

        if(count($cari) >= 1){
            $success['buyer'] = $cari;
        } else {
            $success['buyer'] = [];
        }

        $this->successStatus = 200;
        $success['success'] = true;

        return response()->json($success, $this->successStatus);
    }

    public function getSugar($pembeli, $date) {
        $cariCane = Sales::where('buyer', 'like', '%'.$pembeli.'%')->where('date', 'like', '%'.$date.'%')->sum('mount_sugar_sold_cane');
        $cariRs = Sales::where('buyer', 'like', '%'.$pembeli.'%')->where('date', 'like', '%'.$date.'%')->sum('mount_sugar_sold_rs');
        
        if($cariCane >= 1 && $cariRs >= 1) {
            $success['sugar'] = ['cane', 'rs'];
        } else {
            if($cariCane >= 1) {
                $success['sugar'] = ['cane'];
            } else {
                $success['sugar'] = ['rs'];
            }
        }

        $this->successStatus = 200;
        $success['date'] = $date;
        $success['success'] = true;

        return response()->json($success, $this->successStatus);
    }

    public function getMaxlength($pembeli, $date, $sugar){
        if($sugar == 'rs'){
            $max = Sales::where('buyer', 'like', '%'.$pembeli.'%')->where('date', 'like', '%'.$date.'%')->sum('mount_sugar_sold_rs');
        } else {
            $max = Sales::where('buyer', 'like', '%'.$pembeli.'%')->where('date', 'like', '%'.$date.'%')->sum('mount_sugar_sold_cane');
            $test = Sales::where('buyer', 'like', '%'.$pembeli.'%')->first();
        }

        $success['max'] = str_replace(".00", "", $max);
        $this->successStatus = 200;
        $success['pembeli'] = $pembeli;
        $success['date'] = $date;
        $success['success'] = true;

        return response()->json($success, $this->successStatus);
    }

    public function add(Request $request) {
        if($request->param == 'stockBulkSugarFromCane'){
            $logistic = new LogisticStockBulkSugarFromCane();
        } else if($request->param == 'stockBulkSugarFromRs'){
            $logistic = new LogisticStockBulkSugarFromRs();
        } else if($request->param == 'stockOutBulkSugar'){
            $logistic = new LogisticStockOutBulkSugar();
            $logistic->sugar = $request->sugar;
        } else if($request->param == 'returnBulkSugar'){
            if($request->sugar == 'cane'){
                $cek = Sales::where('date', 'like', '%'.$request->date.'%')->where('buyer', 'like', '%'.$request->buyer.'%')->sum('mount_sugar_sold_cane');
            } else {
                $cek = Sales::where('date', 'like', '%'.$request->date.'%')->where('buyer', 'like', '%'.$request->buyer.'%')->sum('mount_sugar_sold_rs');
            }

            if($request->volume > $cek){ // jika jumlah volume return yang diinput lebih besar dari jumlah pembelian
                return false;
            }

            $logistic = new LogisticReturnBulkSugar();
            $logistic->buyer = $request->buyer;
            $logistic->sugar = $request->sugar;
        } else {
            return false;
        }
        $logistic->date = $request->date;
        $logistic->volume = $request->volume;
        $logistic->save();

        // update stok
        $cekStock = StokSugar::latest()->first();

        $updateStock = new StokSugar;
        $updateStock->date = $request->date;
        if($request->param == 'stockBulkSugarFromCane') {
            $updateStock->cane = $cekStock->cane + $request->volume;
            $updateStock->rs = $cekStock->rs + 0;
            $updateStock->proses = 'Logistics Add Sugar Cane (Stok Bertambah)';
        } else if($request->param == 'stockBulkSugarFromRs') {
            $updateStock->cane = $cekStock->cane + 0;
            $updateStock->rs = $cekStock->rs + $request->volume;
            $updateStock->proses = 'Logistics Add Raw Sugar (Stok Bertambah)';
        } else if($request->param =='stockOutBulkSugar') {
            if($request->sugar == 'cane') {
                $updateStock->cane = $cekStock->cane - $request->volume;
                $updateStock->rs = $cekStock->rs - 0;
                $updateStock->proses = 'Logistics Stock Out Sugar Cane (Stok Berkurang)';
            } else {
                $updateStock->cane = $cekStock->cane - 0;
                $updateStock->rs = $cekStock->rs - $request->volume;
                $updateStock->proses = 'Logistics Stock Out Raw Sugar (Stok Berkurang)';
            }
        } else { // return
            if($request->sugar == 'cane') {
                $updateStock->cane = $cekStock->cane + $request->volume;
                $updateStock->rs = $cekStock->rs + 0;
                $updateStock->proses = 'Logistics Return Sugar Cane (Stok Bertambah)';
            } else {
                $updateStock->cane = $cekStock->cane + 0;
                $updateStock->rs = $cekStock->rs + $request->volume;
                $updateStock->proses = 'Logistics Return Raw Sugar (Stok Bertambah)';
            }
        }
        $updateStock->volume = $request->volume;
        $updateStock->save();

        // jika prosesnya adalah pengembalian (return bulk sugar)
        if($request->param == 'returnBulkSugar'){
            if($request->sugar == 'rs'){ // jika transaksi sales adalah sugar rs
                // penambahan data stok bulk sugar from rs
                $return = new LogisticStockBulkSugarFromRs();
                $return->date = $request->date;
                $return->volume = $request->volume;
                $return->save();
            } else { // jika transaksi sales adalah sugar cane
                // penambahan data stok bulk sugar from cane
                $return = new LogisticStockBulkSugarFromCane();
                $return->date = $request->date;
                $return->volume = $request->volume;
                $return->save();
            }
            $success['stock'] = $return;
        }

        // insert for summary logistic
        $stockBulkSugarFromCane = LogisticStockBulkSugarFromCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $stockBulkSugarFromRs = LogisticStockBulkSugarFromRs::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $stockOutBulkSugar = LogisticStockOutBulkSugar::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $returnBulkSugar = LogisticReturnBulkSugar::where('date', 'like', '%'.$request->date.'%')->sum('volume');        

        // check data logistic
        $check = Logistic::where('date', 'like', '%'.$request->date.'%')->first(); // check apakah sudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            $summary = new Logistic;
        }
        $summary->date = $request->date;
        $summary->stock_bulk_sugar_from_cane = $stockBulkSugarFromCane;
        $summary->stock_bulk_sugar_from_rs = $stockBulkSugarFromRs;
        $summary->stock_out_bulk_sugar = $stockOutBulkSugar;
        $summary->return_bulk_sugar = $returnBulkSugar;
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $logistic;

        return response()->json($success, $this->successStatus);
    }

    public function summary() {
        $this->successStatus = 200;
        $success['success'] = true;

        $interval = 0;
        $valDate = [];
        $valLine = [];
        $valSBSFC = [];
        $valSBSFRS = [];
        $valSOBS = [];
        $valRBS = [];

        $akhir = Logistic::orderBy('date', 'desc')->first()->date;
        // looping all data Logistics
        foreach(Logistic::orderBy('date', 'asc')->get() as $l){ // urutkan ascending
            $i = $interval++;
            // setting agar tiap 14x looping maka interval menjadi 0
            if ($i == 13){
                $interval = 0;
            }

            // setting jika interval 0 maka ambil data
            if ($i == 0 || $l->date == $akhir){
                array_push($valDate, date('d-M-Y', strtotime($l->date)));
                array_push($valLine, ($l->stock_bulk_sugar_from_cane ? $l->stock_bulk_sugar_from_cane : 0)+($l->stock_bulk_sugar_from_rs ? $l->stock_bulk_sugar_from_rs : 0)-($l->stock_out_bulk_sugar ? $l->stock_out_bulk_sugar : 0));
                array_push($valSBSFC, $l->stock_bulk_sugar_from_cane);
                array_push($valSBSFRS, $l->stock_bulk_sugar_from_rs);
                array_push($valSOBS, $l->stock_out_bulk_sugar);
                array_push($valRBS, $l->return_bulk_sugar);
            }
        }

        // foreach(Logistic::all() as $l) {
        //     array_push($valDate, date('d-M-Y', strtotime($l->date)));
        //     array_push($valLine, ($l->stock_bulk_sugar_from_cane ? $l->stock_bulk_sugar_from_cane : 0)+($l->stock_bulk_sugar_from_rs ? $l->stock_bulk_sugar_from_rs : 0)-($l->stock_out_bulk_sugar ? $l->stock_out_bulk_sugar : 0));
        // }

        $stacked = Logistic::orderBy('date', 'asc')->get();

        // $success['summaryLogistic'] = Logistic::all();
        $success['summaryLogistic'] = Logistic::orderBy('date', 'desc')->get();
        $success['date'] = $valDate;
        $success['sbsfc'] = $valSBSFC;
        $success['sbsfrs'] = $valSBSFRS;
        $success['line'] = $valLine;
        $success['sobs'] = $valSOBS;
        $success['rbs'] = $valRBS;
        $success['stacked'] = $stacked;

        
        
        // $success['date'] = Logistic::orderBy('date', 'asc')->get()->pluck('date');
        // $success['msc'] = Logistic::orderBy('date', 'asc')->get()->pluck('stock_bulk_sugar_from_cane');
        // $success['prs'] = Logistic::orderBy('date', 'asc')->get()->pluck('stock_bulk_sugar_from_rs');
        // $success['sc'] = Logistic::orderBy('date', 'asc')->get()->pluck('stock_out_bulk_sugar');
        // $success['sfrs'] = Logistic::orderBy('date', 'asc')->get()->pluck('return_bulk_sugar');

        return response()->json($success, $this->successStatus);
    }

    public function addHash(Request $request) {
        $insert = new Transaction();
        $insert->user = Auth::user()->username;
        $insert->wallet = $request->wallet;
        $insert->transaksi_hash = $request->transaction;
        $insert->flag = 'logistics';
        if($request->flag == 'stockBulkSugarFromCane'){
            $cariTransaksi = LogisticStockBulkSugarFromCane::find($request->id);
            $insert->jenis_transaksi = 'Stock Bulk Sugar From Cane';
        } else if($request->flag == 'stockBulkSugarFromRs'){
            $cariTransaksi = LogisticStockBulkSugarFromRs::find($request->id);
            $insert->jenis_transaksi = 'Stock Bulk Sugar From RS';
        } else if($request->flag == 'stockOutBulkSugar') {
            $cariTransaksi = LogisticStockOutBulkSugar::find($request->id);
            $insert->jenis_transaksi = 'Stock Out Bulk Sugar';
        } else if($request->flag == 'returnBulkSugar'){
            $cariTransaksi = LogisticReturnBulkSugar::find($request->id);
            $insert->jenis_transaksi = 'Return Bulk Sugar';
        } else {
            return false;
        }

        if($cariTransaksi){
            $insert->transaksi_id = $request->id;
        }

        $insert->save();        

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $insert;

        return response()->json($success, $this->successStatus);
    }

    public function indexEdit($flag, $id) {
        if($flag == 'sbsfc'){
            $cariData = LogisticStockBulkSugarFromCane::find($id);
        } else if($flag == 'sbsfrs') {
            $cariData = LogisticStockBulkSugarFromRs::find($id);
        } else if($flag == 'sobs') {
            $cariData = LogisticStockOutBulkSugar::find($id);
        } else {
            $cariData = LogisticReturnBulkSugar::find($id);
        }
        
        $success['id']     = $id;
        $success['flag']     = $flag;
        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $cariData;

        return response()->json($success, $this->successStatus);
    }

    public function edit(Request $request) {
        if($request->flag == 'sbsfc'){
            $data = LogisticStockBulkSugarFromCane::find($request->id);
        } else if($request->flag == 'sbsfrs') {
            $data = LogisticStockBulkSugarFromRs::find($request->id);
        } else if($request->flag == 'sobs') {
            $data = LogisticStockOutBulkSugar::find($request->id);
        } else {
            $data = LogisticReturnBulkSugar::find($request->id);
            $data->buyer = $request->buyer;
            if($data->sugar == 'cane') { // cari data yang direturn cane atau rs
                $sugar = LogisticStockBulkSugarFromCane::where('date', $data->date)->where('volume', $data->volume)->first();
            } else {
                $sugar = LogisticStockBulkSugarFromRs::where('date', $data->date)->where('volume', $data->volume)->first();
            }

            $data->sugar = $request->sugar;

            // update data return yang masuk ke stock sugar
            $sugar->date = $request->date;
            $sugar->volume = $request->volume;
            $sugar->save();
            $success['sugar'] = $sugar;
        }
        $data->date = $request->date;
        $data->volume = $request->volume;
        $data->save();

        // insert for summary logistic
        $stockBulkSugarFromCane = LogisticStockBulkSugarFromCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $stockBulkSugarFromRs = LogisticStockBulkSugarFromRs::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $stockOutBulkSugar = LogisticStockOutBulkSugar::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $returnBulkSugar = LogisticReturnBulkSugar::where('date', 'like', '%'.$request->date.'%')->sum('volume');        

        // check data logistic
        $check = Logistic::where('date', 'like', '%'.$request->date.'%')->first(); // check apakah sudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            $summary = new Logistic;
        }
        $summary->date = $request->date;
        $summary->stock_bulk_sugar_from_cane = $stockBulkSugarFromCane;
        $summary->stock_bulk_sugar_from_rs = $stockBulkSugarFromRs;
        $summary->stock_out_bulk_sugar = $stockOutBulkSugar;
        $summary->return_bulk_sugar = $returnBulkSugar;
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $data;

        return response()->json($success, $this->successStatus);
    }
}
