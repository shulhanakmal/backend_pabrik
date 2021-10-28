<?php

namespace App\Http\Controllers\API;

use Auth;
use App\Logistic;
use App\StokSugar;
use App\Production;
use App\Transaction;
use App\ProductionSugarCane;
use Illuminate\Http\Request;
use App\ProductionProcessedRs;
use App\ProductionSugarFromRs;
use App\ProductionMilledSugarCane;
use App\Http\Controllers\Controller;
use App\LogisticStockBulkSugarFromRs;
use App\LogisticStockBulkSugarFromCane;

class ProductionController extends Controller {
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
        // $success['production']   = Production::all();
        $success['milledSugarCane']   = ProductionMilledSugarCane::orderBy('created_at', 'desc')->get();
        $success['processedRS']   = ProductionProcessedRs::orderBy('created_at', 'desc')->get();
        $success['sugarCane']   = ProductionSugarCane::orderBy('created_at', 'desc')->get();
        $success['sugarFromRS']   = ProductionSugarFromRs::orderBy('created_at', 'desc')->get();
        // $success['milledSugarCane']   = ProductionMilledSugarCane::where('milled_sugar_cane', '!=', NULL)->get();
        // $success['processedRS']   = ProductionProcessedRS::where('processed_rs', '!=', NULL)->get();
        // $success['sugarCane']   = ProductionSugarCane::where('sugar_cane', '!=', NULL)->get();
        // $success['sugarFromRS']   = ProductionSugarFromRS::where('sugar_from_rs', '!=', NULL)->get();

