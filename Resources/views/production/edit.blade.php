@extends('layouts.app')
@section('title', __('manufacturing::lang.production'))

@section('content')
    @if($production_purchase->type == 'stock_adjustment')
        
    @else
        @include('manufacturing::layouts.nav')
    @endif
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        @if($production_purchase->type == 'stock_adjustment')
            Stock Adjustment
        @else
            @lang('manufacturing::lang.production')
        @endif
    </h1>
</section>

<!-- Main content -->
<section class="content">

	{!! Form::open(['url' => action('\Modules\Manufacturing\Http\Controllers\ProductionController@update', [$production_purchase->id]), 'method' => 'put', 'id' => 'production_form', 'files' => true ]) !!}
	@component('components.widget', ['class' => 'box-solid'])
		<div class="row">
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('ref_no', __('purchase.ref_no').':') !!}
					{!! Form::text('ref_no', $production_purchase->ref_no, ['class' => 'form-control', 'readonly']); !!}
					<input type="hidden" name="type" value="{{ $production_purchase->type }}" />
				</div>
			</div>
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('transaction_date', __('manufacturing::lang.mfg_date') . ':*') !!}
					<div class="input-group">
						<span class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</span>
						{!! Form::text('transaction_date', @format_datetime($production_purchase->transaction_date), ['class' => 'form-control', 'required']); !!}
					</div>
				</div>
			</div>
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('location_id', __('purchase.business_location').':*') !!}
					@show_tooltip(__('tooltip.purchase_location'))
					{!! Form::select('location_id', $business_locations, $production_purchase->location_id, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
				</div>
			</div>
			@php
				$purchase_line = $production_purchase->purchase_lines[0];
			@endphp
			
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('variation_id_shown', __('sale.product').':*') !!}
					{!! Form::select('variation_id_shown', $recipe_dropdown, $purchase_line->variation_id, ['class' => 'form-control', 'placeholder' => __('messages.please_select'), 'required', 'disabled']); !!}
					{!! Form::hidden('variation_id', $purchase_line->variation_id, ['id' => 'variation_id']); !!}
						{!! Form::hidden('variation_p_id', $purchase_line->product_id, ['id' => 'variation_id']); !!}
						
					<input type="hidden" id="pro_id" value="{{ $var_id }}"/>
				</div>
			</div>
		

			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('recipe_quantity', __('lang_v1.quantity').':*') !!}
					<div class="@if(!empty($sub_units)) input_inline @else input-group @endif" id="recipe_quantity_input">
						@if($production_purchase->type == 'stock_adjustment')
                            {!! Form::text('quantity', @num_format($quantity), ['class' => 'form-control input_number', 'id' => 'recipe_quantity', 'required']); !!}
                        @else
                            {!! Form::text('quantity', @num_format($quantity), ['class' => 'form-control input_number', 'id' => 'recipe_quantity', 'required', 'data-rule-notEmpty' => 'true', 'data-rule-notEqualToWastedQuantity' => 'true']); !!}
                        @endif
						<span class="@if(empty($sub_units)) input-group-addon @endif" id="unit_html">
							@if(!empty($sub_units))
								<select name="sub_unit_id" class="form-control" id="sub_unit_id">
								@foreach($sub_units as $key => $value)
									<option 
										value="{{$key}}" 
										data-multiplier="{{$value['multiplier']}}" 
										data-unit_name="{{$value['name']}}"
										@if($key == $sub_unit_id)
											@php
												$unit_name = $value['name'];
											@endphp
											selected
										@endif
										>{{$value['name']}}</option>
								@endforeach
								</select>
							@else
								{{ $unit_name }}
							@endif
						</span>
					</div>
				</div>
			</div>
		
			<div class="col-sm-4 @if($production_purchase->type == 'stock_adjustment') hide @endif">
				<div class="form-group hide">
					{!! Form::label('contractor', 'Contractor'.':*') !!}
					<select class="form-control" name="contractor" id="contractor">
					    <option selected disabled>Select Please</option>
					    @foreach($contractor as $c)
					    @if($production_purchase->contractor==$c->id)
					    <option value="{{ $c['id'] }}" selected>{{ $c['supplier_business_name'] }}</option>
					    @else
					      <option value="{{ $c['id'] }}">{{ $c['supplier_business_name'] }}</option>
					    @endif
					    @endforeach
					</select>
		
					
			</div>
			
		
		</div>
	<div class="col-sm-4  @if($production_purchase->type == 'stock_adjustment') hide @endif hide">
				<div class="form-group">
					{!! Form::label('contractor_rates', 'Contractor Rates'.':*') !!}
				<!--{!! Form::text('contractor_rates', $production_purchase->contractor_rates, ['class' => 'form-control','id'=>'contractor_rates']); !!}-->
					<select class="form-control" name="contractor_rates" id="contractor_rates">
					    <option selected disabled>Select Please</option>
					    @foreach($contractor_rate as $c)
					    
					    
					    @if($production_purchase->contractor_rates==$c->rate)
					    <option value="{{ $c->rate }}" selected>{{ $c->rate }}</option>
					    @else
					      <option value="{{ $c->rate }}">{{ $c->rate }}</option>
					    @endif
					    @endforeach
					</select>
				</div>
			</div>
	
	@endcomponent

	@component('components.widget', ['class' => 'box-solid', 'title' => __('manufacturing::lang.ingredients')])
		<div class="row">
			<div class="col-md-12">
			      <div><input type="checkbox" name="check" value="1" class="dis_product" />Make Editable</div>
				<div id="enter_ingredients_table">
					{{-- {{dd($ingredients)}} --}}
					@include('manufacturing::recipe.ingredients_for_production')
				</div>
			</div>
		</div>
		<div class="row">
			@if(request()->session()->get('business.enable_lot_number') == 1)
				<div class="col-sm-3">
					<div class="form-group">
						{!! Form::label('lot_number', __('lang_v1.lot_number').':') !!}
						{!! Form::text('lot_number', $purchase_line->lot_number, ['class' => 'form-control']); !!}
					</div>
				</div>
			@endif
			@if(session('business.enable_product_expiry'))
				<div class="col-sm-3">
					<div class="form-group">
						{!! Form::label('exp_date', __('product.exp_date').':*') !!}
						<div class="input-group">
							<span class="input-group-addon">
								<i class="fa fa-calendar"></i>
							</span>
							{!! Form::text('exp_date', !empty($purchase_line->exp_date) ? @format_date($purchase_line->exp_date) : null, ['class' => 'form-control', 'readonly']); !!}
						</div>
					</div>
				</div>
			@endif
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label('mfg_wasted_units', __('manufacturing::lang.waste_units').':') !!} @show_tooltip(__('manufacturing::lang.wastage_tooltip'))
					<div class="input-group">
						{!! Form::text('mfg_wasted_units', @num_format($production_purchase->mfg_wasted_units), ['class' => 'form-control input_number']); !!}
						<span class="input-group-addon" id="wasted_units_text">{{$unit_name}}</span>
						
					<input type="hidden" name="hidden_total" id="hidden_total"/>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label('production_cost', __('manufacturing::lang.production_cost').':') !!} @show_tooltip(__('manufacturing::lang.production_cost_tooltip'))
					<div class="input_inline">
						{!! Form::text('production_cost', @num_format($production_purchase->mfg_production_cost), ['class' => 'form-control input_number']); !!}
						<span>
							{!! Form::select('mfg_production_cost_type',['fixed' => __('lang_v1.fixed'), 'percentage' => __('lang_v1.percentage'), 'per_unit' => __('manufacturing::lang.per_unit')], $production_purchase->mfg_production_cost_type, ['class' => 'form-control', 'id' => 'mfg_production_cost_type']); !!}	
						</span>
					</div>
					<p><strong>
						{{__('manufacturing::lang.total_production_cost')}}:
					</strong>
					<span id="total_production_cost" class="display_currency" data-currency_symbol="true">{{$total_production_cost}}</span></p>
				</div>
			</div>
			<div class="col-md-3 pull-right font-16">
				{!! Form::hidden('final_total', @num_format($production_purchase->final_total), ['id' => 'final_total']); !!}
				<strong>
					{{__('manufacturing::lang.total_cost')}}:
				</strong>
				<span id="final_total_text" class="display_currency pull-right" data-currency_symbol="true">{{ $production_purchase->final_total }}</span>
			</div>
		</div>
		<div class="row hide">
			<div class="col-md-3 col-md-offset-9">
				<div class="form-group">
					<br>
					<div class="checkbox">
						<label>
						{!! Form::checkbox('finalize', 1, false, ['class' => 'input-icheck', 'id' => 'finalize']); !!} @lang('manufacturing::lang.finalize')
						</label> @show_tooltip(__('manufacturing::lang.finalize_tooltip'))
					</div>
		        </div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12 fixed-button">
			<div class="text-center">
            <div class="btn-group ">
				<button type="submit" class="btn-big btn-primary update-btn">Update & Close</button>
				<button class="btn-big btn-danger" type="button" onclick="window.history.back()">Close</button>
			</div>
			</div>
			</div>
		</div>
	@endcomponent

{!! Form::close() !!}
</section>
@endsection

@section('javascript')
	@include('manufacturing::production.production_script')

	<script type="text/javascript">
		$(document).ready( function () {
		    $('#recipe_quantity').trigger('change');
		    
			calculateRecipeTotal();
			
			
				  $(".dis_product").on("click", function(){
    // alert("sa");
    check = $(".dis_product").is(":checked");
    if(check) {
        $('.product_ing').removeAttr("disabled")
          $('.ingredient_id').attr("disabled","true");
   
    } else {
        $('.product_ing').attr("disabled","true");
        $('.ingredient_id').removeAttr("disabled");
        
        // alert("Checkbox is unchecked.");
    }
}); 

$('#contractor_rates').trigger('change')
		});



</script>
@endsection
