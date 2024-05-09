<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\BusinessLocation;
use App\Transaction;
use App\Contact;
use App\Product;
use App\VariationLocationDetails;
use App\contractor_rates;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;

use App\Variation;
use App\Business;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Utils\ManufacturingUtil;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Yajra\DataTables\Facades\DataTables;

class ProductionController extends Controller
{
    /**
     * All Utils instance.
     *
     */
    protected $moduleUtil;
    protected $productUtil;
    protected $transactionUtil;
    protected $mfgUtil;
    protected $businessUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, ProductUtil $productUtil, TransactionUtil $transactionUtil, ManufacturingUtil $mfgUtil, BusinessUtil $businessUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->mfgUtil = $mfgUtil;
        $this->businessUtil = $businessUtil;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $productions = Transaction::join(
                'business_locations AS bl',
                'transactions.location_id',
                '=',
                'bl.id'
            )->leftJoin('purchase_lines as pl', 'pl.transaction_id', '=', 'transactions.id')
                ->leftJoin('units as su', 'pl.sub_unit_id', '=', 'su.id')
                ->leftJoin('variations as v', 'v.id', '=', 'pl.variation_id')
                ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
                // ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('products as p', 'p.id', '=', 'pl.product_id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'production_purchase')
                ->whereNull('transactions.mfg_is_multiproduction')
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'ref_no',
                    'bl.name as location_name',
                    'p.name as product_name',
                    'pl.quantity',
                    'final_total',
                    'su.short_name as sub_unit_name',
                    'su.base_unit_multiplier',
                    'u.short_name as unit_name',
                    'mfg_is_final'
                )->groupBy('transactions.id');

            return Datatables::of($productions)
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="' .  action('\Modules\Manufacturing\Http\Controllers\ProductionController@show', $row->id) . '" class="btn btn-info btn-xs btn-modal btn-vew" data-container=".view_modal"><i class="fa fa-eye"></i></button>';
                    // if ($row->mfg_is_final == 0) {
                        $html .= ' <a href="' .  action('\Modules\Manufacturing\Http\Controllers\ProductionController@edit', $row->id) . '" class="btn btn-primary btn-xs btn-edt"><i class="fa fa-edit"></i></a>';

                        $html .= ' <button data-href="' . action('\Modules\Manufacturing\Http\Controllers\ProductionController@destroy', [$row->id]) . '" class="delete-production btn btn-xs btn-danger btn-dlt"><i class="fa fa-trash"></i></button>';
                    // }

                    return $html;
                })
                ->editColumn(
                    'final_total',
                    '<span class="display_currency final_total" data-currency_symbol="true" data-orig-value="{{$final_total}}">{{$final_total}}</span>'
                )
                ->editColumn(
                    'quantity',
                    function ($row) {
                        $qty = empty($row->base_unit_multiplier) ? $row->quantity : $row->quantity / $row->base_unit_multiplier;
                        $unit = empty($row->sub_unit_name) ? $row->unit_name : $row->sub_unit_name;
                        return "<span class='display_currency' data-currency_symbol='false' data-orig-value='$qty'>$qty</span> $unit";
                    }
                )
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->rawColumns(['final_total', 'action', 'quantity'])
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->whereRaw("p.name like ?", ["%{$keyword}%"]);
                })
                ->make(true);
        }

        return view('manufacturing::production.index');
    }





    public function multiproduction_index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            
            $productions = Transaction::join(
                'business_locations AS bl',
                'transactions.location_id',
                '=',
                'bl.id'
            )->leftJoin('purchase_lines as pl', 'pl.transaction_id', '=', 'transactions.id')
                ->leftJoin('units as su', 'pl.sub_unit_id', '=', 'su.id')
                ->leftJoin('business_locations as lo', 'bl.location_id', '=', 'lo.id')
                ->leftJoin('variations as v', 'v.id', '=', 'pl.variation_id')
                ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
                ->leftJoin('products as p', 'p.id', '=', 'pl.product_id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('transactions.business_id', $business_id)
                ->when(request('type') == 'stock_adjustment', function ($query) {
                   $query->where('transactions.type', 'stock_adjustment');
                }, function($query) {
                    $query->where('transactions.type', 'production_purchase')
                        ->where('transactions.mfg_is_multiproduction', 1);
                })
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'ref_no',
                    'p.name as product_name',
                    'bl.name as location_name',
                    'pl.quantity',
                    'final_total',
                    'lo.name as name',
                    'su.short_name as sub_unit_name',
                    'su.base_unit_multiplier',
                    'u.short_name as unit_name',
                    'mfg_is_final'
                )->groupBy('transactions.id')->orderby('transaction_date', 'desc');

            return Datatables::of($productions)
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="' .  action('\Modules\Manufacturing\Http\Controllers\ProductionController@show', $row->id) . '" class="btn btn-info btn-xs btn-modal btn-vew" data-container=".view_modal"><i class="fa fa-eye"></i></button>';
                    
                        $html .= ' <a href="' .  action('\Modules\Manufacturing\Http\Controllers\ProductionController@edit', $row->id) . '" class="btn btn-primary btn-xs btn-edt"><i class="fa fa-edit"></i></a>';

                        $html .= ' <button data-href="' . action('\Modules\Manufacturing\Http\Controllers\ProductionController@destroy', [$row->id]) . '" class="delete-production btn btn-xs btn-danger btn-dlt"><i class="fa fa-trash"></i></button>';
                    
                    return $html;
                })
                ->editColumn(
                    'final_total',
                    '<span class="display_currency final_total" data-currency_symbol="true" data-orig-value="{{$final_total}}">{{$final_total}}</span>'
                )
                ->editColumn(
                    'quantity',
                    function ($row) {
                        $qty = empty($row->base_unit_multiplier) ? $row->quantity : $row->quantity / $row->base_unit_multiplier;
                        $unit = empty($row->sub_unit_name) ? $row->unit_name : $row->sub_unit_name;
                        return "<span class='display_currency' data-currency_symbol='false' data-orig-value='$qty'>$qty</span> $unit";
                    }
                )
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->rawColumns(['final_total', 'action', 'quantity'])
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->whereRaw("p.name like ?", ["%{$keyword}%"]);
                })
                ->make(true);
        }

        return view('manufacturing::production.multiproduction_index');
    }




    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $recipe_dropdown = MfgRecipe::forDropdown($business_id);
        $contractor = Contact::where('type', 'contracter')->get();
        
        $getprefix = Business::first();
        $prefix = $getprefix->ref_no_prefixes['production'];
        
        $trans_no = DB::table('transactions')
        ->where('ref_no', 'like', $prefix .'%')
        ->select('ref_no', DB::raw("substring_index(substring_index(ref_no,'-',-1),',',-1) as max_no"))->get()
        ->max('max_no');
        if(empty($trans_no)){
            $trans_no_ = 1;
        }else{
            $break_no = explode("-",$trans_no);
            $trans_no_ = end($break_no)+1;
        }
        $ref_no = $prefix.'-'.$trans_no_;

        return view('manufacturing::production.create')
            ->with(compact('business_locations', 'recipe_dropdown', 'contractor','ref_no'));
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        if ($request->input('check')) {
            foreach ($request->input('ingredients') as $key => $value) {
                $mfg_ing = MfgRecipeIngredient::where('id', $key)->first();
                $mfg_ing->variation_id = $value['variation_id'];
                $mfg_ing->save();
            }
        }
        $business_id = $request->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $request->validate([
                'transaction_date' => 'required',
                'location_id' => 'required',
                'final_total' => 'required'
            ]);

            //Create Production purchase
            $manufacturing_settings = $this->mfgUtil->getSettings($business_id);
            $user_id = $request->session()->get('user.id');
            $transaction_data = $request->only(['ref_no', 'transaction_date', 'location_id', 'final_total', 'contractor', 'contractor_rates']);
            $is_final = !empty($request->input('finalize')) ? 1 : 0;
            $transaction_data['business_id'] = $business_id;
            $transaction_data['created_by'] = $user_id;
            $transaction_data['type'] = 'production_purchase';
            $transaction_data['status'] = $is_final ? 'received' : 'pending';
            $transaction_data['payment_status'] = 'due';
            $transaction_data['transaction_date'] = $this->productUtil->uf_date($transaction_data['transaction_date'], false);
            $transaction_data['final_total'] = $this->productUtil->num_uf($transaction_data['final_total']);
            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount($transaction_data['type']);
            //Generate reference number
            if (empty($transaction_data['ref_no'])) {
                $prefix = !empty($manufacturing_settings['ref_no_prefix']) ? $manufacturing_settings['ref_no_prefix'] : null;
                $transaction_data['ref_no'] = $this->productUtil->generateReferenceNumber($transaction_data['type'], $ref_count, null, $prefix);
            }
            $variation_id = $request->input('variation_id');
            $variation = Variation::where('id', $variation_id)
                ->with(['product'])
                ->first();
            $final_total = $request->input('final_total');
            $quantity = $request->input('quantity');
            $waste_units = $this->productUtil->num_uf($request->input('mfg_wasted_units'));
            $uf_qty = $this->productUtil->num_uf($quantity);
            if (!empty($waste_units)) {
                $new_qty = $uf_qty - $waste_units;
                $uf_qty = $new_qty;
                $quantity = $this->productUtil->num_f($new_qty);
            }

            $final_total_uf = $this->productUtil->num_uf($final_total);
            $unit_purchase_line_total = $final_total_uf / $uf_qty;
            $unit_purchase_line_total_f = $this->productUtil->num_f($unit_purchase_line_total);
            $transaction_data['mfg_wasted_units'] = $waste_units;
            $transaction_data['mfg_production_cost'] = $this->productUtil->num_uf($request->input('production_cost'));
            $transaction_data['mfg_production_cost_type'] = $request->input('mfg_production_cost_type');
            $transaction_data['mfg_is_final'] = $is_final;
            $purchase_line_data = [
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'product_id' => $variation->product_id,
                'product_unit_id' => $variation->product->unit_id,
                'pp_without_discount' => $unit_purchase_line_total_f,
                'discount_percent' => 0,
                'purchase_price' => $unit_purchase_line_total_f,
                'purchase_price_inc_tax' => $unit_purchase_line_total_f,
                'item_tax' => 0,
                'purchase_line_tax_id' => null,
                'mfg_date' => $this->transactionUtil->format_date($transaction_data['transaction_date'])
            ];
            if (request()->session()->get('business.enable_lot_number') == 1) {
                $purchase_line_data['lot_number'] = $request->input('lot_number');
            }

            if (request()->session()->get('business.enable_product_expiry') == 1) {
                $purchase_line_data['exp_date'] = $request->input('exp_date');
            }

            if (!empty($request->input('sub_unit_id'))) {
                $purchase_line_data['sub_unit_id'] = $request->input('sub_unit_id');
            }

            // for single production valuation
            if (!empty($request->input('production_total'))) {
                $purchase_line_data['production_total'] = $request->input('production_total');
            }

            DB::beginTransaction();

            $transaction = Transaction::create($transaction_data);
            $transaction->finalize = $request->finalize;

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            $update_product_price = !empty($manufacturing_settings['enable_updating_product_price']) && $is_final ? true : false;

            $this->productUtil->createOrUpdatePurchaseLines($transaction, [$purchase_line_data], $currency_details, $update_product_price, null, $request->input('ingredients'));
            $this->productUtil->adjustStockOverSelling($transaction);

            //Create production sell
            $transaction_sell_data = [
                'business_id' => $business_id,
                'location_id' => $transaction->location_id,
                'transaction_date' => $transaction->transaction_date,
                'created_by' => $transaction->created_by,
                'status' => $is_final ? 'final' : 'draft',
                'type' => 'production_sell',
                'mfg_parent_production_purchase_id' => $transaction->id,
                'payment_status' => 'due',
                'final_total' => $transaction->final_total
            ];

            $sell_lines = [];
            $ingredient_quantities = !empty($request->input('ingredients')) ? $request->input('ingredients') : [];

            //Get ingredient details to create sell lines
            $recipe = MfgRecipe::where('variation_id', $variation_id)->first();

            $all_variation_details = $this->mfgUtil->getIngredientDetails($recipe, $business_id);

            foreach ($all_variation_details as $variation_details) {
                $variation = $variation_details['variation'];

                $line_sub_unit_id = !empty($ingredient_quantities[$variation_details['id']]['sub_unit_id']) ?
                    $ingredient_quantities[$variation_details['id']]['sub_unit_id'] : null;
                $line_multiplier = !empty($line_sub_unit_id) ? $variation_details['sub_units'][$line_sub_unit_id]['multiplier'] : 1;

                $mfg_waste_percent = !empty($ingredient_quantities[$variation_details['id']]['mfg_waste_percent']) ? $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['mfg_waste_percent']) : 0;

                $sell_lines[] = [
                    'product_id' => $variation->product_id,
                    'variation_id' => $variation->id,
                    'quantity' => $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['quantity']),
                    'ing_quantity' => $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['ing_quantity']),
                    'item_tax' => 0,
                    'tax_id' => null,
                    // 'unit_price' => $variation->dpp_inc_tax * $line_multiplier,
                    // 'unit_price_inc_tax' => $variation->dpp_inc_tax * $line_multiplier,
                    'unit_price' => $variation->dpp_inc_tax,
                    'unit_price_inc_tax' => $variation->dpp_inc_tax,
                    'enable_stock' => $variation_details['enable_stock'],
                    'product_unit_id' => $variation->product->unit_id,
                    'sub_unit_id' => $line_sub_unit_id,
                    'base_unit_multiplier' => $line_multiplier,
                    'mfg_waste_percent' => $mfg_waste_percent
                ];
            }

            //Create Sell Transfer transaction
            $production_sell = Transaction::create($transaction_sell_data);

            if (!empty($sell_lines)) {
                $this->transactionUtil->createOrUpdateSellLines($production_sell, $sell_lines, $transaction_sell_data['location_id'], null, null, ['mfg_waste_percent' => 'mfg_waste_percent']);
            }

            if ($production_sell->status == 'final') {
                foreach ($sell_lines as $sell_line) {
                    if ($sell_line['enable_stock']) {
                        $line_qty = $sell_line['quantity'] * $sell_line['base_unit_multiplier'];
                        $this->productUtil->decreaseProductQuantity(
                            $sell_line['product_id'],
                            $sell_line['variation_id'],
                            $production_sell->location_id,
                            $line_qty
                        );
                    }
                }

                $business_details = $this->businessUtil->getDetails($business_id);
                $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

                //Map sell lines with purchase lines
                $business = [
                    'id' => $business_id,
                    'accounting_method' => $request->session()->get('business.accounting_method'),
                    'location_id' => $production_sell->location_id,
                    'pos_settings' => $pos_settings
                ];
                $this->transactionUtil->mapPurchaseSell($business, $production_sell->sell_lines, 'production_purchase');
            }

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.added_success')
            ];
        } catch (Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => $e->getMessage()
            ];
        }

        return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@index')->with('status', $output);
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        $production_purchase = Transaction::where('business_id', $business_id)
            // ->where('type', 'production_purchase')
            ->with([
                'purchase_lines', 'purchase_lines.variations', 'purchase_lines.variations.product_variation', 'purchase_lines.variations.product',
                'purchase_lines.sub_unit', 'purchase_lines.variations.product.unit', 'purchase_lines.product'
            ])
            ->findOrFail($id);
        $production_sell = Transaction::where('business_id', $business_id)
            ->where('type', 'production_sell')
            ->where('mfg_parent_production_purchase_id', $production_purchase->id)
            ->with(['sell_lines', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'sell_lines.variations.product', 'sell_lines.sub_unit'])
            ->first();

        $purchase_line = $production_purchase->purchase_lines[0];
        $base_unit_multiplier = !empty($purchase_line->sub_unit) ? $purchase_line->sub_unit->base_unit_multiplier : 1;
        $quantity = $purchase_line->quantity / 1;
        $quantity_wasted = 0;
        $unit_name = !empty($purchase_line->sub_unit) ?  $purchase_line->sub_unit->short_name : $purchase_line->variations->product->unit->short_name ?? '';
        if (!empty($production_purchase->mfg_wasted_units)) {
            $quantity_wasted = $production_purchase->mfg_wasted_units;
            $quantity += $quantity_wasted;
        }

        $actual_quantity = $quantity * $base_unit_multiplier;

        $ingredients = [];
        $total_ingredients_price = 0;
        //Format sell lines
        if(!empty($production_sell)){
            foreach ($production_sell->sell_lines as $sell_line) {
                $variation = $sell_line->variations;
                $sell_line_qty = empty($sell_line->sub_unit) ? $sell_line->quantity : $sell_line->quantity / $sell_line->sub_unit->base_unit_multiplier;
                $unit = empty($sell_line->sub_unit) ? $variation->product->unit->short_name : $sell_line->sub_unit->short_name;
    
                $line_total_price = $variation->dpp_inc_tax * $sell_line->quantity;
                $total_ingredients_price += $line_total_price;
    
                $waste_percent = !empty($sell_line->mfg_waste_percent) ? $sell_line->mfg_waste_percent : 0;
                $wasted_qty = $this->moduleUtil->calc_percentage($sell_line_qty, $waste_percent);
                $final_quantity = $sell_line_qty - $wasted_qty;
    
                $ingredients[] = [
                    'dpp_inc_tax' => $variation->dpp_inc_tax,
                    'quantity' => $sell_line_qty,
                    'full_name' => $variation->full_name,
                    'id' => $variation->id,
                    'unit' => $unit,
                    'allow_decimal' => $variation->product->unit->allow_decimal,
                    'variation' => $variation,
                    'enable_stock' => $variation->product->enable_stock,
                    'total_price' => $line_total_price,
                    'waste_percent' =>  $waste_percent,
                    'final_quantity' => $final_quantity
                ];
            }
        }
            $total_production_cost = 0;
            if (!empty($production_purchase->mfg_production_cost)) {
                $total_production_cost = $production_purchase->mfg_production_cost;
                if ($production_purchase->mfg_production_cost_type == 'percentage') {
                    $total_production_cost = $this->transactionUtil->calc_percentage($total_ingredients_price, $production_purchase->mfg_production_cost);
                } elseif ($production_purchase->mfg_production_cost_type == 'per_unit') {
                    $total_production_cost = $production_purchase->mfg_production_cost * $quantity;
                }
            }
        
        return view('manufacturing::production.show')->with(compact('production_purchase', 'production_sell', 'purchase_line', 'ingredients', 'unit_name', 'quantity', 'quantity_wasted', 'actual_quantity', 'total_production_cost'));
    }

    /**
     * Show the form for editing the specified resource.
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        $production_purchase = Transaction::where('business_id', $business_id)
            // ->where('type', 'production_purchase')
            ->with(['purchase_lines', 'purchase_lines.variations', 'purchase_lines.variations.product_variation', 'purchase_lines.variations.product'])
            ->findOrFail($id);

        $production_sell = Transaction::where('business_id', $business_id)
            ->where('type', 'production_sell')
            ->where('mfg_parent_production_purchase_id', $production_purchase->id)
            ->with(['sell_lines', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'sell_lines.variations.product', 'sell_lines.variations.product.unit'])
            ->first();
        $purchase_line = $production_purchase->purchase_lines[0];

        $recipe = MfgRecipe::where('variation_id', $purchase_line->variation_id)
            ->first();

        // $base_unit_multiplier = !empty($purchase_line->sub_unit) ? $purchase_line->sub_unit->base_unit_multiplier : 1;
        $base_unit_multiplier  = 1;
        $quantity = ($base_unit_multiplier != 0) ? $purchase_line->quantity / $base_unit_multiplier : 0;

        $quantity_wasted = 0;

       

        if (!empty($production_purchase->mfg_wasted_units)) {
            $quantity_wasted = $production_purchase->mfg_wasted_units;
            $quantity += $quantity_wasted;
        }

        $actual_quantity = $quantity * $base_unit_multiplier;

        $sub_units = $this->moduleUtil->getSubUnits($business_id, $purchase_line->product->unit->id);
        $unit_name = $purchase_line->product->unit->short_name;
        $sub_unit_id = $purchase_line->sub_unit_id;

        $ingredients = [];
        $total_ingredients_price = 0;
        if(isset($production_sell->sell_lines)){
            foreach ($production_sell->sell_lines as $sell_line) {
            $variation = $sell_line->variations;

            $line_sub_units = $this->moduleUtil->getSubUnits($business_id, $variation->product->unit->id);
            $is_line_sub_unit = false;
            $line_sub_unit_id = null;
            $multiplier = 1;
            $line_unit_name = $variation->product->unit->short_name;
            $allow_decimal = $variation->product->unit->allow_decimal;
            if (!empty($line_sub_units)) {
                foreach ($line_sub_units as $key => $value) {
                    if (!empty($sell_line->sub_unit_id) && $sell_line->sub_unit_id == $key) {
                        $line_sub_unit_id = $sell_line->sub_unit_id;
                        $multiplier = $value['multiplier'];
                        $allow_decimal = $value['allow_decimal'];
                        $line_unit_name = $value['name'];
                    }
                }
                $is_line_sub_unit = true;
            }

            $unit_quantity = $sell_line->quantity / $actual_quantity;

            $line_total_price = $variation->dpp_inc_tax * $sell_line->quantity;
            $total_ingredients_price += $line_total_price;

            $waste_percent = !empty($sell_line->mfg_waste_percent) ? $sell_line->mfg_waste_percent : 0;
            $wasted_qty = $this->moduleUtil->calc_percentage($sell_line->quantity, $waste_percent);
            $final_quantity = ($sell_line->quantity - $wasted_qty) / $multiplier;
            // dd($sell_line);
            $ingredients[] = [
                'dpp_inc_tax' => $variation->dpp_inc_tax,
                'quantity' => $sell_line->quantity / $multiplier,
                'ing_quantity' => $sell_line->ing_quantity,
                'full_name' => $variation->full_name,
                'variation_id' => $variation->id,
                'unit' => $line_unit_name,
                'allow_decimal' => $allow_decimal,
                'variation' => $variation,
                'enable_stock' => $variation->product->enable_stock,
                'is_sub_unit' => $is_line_sub_unit,
                'sub_units' => $line_sub_units,
                'sub_unit_id' => $line_sub_unit_id,
                'multiplier' => $multiplier,
                'unit_quantity' => $unit_quantity,
                'total_price' => $line_total_price,
                'waste_percent' =>  $waste_percent,
                'final_quantity' => $final_quantity,
                'id' => $sell_line->id
            ];
        }
        // dd($ingredients);
        }
        $total_production_cost = 0;
        if (!empty($recipe->extra_cost)) {
            $total_production_cost = $this->transactionUtil->calc_percentage($total_ingredients_price, $recipe->extra_cost);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $variation_name = $purchase_line->product->name;
        if ($purchase_line->product->type == 'variable') {
            $variation_name .= ' - ' .
                $purchase_line->product_variation->name .
                ' - ' . $purchase_line->variations->name;
        }
        $variation_name .= ' (' . 001100 . ')';
        
        $recipe_dropdown = [$purchase_line->variation_id => $variation_name];
        
        $varr_id = Variation::where('product_id', $purchase_line->product->id)->first();
        $var_id = $varr_id->id;
        
        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $manufacturing_settings = $this->mfgUtil->getSettings($business_id);

        $contractor = contact::where('type', 'contracter')->get();

        $contractor_rate = contractor_rates::get();
        $product = Product::All();

        $transac = Transaction::with(['purchase_lines', 'purchase_lines.product', 'purchase_lines.product.variations'])->findOrFail($id);

        $recipe = MfgRecipe::where('variation_id', $transac->purchase_lines[0]->product->variations[0]->id)
                        ->with(['variation', 'variation.product', 'variation.product.unit', 'sub_unit',
                        'ingredients' => function ($query) {
                            $query->orderBy('sort_order', 'asc');
                        }])
                        ->first();


        // $ingredients = [];
        // if (!empty($recipe)) {
        //     $ingredients_array = [];

        //     foreach ($recipe->ingredients as $ingredient) {
        //         $ingredient_quantity = $this->mfgUtil->calc_percentage($ingredient->quantity, $ingredient->waste_percent, $ingredient->quantity);
        //         $ingredients_array[$ingredient->variation_id] = [
        //             'quantity' => $ingredient_quantity,
        //             'sub_unit_id' => $ingredient->sub_unit_id,
        //             'waste_percent' => $ingredient->waste_percent
        //         ];
        //     }
        //     $ingredients = $this->mfgUtil->getIngredientDetails($recipe, $business_id, 12);
        // }
        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $manufacturing_settings = $this->mfgUtil->getSettings($business_id);
        
        $product=Product::All();

        return view('manufacturing::production.edit')->with(compact('var_id','product', 'production_purchase', 'contractor_rate', 'contractor', 'production_sell', 'business_locations', 'recipe_dropdown', 'ingredients', 'business_details', 'pos_settings', 'sub_units', 'quantity', 'quantity_wasted', 'actual_quantity', 'recipe', 'unit_name', 'sub_unit_id', 'total_production_cost', 'manufacturing_settings'));
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');

        try {
            $request->validate([
                'transaction_date' => 'required',
                'location_id' => 'required',
                'final_total' => 'required'
            ]);

            //Create Production purchase
            $transaction_data = $request->only(['ref_no', 'transaction_date', 'location_id', 'final_total', 'contractor', 'contractor_rates']);

            $is_final = !empty($request->input('finalize')) ? 1 : 0;

            $manufacturing_settings = $this->mfgUtil->getSettings($business_id);

            $transaction_data['status'] = $is_final ? 'received' : 'pending';
            $transaction_data['payment_status'] = 'due';
            $transaction_data['transaction_date'] = $this->productUtil->uf_date($transaction_data['transaction_date'], false);
            $transaction_data['final_total'] = $this->productUtil->num_uf($transaction_data['final_total']);

            // $variation_id = $request->input('variation_id');
            $variation_id = $request->input('variation_p_id');
            
            $variation = Variation::where('product_id', $variation_id)
                ->with(['product'])
                ->first();
            
            $final_total = $request->input('final_total');
            $quantity = $request->input('quantity');
            $waste_units = $this->productUtil->num_uf($request->input('mfg_wasted_units'));
            $uf_qty = $this->productUtil->num_uf($quantity);
            if (!empty($waste_units)) {
                $new_qty = $uf_qty - $waste_units;
                $uf_qty = $new_qty;
                $quantity = $this->productUtil->num_f($new_qty);
            }

            $final_total_uf = $this->productUtil->num_uf($final_total);

            $unit_purchase_line_total = $final_total_uf / $uf_qty;

            $unit_purchase_line_total_f = $this->productUtil->num_f($unit_purchase_line_total);

            $transaction_data['mfg_wasted_units'] = $waste_units;
            $transaction_data['mfg_production_cost'] = $this->productUtil->num_uf($request->input('production_cost'));
            $transaction_data['mfg_production_cost_type'] = $request->input('mfg_production_cost_type');
            $transaction_data['mfg_is_final'] = $is_final;
            $purchase_line_data = [
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'product_id' => $variation->product_id,
                'product_unit_id' => $variation->product->unit_id,
                'pp_without_discount' => $unit_purchase_line_total_f,
                'discount_percent' => 0,
                'purchase_price' => $unit_purchase_line_total_f,
                'purchase_price_inc_tax' => $unit_purchase_line_total_f,
                'item_tax' => 0,
                'purchase_line_tax_id' => null,
                'mfg_date' => $this->transactionUtil->format_date($transaction_data['transaction_date'])
            ];

            if (request()->session()->get('business.enable_lot_number') == 1) {
                $purchase_line_data['lot_number'] = $request->input('lot_number');
            }

            if (request()->session()->get('business.enable_product_expiry') == 1) {
                $purchase_line_data['exp_date'] = $request->input('exp_date');
            }

            if (!empty($request->input('sub_unit_id'))) {
                $purchase_line_data['sub_unit_id'] = $request->input('sub_unit_id');
            }

            $transaction = Transaction::where('business_id', $business_id)->findOrFail($id);

            DB::beginTransaction();

            $transaction->update($transaction_data);

            $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);

            $update_product_price = !empty($manufacturing_settings['enable_updating_product_price']) && $is_final ? true : false;

            $this->productUtil->createOrUpdatePurchaseLines($transaction, [$purchase_line_data], $currency_details, $update_product_price, null, $request->input('ingredients'));

            //Adjust stock over selling if found
            $this->productUtil->adjustStockOverSelling($transaction);

            $transaction_sell_data = [
                'transaction_date' => $transaction->transaction_date,
                'status' => $is_final ? 'final' : 'draft',
                'payment_status' => 'due',
                'final_total' => $transaction->final_total
            ];

            //Create Sell Transfer transaction
            $production_sell = Transaction::where('business_id', $business_id)
                ->where('type', 'production_sell')
                ->with('sell_lines', 'sell_lines.product', 'sell_lines.variations')
                ->where('mfg_parent_production_purchase_id', $transaction->id)
                ->first();
            
            if(!empty($production_sell)){
                
                $production_sell->update($transaction_sell_data);
                
                $sell_lines = [];
                $ingredient_quantities = $request->input('ingredients');
    
                foreach ($production_sell->sell_lines as $sell_line) {
                    $variation = $sell_line->variations;
    
                    $line_sub_unit_id = !empty($ingredient_quantities[$sell_line->id]['sub_unit_id']) ?
                        $ingredient_quantities[$sell_line->id]['sub_unit_id'] : null;
                    $line_multiplier = 1;
                    if (!empty($line_sub_unit_id)) {
                        $sub_units = $this->productUtil->getSubUnits($business_id, $sell_line->product->unit_id);
                        $line_multiplier = !empty($sub_units[$line_sub_unit_id]['multiplier']) ? $sub_units[$line_sub_unit_id]['multiplier'] : 1;
                    }
    
                    $mfg_waste_percent = !empty($ingredient_quantities[$sell_line->id]['mfg_waste_percent']) ? $this->productUtil->num_uf($ingredient_quantities[$sell_line->id]['mfg_waste_percent']) : 0;
    
                    $sell_lines[] = [
                        'product_id' => $variation->product_id,
                        'variation_id' => $variation->id,
                        'quantity' => $this->productUtil->num_uf($ingredient_quantities[$sell_line->id]['quantity'] ?? 0),
                        'ing_quantity' => $this->productUtil->num_uf($ingredient_quantities[$sell_line->id]['ing_quantity']),
                        'item_tax' => 0,
                        'tax_id' => null,
                        // 'unit_price' => $variation->dpp_inc_tax * $line_multiplier,
                        // 'unit_price_inc_tax' => $variation->dpp_inc_tax * $line_multiplier,
                        'unit_price' => $variation->dpp_inc_tax,
                        'unit_price_inc_tax' => $variation->dpp_inc_tax,
                        'enable_stock' => $sell_line->product->enable_stock,
                        'product_unit_id' => $variation->product->unit_id,
                        'sub_unit_id' => $line_sub_unit_id,
                        'base_unit_multiplier' => $line_multiplier,
                        'mfg_waste_percent' => $mfg_waste_percent
                    ];
                }
    
                if (!empty($sell_lines)) {
                    $this->transactionUtil->createOrUpdateSellLines($production_sell, $sell_lines, $transaction->location_id, false, 'draft', ['mfg_waste_percent' => 'mfg_waste_percent']);
                }
    
                if ($transaction_sell_data['status'] == 'final') {
                    foreach ($sell_lines as $sell_line) {
                        if ($sell_line['enable_stock']) {
                            $line_qty = $sell_line['quantity'] * $sell_line['base_unit_multiplier'];
                            $this->productUtil->decreaseProductQuantity(
                                $sell_line['product_id'],
                                $sell_line['variation_id'],
                                $production_sell->location_id,
                                $line_qty
                            );
                        }
                    }
                    //Map sell lines with purchase lines
                    $business = [
                        'id' => $business_id,
                        'accounting_method' => $request->session()->get('business.accounting_method'),
                        'location_id' => $production_sell->location_id
                    ];
                    $this->transactionUtil->mapPurchaseSell($business, $production_sell->sell_lines, 'production_purchase');
                }
            }
            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.updated_success')
            ];
        } catch (Exception $e) {
            DB::rollBack();
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => $e->getMessage()
            ];
        }

        if($request->input('type') == 'stock_adjustment'){
            return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@multiproduction_index',['type' => 'stock_adjustment'])->with('status', $output);
        }else{
            return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@index')->with('status', $output);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                
                DB::beginTransaction();
                    $transac = Transaction::where('id', $id)
                    ->where(function ($query) {
                        $query->where('type', 'production_purchase')
                              ->orWhere('type', 'stock_adjustment');
                    })
                    ->first();
                    DB::table('stock_history')->where('ref_no', $transac->ref_no)->delete();
                    DB::table('valuation_history')->where('ref_no', $transac->ref_no)->delete();
                    
                    $transaction = Transaction::where('id', $id)
                        ->where(function ($query) {
                        $query->where('type', 'production_purchase')
                              ->orWhere('type', 'stock_adjustment');
                        })
                        ->delete();
                DB::commit();
                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.deleted_success')
                ];
            } catch (\Exception $e) {
                \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());

                $output['success'] = false;
                $output['msg'] = trans("messages.something_went_wrong");
            }

            return $output;
        }
    }

    /**
     * Retrives data for manufacturing report.
     * @return Response
     */
    public function getManufacturingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $start_date = request()->get('start_date');
            $end_date = request()->get('end_date');
            $location_id = request()->get('location_id');

            $production_totals = $this->mfgUtil->getProductionTotals($business_id, $location_id, $start_date, $end_date);

            $total_sold = $this->mfgUtil->getTotalSold($business_id, $location_id, $start_date, $end_date);


            $output['total_production'] = $production_totals['total_production'];
            $output['total_production_cost'] = $production_totals['total_production_cost'];
            $output['total_sold'] = $total_sold;

            return $output;
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);
        return view('manufacturing::production.report')->with(compact('business_locations'));
    }

    public function multi_production_create()
    {
        $type = request('type');
        
        $business_id = request()->session()->get('user.business_id');
        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module')) || !auth()->user()->can('manufacturing.access_production')) {
            abort(403, 'Unauthorized action.');
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        
        if($type == 'stock_adjustment'){
            $recipe_dropdown = MfgRecipe::forDropdownAll($business_id);
        }else{
            $recipe_dropdown = MfgRecipe::forDropdown($business_id);
        }
        

        $contractor = Contact::where('type', 'contracter')->get();
        
         
        $getprefix = Business::first();
        
        if($type == 'stock_adjustment'){
            $prefix = $getprefix->ref_no_prefixes['stock_adjustment'];
            $trans_no = DB::table('transactions')
            ->where('ref_no', 'like', '%'. $prefix .'%')
            ->select('custom_field_1', DB::raw("substring_index(substring_index(custom_field_1,'-',-1),',',-1) as max_no"))->get()
            ->max('max_no');
        }else{
            $prefix = $getprefix->ref_no_prefixes['multi_production'];
            $trans_no = DB::table('transactions')
            ->where('ref_no', 'like', '%'. $prefix .'%')
            ->select('custom_field_1', DB::raw("substring_index(substring_index(custom_field_1,'-',-1),',',-1) as max_no"))->get()
            ->max('max_no');
        }
        
        if(empty($trans_no)){
            $trans_no_ = 1;
        }else{
            $break_no = explode("-",$trans_no);
            $trans_no_ = end($break_no)+1;
        }
        $ref_no = $prefix.'-'.$trans_no_;


        return view('manufacturing::Multi_production.create')
            ->with(compact('business_locations', 'recipe_dropdown', 'contractor', 'ref_no'));
    }





    // Quantity Check funtion with multi production


    public function quantity_check(Request $request)
    {
        try {
            $request->validate([
                'transaction_date' => 'required',
                'location_id' => 'required',
                'final_total' => 'required'
            ]);
            $final              = $request->input('finalize');
            $variation_id       = $request->input('variation_id');
            $quantity           = $request->input('quantity');
            $production_total   = $request->input('production_total');
            
            DB::beginTransaction();
            // custom ref_no generator
            $getprefix  = Business::first();
            if($request->input('type') == 'stock_adjustment'){
                $prefix     = $getprefix->ref_no_prefixes['stock_adjustment'];
            }else{
                $prefix     = $getprefix->ref_no_prefixes['multi_production'];
            }
            
            $trans_no = DB::table('transactions')
                ->where('ref_no', 'like', '%'. $prefix .'%')
                ->select('ref_no', DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(ref_no, '-', 2), '-', -1) as max_no"))->get()
                ->max('max_no');
            if(empty($trans_no)){
                $trans_no_ = 1;
            }else{
                $break_no  = explode("-", $trans_no);
                $trans_no_ = end($break_no) + 1;
            }
            $alpha = 'A';
                
                

            // new work for multiproduction     
            foreach ($variation_id as $key => $v) {
                $business_id = $request->session()->get('user.business_id');
                //Create Production purchase
                $manufacturing_settings = $this->mfgUtil->getSettings($business_id);
                $user_id = $request->session()->get('user.id');

                $transaction_data = $request->only(['ref_no', 'transaction_date', 'location_id', 'final_total', 'contractor', 'contractor_rates']);

                $is_final = !empty($request->input('finalize')) ? 1 : 0;
                $transaction_data['business_id'] = $business_id;
                $transaction_data['created_by'] = $user_id;
                
                $transaction_data['status'] = $is_final ? 'received' : 'pending';
                $transaction_data['payment_status'] = 'due';
                $transaction_data['transaction_date'] = $this->productUtil->uf_date($transaction_data['transaction_date'], false);
                $transaction_data['final_total'] = $this->productUtil->num_uf($production_total[$key] ?? 0 );
                if($request->input('type') == 'stock_adjustment'){
                    $transaction_data['type'] = 'stock_adjustment';
                    $transaction_data['mfg_is_multiproduction'] = null;
                }else{
                    $transaction_data['type'] = 'production_purchase';
                    $transaction_data['mfg_is_multiproduction'] = '1';
                }

                $transaction_data['custom_field_1'] =  $transaction_data['ref_no'];
                
                $alpha1 = $alpha++;
                $transaction_data['ref_no'] = $prefix.'-'.$trans_no_.'-'.$alpha1;
                
                $variation_id = $request->input('variation_id');
                $variation = Variation::where('id', $variation_id[$key])
                    ->with(['product'])
                    ->first();
                // $final_total = $request->input('final_total');
                $final_total = $production_total[$key] ?? 0;
                $quantity = $request->input('quantity') ?? '';
                $waste_units = $this->productUtil->num_uf($request->input('mfg_wasted_units'));
                $uf_qty = $this->productUtil->num_uf($quantity[$key]);
                if (!empty($waste_units)) {
                    $new_qty = $uf_qty - $waste_units;
                    $uf_qty = $new_qty;
                    $quantity = $this->productUtil->num_f($new_qty);
                }
                $final_total_uf = $this->productUtil->num_uf($final_total);
                $unit_purchase_line_total = $final_total_uf / $uf_qty;
                $unit_purchase_line_total_f = $this->productUtil->num_f($unit_purchase_line_total);
                $transaction_data['mfg_wasted_units'] = $waste_units;
                $productionCostValue = is_array($request->input('production_cost')) ? ($request->input('production_cost')[0] ?? 0) : $request->input('production_cost');
                $transaction_data['mfg_production_cost'] = $this->productUtil->num_uf($productionCostValue);
                $transaction_data['mfg_production_cost_type'] = $request->input('mfg_production_cost_type');
                $transaction_data['mfg_is_final'] = $is_final;
                $purchase_line_data = [
                    'variation_id' => $variation_id[$key],
                    'quantity' => $quantity[$key],
                    'production_total' => $production_total[$key] ?? 0,
                    'product_id' => $variation->product_id,
                    'product_unit_id' => $variation->product->unit_id,
                    'pp_without_discount' => $unit_purchase_line_total_f,
                    'discount_percent' => 0,
                    'purchase_price' => $unit_purchase_line_total_f,
                    'purchase_price_inc_tax' => $unit_purchase_line_total_f,
                    'item_tax' => 0,
                    'purchase_line_tax_id' => null,
                    'mfg_date' => $this->transactionUtil->format_date($transaction_data['transaction_date'])
                ];
                if (request()->session()->get('business.enable_lot_number') == 1) {
                    $purchase_line_data['lot_number'] = $request->input('lot_number');
                }
                if (request()->session()->get('business.enable_product_expiry') == 1) {
                    $purchase_line_data['exp_date'] = $request->input('exp_date');
                }
                if (!empty($request->input('sub_unit_id'))) {
                    $purchase_line_data['sub_unit_id'] = $request->input('sub_unit_id');
                }
                // for single production valuation
                // if (!empty($request->input('production_total'))) {
                //     $purchase_line_data['production_total'] = $request->input('production_total');
                // }
                $transaction = Transaction::create($transaction_data);
                $transaction->finalize = $request->finalize;
                $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
                $update_product_price = !empty($manufacturing_settings['enable_updating_product_price']) && $is_final ? true : false;
                $this->productUtil->createOrUpdatePurchaseLines($transaction, [$purchase_line_data], $currency_details, $update_product_price, null, $request->input('ingredients'));

                //Adjust stock over selling if found
                $this->productUtil->adjustStockOverSelling($transaction);
                //Create production sell
                $transaction_sell_data = [
                    'business_id' => $business_id,
                    'location_id' => $transaction->location_id,
                    'transaction_date' => $transaction->transaction_date,
                    'created_by' => $transaction->created_by,
                    'status' => $is_final ? 'final' : 'draft',
                    'type' => 'production_sell',
                    'mfg_parent_production_purchase_id' => $transaction->id,
                    'payment_status' => 'due',
                    'final_total' => $transaction->final_total
                ];

                $sell_lines = [];
                $ingredient_quantities = !empty($request->input('ingredients')) ? $request->input('ingredients') : [];
                
                
                
                //Get ingredient details to create sell lines
                $recipe = MfgRecipe::where('variation_id', $variation_id[$key])->first();
                if($recipe){
                    
                    $all_variation_details = $this->mfgUtil->getIngredientDetails($recipe, $business_id);
                    
                    foreach ($all_variation_details as $variation_details) {
                        $variation = $variation_details['variation'];
    
                        $line_sub_unit_id = !empty($ingredient_quantities[$variation_details['id']]['sub_unit_id']) ?
                        $ingredient_quantities[$variation_details['id']]['sub_unit_id'] : null;
                        $line_multiplier = !empty($line_sub_unit_id) ? $variation_details['sub_units'][$line_sub_unit_id]['multiplier'] : 1;
                        
                        $mfg_waste_percent = !empty($ingredient_quantities[$variation_details['id']]['mfg_waste_percent']) ? $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['mfg_waste_percent']) : 0;
    
                        $sell_lines[] = [
                            'product_id' => $variation->product_id,
                            'variation_id' => $variation->id,
                            'quantity' => $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['quantity']),
                            'ing_quantity' => $this->productUtil->num_uf($ingredient_quantities[$variation_details['id']]['ing_quantity']),
                            'item_tax' => 0,
                            'tax_id' => null,
                            // 'unit_price' => $variation->dpp_inc_tax * $line_multiplier,
                            // 'unit_price_inc_tax' => $variation->dpp_inc_tax * $line_multiplier,
                            'unit_price' => $variation->dpp_inc_tax,
                            'unit_price_inc_tax' => $variation->dpp_inc_tax,
                            'enable_stock' => $variation_details['enable_stock'],
                            'product_unit_id' => $variation->product->unit_id,
                            'sub_unit_id' => $line_sub_unit_id,
                            'base_unit_multiplier' => $line_multiplier,
                            'mfg_waste_percent' => $mfg_waste_percent
                        ];
                    }
    
                    //Create Sell Transfer transaction
                    $production_sell = Transaction::create($transaction_sell_data);
                    if (!empty($sell_lines)) {
                        $this->transactionUtil->createOrUpdateSellLines($production_sell, $sell_lines, $transaction_sell_data['location_id'], null, null, ['mfg_waste_percent' => 'mfg_waste_percent']);
                    }
    
                    if ($production_sell->status == 'final') {
                        foreach ($sell_lines as $sell_line) {
                            if ($sell_line['enable_stock']) {
                                $line_qty = $sell_line['quantity'] * $sell_line['base_unit_multiplier'];
                                $this->productUtil->decreaseProductQuantity(
                                    $sell_line['product_id'],
                                    $sell_line['variation_id'],
                                    $production_sell->location_id,
                                    $line_qty
                                );
                            }
                        }
    
                        $business_details = $this->businessUtil->getDetails($business_id);
                        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);
    
                        //Map sell lines with purchase lines
                        $business = [
                            'id' => $business_id,
                            'accounting_method' => $request->session()->get('business.accounting_method'),
                            'location_id' => $production_sell->location_id,
                            'pos_settings' => $pos_settings
                        ];
                        $this->transactionUtil->mapPurchaseSell($business, $production_sell->sell_lines, 'production_purchase');
                    }
                }
            }
            // new work for multiproduction listing
            
            DB::commit();
            $output = [
                'success' => 1,
                'msg' => 'Success'
            ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage());
            $output = [
                'success' => 0,
                'msg' => "File:" . $e->getFile() . "Line:" . $e->getLine() . "Message:" . $e->getMessage()
            ];
        }
        if($request->input('type') == 'stock_adjustment'){
            return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@multiproduction_index',['type' => 'stock_adjustment'])->with('status', $output);
        }else{
            return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@multiproduction_index')->with('status', $output);
        }

    }











    // old function of this. without multi production
    // Quantity Check
    // public function quantity_check(Request $request)
    // {
    //     // dd($request);
    //     try {
    //         $final              = $request->input('finalize');
    //         $variation_id       = $request->input('variation_id');
    //         $quantity           = $request->input('quantity');
    //         $production_total   = $request->input('production_total');

    //         if($final == 1){
    //             DB::beginTransaction();
    //             foreach($variation_id as $key => $v){

    //                 $variation_location_details=VariationLocationDetails::where('variation_id',$v)->firstorfail();
    //                 $variation_location_details->qty_available += $quantity[$key];
    //                 $variation_location_details->save();

    //                 // valuation code start here
    //                 $variation = Variation::where('id', $v)->first();
    //                 $variation_location_detail = VariationLocationDetails::where('variation_id', $v)->first();
    //                 if($variation->sell_price_inc_tax == 0){
    //                 }else{
    //                     $average_price = ($production_total[$key] / $quantity[$key]);
    //                     $variation->sell_price_inc_tax = round($average_price);
    //                     $variation->save();
    //                 }
    //             }
    //             DB::commit();
    //         }
    //     } catch (\Exception $e) {
    //         \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
    //     }

    //     return redirect()->action('\Modules\Manufacturing\Http\Controllers\ProductionController@index');
    // }










    public function add_contractor(Request $request)
    {
        $contractor_parent = contractor_rates::where('contractor_id', $request->contractor)->get();
        if (isset($contractor_parent[0]['id']) && !empty($contractor_parent[0]['id'])) {
            $contractor = contractor_rates::where('parent_id', $contractor_parent[0]['id'])->get();
        } else {
            $contractor = [];
        }
        return response()->json($contractor);
    }
}