        return response()->json($success, $this->successStatus);
    }

    public function inputProsesProduction($flag, $data) {
        $logistik = null;
        if($flag == 'milledSugarCane') {
            $input = new ProductionSugarCane();
            $logistik = new LogisticStockBulkSugarFromCane;
        } else if($flag == 'processedRs') {
            $input = new ProductionSugarFromRS();
            $logistik = new LogisticStockBulkSugarFromRs;
        } else if($flag == 'sugarCane'){
            // input data ke logistik
            $input = new LogisticStockBulkSugarFromCane;
        } else if($flag == 'sugarFromRs'){
            // input data ke logistik
            $input = new LogisticStockBulkSugarFromRs;
        } else {
            return false;
        }        

        $input->date = $data->date;
        if($flag == 'milledSugarCane' || $flag == 'processedRs') {
            // simpan data proses production
            $input->volume = strval($data->volume - ($data->volume*(6.97 / 100)));
            $input->save();

            // simpan data logistik
            $logistik->date = $data->date;
            $logistik->volume = strval($input->volume);
            $logistik->save();
        } else if($flag == 'sugarCane' || $flag == 'sugarFromRs') {
            // simpan data ke logistik
            $input->volume = $data->volume;
            $input->save();
        } else {
            return false;
        }

        // input summary logistik
        $cekLogistik = Logistic::where('date', 'like', '%'.$data->date.'%')->first(); // check apakahsudah ada data dengan tanggal yang sama dengan yang diinput
        if($cekLogistik) {
            $summaryLogistik = $cekLogistik;
        } else {
            $summaryLogistik = new Logistic;
        }

        $sbsfc = LogisticStockBulkSugarFromCane::where('date', 'like', '%'.$data->date.'%')->sum('volume');
        $sbsfrs = LogisticStockBulkSugarFromRs::where('date', 'like', '%'.$data->date.'%')->sum('volume');

        $summaryLogistik->date = $data->date;
        $summaryLogistik->stock_bulk_sugar_from_cane = $sbsfc;
        $summaryLogistik->stock_bulk_sugar_from_rs = $sbsfrs;
        $summaryLogistik->stock_out_bulk_sugar += 0;
        $summaryLogistik->return_bulk_sugar += 0;
        $summaryLogistik->save();
        // end summary logistik

        // input stok
        $latestStok = StokSugar::orderBy('created_at', 'desc')->first();

        $stok = new StokSugar;
        $stok->date = $data->date;

        if($flag == 'milledSugarCane'){
            $stok->proses = 'Production Milled Sugar Cane (Stok Bertambah)';
        } else if($flag == 'sugarCane') {
            $stok->proses = 'Production Sugar Cane (Stok Bertambah)';
        } else if($flag == 'processedRs') {
            $stok->proses = 'Production Processed Raw Sugar (Stok Bertambah)';
        } else {
            $stok->proses = 'Production Raw Sugar (Stok Bertambah)';
        }

        if($logistik) {
            if($flag == 'milledSugarCane' || $flag == 'sugarCane') { // jika yang diinput sugar cane
                $stok->cane = $latestStok->cane + $logistik->volume;
                $stok->rs = $latestStok->rs + 0;
            } else { // jika yang diinput raw sugar
                $stok->cane = $latestStok->cane + 0;
                $stok->rs = $latestStok->rs + $logistik->volume;
            }
            $stok->volume = $logistik->volume;
        } else {
            if($flag == 'processedRs' || $flag == 'sugarFromRs') { // jika yang diinput raw sugar
                $stok->cane = $latestStok->cane + 0;
                $stok->rs = $latestStok->rs + $data->volume;
            } else { // jika yang diinput sugar cane
                $stok->cane = $latestStok->cane + $data->volume;
                $stok->rs = $latestStok->rs + 0;
            }
            $stok->volume = $data->volume;
        }
        $stok->save();

        $hasil = compact(
            'input',
            'logistik'
        );

        return $hasil;
    }

    public function add(Request $request) {
        // input data production
        if($request->param == 'milledSugarCane'){
            $production = new ProductionMilledSugarCane();
        } else if($request->param == 'processedRs'){
            $production = new ProductionProcessedRs();
        } else if($request->param == 'sugarCane'){
            $production = new ProductionSugarCane();
        } else if($request->param == 'sugarFromRs'){
            $production = new ProductionSugarFromRS();
        } else {
            return false;
        }
        $production->date = $request->date;
        $production->volume = $request->volume;
        $production->save();

        // input proses production
        // $this->inputProsesProduction($request->param, $production);
        $hasil = $this->inputProsesProduction($request->param, $production);

        // insert for summary production
        $milledSugarCane = ProductionMilledSugarCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $processedRs = ProductionProcessedRs::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $sugarCane = ProductionSugarCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $sugarFromRs = ProductionSugarFromRs::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        // check data production
        $check = Production::where('date', 'like', '%'.$request->date.'%')->first(); // check apakahsudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            $summary = new Production;
        }
        $summary->date = $request->date;
        $summary->milled_sugar_cane = $milledSugarCane;
        $summary->processed_rs = $processedRs;
        $summary->sugar_cane = $sugarCane;
        $summary->sugar_from_rs = $sugarFromRs;
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['input']    = $hasil['input'];
        $success['logistik'] = $hasil['logistik'];
        $success['data']     = $production;

        return response()->json($success, $this->successStatus);
    }

    public function summary() {
        $this->successStatus = 200;
        $success['success'] = true;

        $interval = 0;
        $valDate = [];
        $valMSC = [];
        $valPRS = [];
        $valSC = [];
        $valSFRS = [];

        $akhir = Production::orderBy('date', 'desc')->first()->date;

        // ambil data per 2 minggu
        foreach(Production::orderBy('date', 'asc')->get() as $d){ // urutkan ascending
            $i = $interval++;
            // setting agar tiap 14x looping maka interval menjadi 0
            if ($i == 13){
                $interval = 0;
            }

            // setting jika interval 0 maka ambil data
            if ($i == 0 || $d->date == $akhir){
                array_push($valDate, date('d-M-Y', strtotime($d->date)));
                array_push($valMSC, $d->milled_sugar_cane);
                array_push($valPRS, $d->processed_rs);
                array_push($valSC, $d->sugar_cane);
                array_push($valSFRS, $d->sugar_from_rs);
            }
        }
        
        // $success['summaryProduction'] = Production::all();
        $success['summaryProduction'] = Production::orderBy('date', 'desc')->get();
        $success['date'] = $valDate;
        $success['msc'] = $valMSC;
        $success['prs'] = $valPRS;
        $success['sc'] = $valSC;
        $success['sfrs'] = $valSFRS;

        return response()->json($success, $this->successStatus);
    }

    public function addHash(Request $request) {
        $insert = new Transaction();
        $insert->user = Auth::user()->username;
        $insert->wallet = $request->wallet;
        $insert->transaksi_hash = $request->transaction;
        $insert->flag = 'production';
        if($request->flag == 'milledSugarCane'){
            $cariTransaksi = ProductionMilledSugarCane::find($request->id);
            $insert->jenis_transaksi = 'Milled Sugar Cane';
        } else if($request->flag == 'processedRs'){
            $cariTransaksi = ProductionProcessedRs::find($request->id);
            $insert->jenis_transaksi = 'Processed RS';
        } else if($request->flag == 'sugarCane') {
            $cariTransaksi = ProductionSugarCane::find($request->id);
            $insert->jenis_transaksi = 'Sugar Cane';
        } else if($request->flag == 'sugarFromRs'){
            $cariTransaksi = ProductionSugarFromRS::find($request->id);
            $insert->jenis_transaksi = 'Sugar From RS';
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

    public function delete($flag, $id) {
        if($flag == 'msc') {
            $hapus = ProductionMilledSugarCane::find($id)->delete();
            return $hapus;
        } else if($flag == 'prs') {
            $hapus = ProductionProcessedRs::find($id)->delete();
            return $hapus;
        } else if($flag == 'sc') {
            $hapus = ProductionSugarCane::find($id)->delete();
            return $hapus;
        } else if($flag == 'sfrs') {
            $hapus = ProductionSugarFromRS::find($id)->delete();
            return $hapus;
        } else {
            return false;
        }
    }

    public function edit(Request $request) {
        if($request->flag == 'msc'){
            $data = ProductionMilledSugarCane::find($request->id);
        } else if($request->flag == 'prs') {
            $data = ProductionProcessedRs::find($request->id);
        } else if($request->flag == 'sc' || $request->flag == 'sfc') {
            $data = ProductionSugarCane::find($request->id);
        } else {
            $data = ProductionSugarFromRS::find($request->id);
        }
        $data->date = $request->date;
        $data->volume = $request->volume;
        $data->save();

        // insert for summary production
        $msc = ProductionMilledSugarCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $prs = ProductionProcessedRs::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $sfc = ProductionSugarCane::where('date', 'like', '%'.$request->date.'%')->sum('volume');
        $sfrs = ProductionSugarFromRS::where('date', 'like', '%'.$request->date.'%')->sum('volume');        

        // check data production
        $check = Production::where('date', 'like', '%'.$request->date.'%')->first(); // check apakah sudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            $summary = new Production;
        }
        // insert data summary
        $summary->date = $request->date;
        $summary->milled_sugar_cane = $msc;
        $summary->processed_rs = $prs;
        $summary->sugar_cane = $sfc;
        $summary->sugar_from_rs = $sfrs;
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $data;

        return response()->json($success, $this->successStatus);
    }

    public function indexEdit($flag, $id) {
        if($flag == 'msc'){
            $cariData = ProductionMilledSugarCane::find($id);
        } else if($flag == 'prs') {
            $cariData = ProductionProcessedRs::find($id);
        } else if($flag == 'sfc') {
            $cariData = ProductionSugarCane::find($id);
        } else {
            $cariData = ProductionSugarFromRS::find($id);
        }
        
        $success['id']       = $id;
        $success['flag']     = $flag;
        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $cariData;

        return response()->json($success, $this->successStatus);
    }
}
