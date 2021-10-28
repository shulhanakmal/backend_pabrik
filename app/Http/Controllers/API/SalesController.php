<?php

namespace App\Http\Controllers\API;

use DB;
use Auth;
use App\Sales;
use App\Logistic;
use App\StokSugar;
use App\Transaction;
use App\SummarySales;
use Illuminate\Http\Request;
use App\LogisticStockOutBulkSugar;
use App\Http\Controllers\Controller;

class SalesController extends Controller {
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
        // $success['sales'] = Sales::all();
        $success['sales'] = Sales::orderBy('created_at', 'desc')->get();
        return response()->json($success, $this->successStatus);
    }

    public function add(Request $request) {
        $sales = new Sales;
        $sales->date = $request->date;
        $sales->no_do = $request->no_do;
        $sales->buyer = $request->buyer;
        $sales->price = $request->price;
        if($request->sugar == 'cane'){
            $sales->mount_sugar_sold_cane = $request->volume;
            $sales->mount_sugar_sold_rs = 0;
        } else if($request->sugar == 'rs') {
            $sales->mount_sugar_sold_cane = 0;
            $sales->mount_sugar_sold_rs = $request->volume;
        } else {
            return false;
        }
        $sales->save();

        // add data stock out bulk sugar
        $sobs = new LogisticStockOutBulkSugar;
        $sobs->date = $sales->date;
        $sobs->volume = $request->volume;
        $sobs->sugar = $request->sugar;
        $sobs->save();

        // update stok
        $currentStok = StokSugar::latest()->first();

        $stok = new StokSugar;
        $stok->date = $sales->date;
        if($request->sugar == 'rs'){
            $stok->rs = $currentStok->rs - $request->volume;
            $stok->cane = $currentStok->cane - 0;
            $stok->proses = 'Sales Raw Sugar (Stok Berkurang)';
        } else {
            $stok->rs = $currentStok->rs - 0;
            $stok->cane = $currentStok->cane - $request->volume;
            $stok->proses = 'Sales Sugar Cane (Stok Berkurang)';
        }
        $stok->volume = $request->volume;
        $stok->save();

        // input summary logistik
        $cekLogistik = Logistic::where('date', 'like', '%'.$request->date.'%')->first(); // check apakahsudah ada data dengan tanggal yang sama dengan yang diinput
        if($cekLogistik) {
            $summaryLogistik = $cekLogistik;
        } else {
            $summaryLogistik = new Logistic;
        }

        $summarySobs = LogisticStockOutBulkSugar::where('date', 'like', '%'.$request->date.'%')->sum('volume');

        $summaryLogistik->date = $request->date;
        $summaryLogistik->stock_bulk_sugar_from_cane += 0;
        $summaryLogistik->stock_bulk_sugar_from_rs += 0;
        $summaryLogistik->stock_out_bulk_sugar += $summarySobs;
        $summaryLogistik->return_bulk_sugar += 0;
        $summaryLogistik->save();
        // end summary logistik

        // summary
        // check data Sales
        $check = SummarySales::where('date', 'like', '%'.$request->date.'%')->first(); // check apakah sudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            $summary = new SummarySales;
        }

        // insert for summary Sales
        $price = Sales::where('date', 'like', '%'.$request->date.'%')->sum('price');
        $cane = Sales::where('date', 'like', '%'.$request->date.'%')->sum('mount_sugar_sold_cane');
        $rs = Sales::where('date', 'like', '%'.$request->date.'%')->sum('mount_sugar_sold_rs');

        // update database
        $summary->date = $request->date;
        $summary->price = $price;
        $summary->cane = $cane;
        $summary->rs = $rs;
        if($summary->rs > 0 && $summary->cane > 0) {
            $summary->provenue = ($summary->rs + $summary->cane) * $summary->price;
        } else {
            if($summary->rs < 1) {
                $summary->provenue = $summary->cane * $summary->price;
            } else {
                $summary->provenue = $summary->rs * $summary->price;
            }
        }
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $sales;

        return response()->json($success, $this->successStatus);
    }

    public function summary() {
        $arr = [];
        $valCane = [];
        $valRs = [];
        $valDate = [];

        foreach(SummarySales::select(DB::raw('date'))->distinct()->orderBy('date', 'asc')->get()->pluck('date') as $d){
            array_push($valCane, SummarySales::where('date', $d)->orderBy('date', 'desc')->sum('cane'));
            array_push($valRs, SummarySales::where('date', $d)->orderBy('date', 'desc')->sum('rs'));
            array_push($valDate, date('d-M-Y', strtotime($d)));
        }

        // pie chart
        $buyer = Sales::select(DB::raw('buyer'))->orderBy('buyer', 'asc')->get()->count();
        $pt = Sales::where('buyer', 'like', '%, PT%')->orderBy('buyer', 'asc')->get()->count();
        $cv = Sales::where('buyer', 'like', '%, CV%')->orderBy('buyer', 'asc')->get()->count();
        $koperasi = Sales::where('buyer', 'like', '%koperasi%')->orderBy('buyer', 'asc')->get()->count();
        $perkumpulan = Sales::where('buyer', 'like', '%perkumpulan%')->orderBy('buyer', 'asc')->get()->count();
        $individu = Sales::where('buyer', 'not like', '%perkumpulan%')->
        where('buyer', 'not like', '%, PT%')->
        where('buyer', 'not like', '%, CV%')->
        where('buyer', 'not like', '%Koperasi%')->
        orderBy('buyer', 'asc')->get()->count();

        $piePT = $pt / $buyer * 100;
        $pieCV = $cv / $buyer * 100;
        $pieKoperasi = $koperasi / $buyer * 100;
        $piePerkumpulan = $perkumpulan / $buyer * 100;
        $pieIndividu = $individu / $buyer * 100;

        $pieVal = [round($piePT), round($pieCV), round($pieKoperasi), round($piePerkumpulan), round($pieIndividu)];
        $pieBuyer = ['pt', 'cv', 'koperasi', 'perkumpulan', 'individu'];

        $this->successStatus = 200;
        $success['success'] = true;
        $success['pieVal'] = $pieVal;
        $success['pieBuyer'] = $pieBuyer;
        // $success['summarySales'] = SummarySales::alls();
        $success['summarySales'] = SummarySales::orderBy('date', 'desc')->get();
        $success['date'] = $valDate;
        $success['cane'] = $valCane;
        $success['rs'] = $valRs;

        return response()->json($success, $this->successStatus);
    }

    public function addHash(Request $request) {
        $insert = new Transaction();
        $insert->user = Auth::user()->username;
        $insert->wallet = $request->wallet;
        $insert->transaksi_hash = $request->transaction;
        $insert->jenis_transaksi = 'Sales';
        $insert->flag = 'sales';
        
        $cariTransaksi = Sales::find($request->id);

        if($cariTransaksi){
            $insert->transaksi_id = $request->id;
        }

        $insert->save();        

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $insert;

        return response()->json($success, $this->successStatus);
    }

    public function edit(Request $request) {
        $salesBefore = Sales::find($request->id);
        $dataSummary = SummarySales::where('date', $salesBefore->date)->first();
        $sales = Sales::find($request->id);
        
        // update data sales
        $sales->date = $request->date;
        $sales->no_do = $request->no_do;
        $sales->buyer = $request->buyer;
        $sales->price = $request->price;
        if($request->sugar == 'cane'){
            $sales->mount_sugar_sold_cane = $request->volume;
            $sales->mount_sugar_sold_rs = 0;
        } else if($request->sugar == 'rs') {
            $sales->mount_sugar_sold_cane = 0;
            $sales->mount_sugar_sold_rs = $request->volume;
        } else {
            return false;
        }
        $sales->save();
        // end update data

        // check summary production
        $check = SummarySales::where('date', 'like', '%'.$request->date.'%')->first(); // check apakah sudah ada data dengan tanggal yang sama dengan yang diinput
        if($check){ // jika data sudah ada
            $summary = $check;
        } else {
            // summary data lama dikurangi dan dibuat data baru
            $dataSummary->price = $dataSummary->price - $salesBefore->price;
            $dataSummary->cane = $dataSummary->cane - $salesBefore->mount_sugar_sold_cane;
            $dataSummary->rs = $dataSummary->rs - $salesBefore->mount_sugar_sold_rs;
            if($dataSummary->rs > 0 && $dataSummary->cane > 0) {
                $dataSummary->provenue = ($dataSummary->rs + $dataSummary->cane) * $dataSummary->price;
            } else {
                if($dataSummary->rs < 1) {
                    $dataSummary->provenue = $dataSummary->cane * $dataSummary->price;
                } else {
                    $dataSummary->provenue = $dataSummary->rs * $dataSummary->price;
                }
            }
            $dataSummary->save();
            // buat summary baru
            $summary = new SummarySales;
        }

        // insert for summary Sales
        $price = Sales::where('date', 'like', '%'.$request->date.'%')->sum('price');
        $cane = Sales::where('date', 'like', '%'.$request->date.'%')->sum('mount_sugar_sold_cane');
        $rs = Sales::where('date', 'like', '%'.$request->date.'%')->sum('mount_sugar_sold_rs');

        // update database
        $summary->date = $request->date;
        $summary->price = $price;
        $summary->cane = $cane;
        $summary->rs = $rs;
        if($summary->rs > 0 && $summary->cane > 0) {
            $summary->provenue = ($summary->rs + $summary->cane) * $summary->price;
        } else {
            if($dataSummary->rs < 1) {
                $summary->provenue = $summary->cane * $summary->price;
            } else {
                $summary->provenue = $summary->rs * $summary->price;
            }
        }
        $summary->save();
        // end summary

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $sales;

        return response()->json($success, $this->successStatus);
    }

    public function indexEdit($id) {
        $cariData = Sales::find($id);
        if($cariData->mount_sugar_sold_cane < 1) {
            $flag = 'rs';
        } else {
            $flag = 'cane';
        }
        
        $success['id']       = $id;
        $success['flag']     = $flag;
        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $cariData;

        return response()->json($success, $this->successStatus);
    }
}
