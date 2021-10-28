<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Transaction;
use App\StokSugar;
use Auth;
use DB;

class DashboardController extends Controller {
    public $successStatus = 401;

    function __construct() {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function index() {
        $this->successStatus = 200;

        $user = Auth::user();

        $valLineVol = [];
        $valLineDate = [];

        // production
        $pieMsc = Transaction::where('flag', 'production')->where('jenis_transaksi', 'Milled Sugar Cane')->get()->count();
        $piePrs = Transaction::where('flag', 'production')->where('jenis_transaksi', 'Processed RS')->get()->count();
        $pieSc = Transaction::where('flag', 'production')->where('jenis_transaksi', 'Sugar Cane')->get()->count();
        $pieSfrs = Transaction::where('flag', 'production')->where('jenis_transaksi', 'Sugar From RS')->get()->count();
        $lineDateProduction = Transaction::where('flag', 'production')->select(DB::raw('DATE(`created_at`) as DATE')) ->distinct()->get();
        // end production

        // logistics
        $pieSbsfc = Transaction::where('flag', 'logistics')->where('jenis_transaksi', 'Stock Bulk Sugar From Cane')->get()->count();
        $pieSbsfrs = Transaction::where('flag', 'logistics')->where('jenis_transaksi', 'Stock Bulk Sugar From RS')->get()->count();
        $pieSobs = Transaction::where('flag', 'logistics')->where('jenis_transaksi', 'Stock Out Bulk Sugar')->get()->count();
        $pieRbs = Transaction::where('flag', 'logistics')->where('jenis_transaksi', 'Return Bulk Sugar')->get()->count();
        $lineDateLogistics = Transaction::where('flag', 'logistics')->select(DB::raw('DATE(`created_at`) as DATE')) ->distinct()->get();
        // end logistics

        // sales
        $pieSales = Transaction::where('flag', 'sales')->where('jenis_transaksi', 'Stock Bulk Sugar From Cane')->get()->count();
        $lineDateSales = Transaction::where('flag', 'sales')->select(DB::raw('DATE(`created_at`) as DATE')) ->distinct()->get();
        // end sales

        if($user->role == 'production') {
            $success['userRole'] = 'production';

            // pie
            $pieFlag = ['Milled Sugar Cane', 'Processed RS', 'Sugar Cane', 'Sugar From RS'];
            $pieVol = [$pieMsc, $piePrs, $pieSc, $pieSfrs];
            // end pie

            // line
            foreach($lineDateProduction as $d) {
                $cari = Transaction::where('flag', 'production')->where('created_at', 'like', '%'.$d->DATE.'%')->count();
                array_push($valLineDate, date('d-M-Y', strtotime($d->DATE)));
                array_push($valLineVol, $cari);
            }
            // end line
        } else if($user->role == 'logistics') {
            $success['userRole'] = 'logistics';

            // pie
            $pieFlag = ['Stock Bulk Sugar From Cane', 'Stock Bulk Sugar From RS', 'Stock Out Bulk Sugar', 'Return Bulk Sugar'];
            $pieVol = [$pieSbsfc, $pieSbsfrs, $pieSobs, $pieRbs];
            // end pie

            // line
            foreach($lineDateLogistics as $d) {
                $cari = Transaction::where('flag', 'logistics')->where('created_at', 'like', '%'.$d->DATE.'%')->get()->count();
                array_push($valLineDate, date('d-M-Y', strtotime($d->DATE)));
                array_push($valLineVol, $cari);
            }
            // end line
        } else if($user->role == 'sales') {
            $success['userRole'] = 'sales';

            // pie
            $pieFlag = ['sales'];
            $pieVol = [$pieSales];
            // end pie

            // line
            foreach($lineDateSales as $d) {
                $cari = Transaction::where('flag', 'sales')->where('created_at', 'like', '%'.$d->DATE.'%')->get()->count();
                array_push($valLineDate, date('d-M-Y', strtotime($d->DATE)));
                array_push($valLineVol, $cari);
            }
            // end line
        } else {
            $success['userRole'] = 'admin';

            $valAdminLineDateProduction = [];
            $valAdminLineVolProduction = [];
            $valAdminLineDateLogistics = [];
            $valAdminLineVolLogistics = [];
            $valAdminLineVolSales = [];
            $valAdminLineDateSales = [];

            // production
            // pie chart
            $pieFlagProduction = ['Milled Sugar Cane', 'Processed RS', 'Sugar Cane', 'Sugar From RS'];
            $pieVolProduction = [$pieMsc, $piePrs, $pieSc, $pieSfrs];
            // end pie chart
            // line chart
            foreach($lineDateProduction as $d) {
                $cari = Transaction::where('flag', 'production')->where('created_at', 'like', '%'.$d->DATE.'%')->get()->count();
                array_push($valAdminLineDateProduction, date('d-M-Y', strtotime($d->DATE)));
                array_push($valAdminLineVolProduction, $cari);
            }
            // end line chart
            // end production

            // logistics
            // pie chart
            $pieFlagLogistics = ['Stock Bulk Sugar From Cane', 'Stock Bulk Sugar From RS', 'Stock Out Bulk Sugar', 'Return Bulk Sugar'];
            $pieVolLogistics = [$pieSbsfc, $pieSbsfrs, $pieSobs, $pieRbs];
            // end pie chart
            // line chart
            foreach($lineDateLogistics as $d) {
                $cari = Transaction::where('flag', 'logistics')->where('created_at', 'like', '%'.$d->DATE.'%')->get()->count();
                array_push($valAdminLineDateLogistics, date('d-M-Y', strtotime($d->DATE)));
                array_push($valAdminLineVolLogistics, $cari);
            }
            // end line chart
            // end logistics

            // sales
            // pie chart
            $pieFlagSales = ['sales'];
            $pieVolSales = [$pieSales];;
            // end pie chart
            // line chart
            foreach($lineDateSales as $d) {
                $cari = Transaction::where('flag', 'sales')->where('created_at', 'like', '%'.$d->DATE.'%')->get()->count();
                array_push($valAdminLineDateSales, date('d-M-Y', strtotime($d->DATE)));
                array_push($valAdminLineVolSales, $cari);
            }
        }

        $production = Transaction::where('flag', 'production')->orderBy('created_at', 'desc')->get();
        $logistics = Transaction::where('flag', 'logistics')->orderBy('created_at', 'desc')->get();
        $sales = Transaction::where('flag', 'sales')->orderBy('created_at', 'desc')->get();

        $success['productionData'] = $production;
        $success['logisticsData'] = $logistics;
        $success['salesData'] = $sales;
        // data chart
        if($user->role == 'admin'){
            // pie
            $success['valAdminPieFlagProduction'] = $pieFlagProduction;
            $success['valAdminPieVolProduction'] = $pieVolProduction;
            $success['valAdminPieFlagLogistics'] = $pieFlagLogistics;
            $success['valAdminPieVolLogistics'] = $pieVolLogistics;
            $success['valAdminPieFlagSales'] = $pieFlagSales;
            $success['valAdminPieVolSales'] = $pieVolSales;
            // end pie
            // line
            $success['valAdminLineDateProduction'] = $valAdminLineDateProduction;
            $success['valAdminLineVolProduction'] = $valAdminLineVolProduction;
            $success['valAdminLineDateLogistics'] = $valAdminLineDateLogistics;
            $success['valAdminLineVolLogistics'] = $valAdminLineVolLogistics;
            $success['valAdminLineDateSales'] = $valAdminLineDateSales;
            $success['valAdminLineVolSales'] = $valAdminLineVolSales;
            $success['valLineDate'] = null;
            $success['valLineVol'] = null;
            $success['valPieFlag'] = null;
            $success['valPieVol'] = null;
        } else {
            // pie
            $success['valAdminPieFlagProduction'] = null;
            $success['valAdminPieVolProduction'] = null;
            $success['valAdminPieFlagLogistics'] = null;
            $success['valAdminPieVolLogistics'] = null;
            $success['valAdminPieFlagSales'] = null;
            $success['valAdminPieVolSales'] = null;
            // end pie
            // line
            $success['valAdminLineDateProduction'] = null;
            $success['valAdminLineVolProduction'] = null;
            $success['valAdminLineDateLogistics'] = null;
            $success['valAdminLineVolLogistics'] = null;
            $success['valAdminLineDateSales'] = null;
            $success['valAdminLineVolSales'] = null;
            $success['valLineDate'] = $valLineDate;
            $success['valLineVol'] = $valLineVol;
            $success['valPieFlag'] = $pieFlag;
            $success['valPieVol'] = $pieVol;
        }
        // end data chart
        $success['userRole'] = $user->role;
        $success['success'] = true;
        $success['user'] = $user;

        return response()->json($success, $this->successStatus);
    }

    public function stock() {
        $this->successStatus = 200;
        $success['success'] = true;

        $stock = StokSugar::orderBy('created_at', 'desc')->get();

        $success['stock'] = $stock;

        return response()->json($success, $this->successStatus);
    }
}