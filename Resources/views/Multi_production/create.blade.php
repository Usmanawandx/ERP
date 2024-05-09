@extends('layouts.app')
@if(request('type') == 'stock_adjustment')
     @section('title', 'Stock Adjustment')
@else
    @section('title', __('manufacturing::lang.production'))
@endif


@section('content')

@if(request('type') == 'stock_adjustment')
@else
    @include('manufacturing::layouts.nav')
@endif

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        @if(request('type') == 'stock_adjustment')
            Stock Adjustment
        @else
            Multi Production
        @endif
    </h1>
</section>

<!-- Main content -->
<section class="content">

	{!! Form::open(['url' => action('\Modules\Manufacturing\Http\Controllers\ProductionController@quantity_check'), 'method' => 'post', 'id' => 'production_form_new', 'files' => true ]) !!}
	@component('components.widget', ['class' => 'box-solid'])
		<div class="row">
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('ref_no', __('purchase.ref_no').':') !!} @show_tooltip(__('manufacturing::lang.ref_no_tooltip'))
					{!! Form::text('ref_no', $ref_no, ['class' => 'form-control', 'readonly']); !!}
					<input type="hidden" name="type" value="{{ request('type') }}" />
				</div>
			</div>
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('transaction_date', __('manufacturing::lang.mfg_date') . ':*') !!}
					<div class="input-group">
						<span class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</span>
						{!! Form::text('transaction_date', @format_date('now'), ['class' => 'form-control transaction_date', 'required']); !!}
					</div>
				</div>
			</div>
			
			@if(count($business_locations) == 1)
				@php 
					$default_location = current(array_keys($business_locations->toArray())) 
				@endphp
			@else
				@php $default_location = null; @endphp
			@endif

		
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('location_id', __('purchase.business_location').':*') !!}
					@show_tooltip(__('tooltip.purchase_location'))
					{!! Form::select('location_id', $business_locations, $default_location, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
				</div>
			</div>
			
			
			<div class="col-sm-4 @if(request('type') == 'stock_adjustment') hide @endif hide">
				<div class="form-group">
					{!! Form::label('contractor', 'Contractor'.':*') !!}
					<select class="form-control" name="contractor" id="contractor">
					    <option selected disabled>Select Please</option>
					    @foreach($contractor as $c)
					    <option value="{{ $c['id'] }}">{{ $c['supplier_business_name'] }}</option>
					    @endforeach
					</select>

				</div>
			</div>
			
				<div class="col-sm-4 @if(request('type') == 'stock_adjustment') hide @endif hide">
				<div class="form-group">
					{!! Form::label('contractor_rates', 'Contractor Rates'.':*') !!}
			<!--{!! Form::text('contractor_rates', null, ['class' => 'form-control','id'=>'contractor_rates']); !!}-->
			    <select class="form-control" name="contractor_rates" id="contractor_rates">
					    <option selected disabled>Select Please</option>
				</select>
				</div>
			</div>
			
			
			
		</div>
		
			<div class="row">
    <table class="table table-condensed table-bordered table-th-green text-center table-striped product_form">
		<thead>
		<tr>
			<th style="width: 37%;">{!! Form::label('variation_id', __('sale.product').':*') !!}</th>
			<th>{!! Form::label('recipe_quantity', __('lang_v1.quantity').':*') !!}</th>
			<th>Action</th>
		</tr>
		</thead>
		<tbody id="tbody">
		<tr id="row_0">
			<td>	
		{!! Form::select('variation_id[]', $recipe_dropdown, null, ['class' => 'form-control select2 search_product', 'placeholder' => __('messages.please_select'), 'required']); !!}
			</td>
		<td>	
			
			<div class="col-sm-4">
				
				<div class="form-group">
					    @if(request('type') == 'stock_adjustment')
					        {!! Form::text('quantity[]', 1, ['class' => 'form-control input_number', 'id' =>'','onchange'=>'recipe_qty(this)','required']); !!}
					    @else
						    {!! Form::text('quantity[]', 1, ['class' => 'form-control input_number recipe_quantity_multi', 'id' =>'recipe_quantity_multi','onchange'=>'recipe_qty(this)','required', 'data-rule-notEmpty' => 'true','data-rule-notEqualToWastedQuantity' => 'true']); !!}
						@endif
						{{-- <span class="input-group-addon " id="unit_html" ></span> --}}
				</div>
				
				
				
				
				</div>
		</td>
		<td> <button class="btn btn-md btn-primary addBtn" type="button"  onclick="add_row(this)" style="
			padding: 0px 5px 2px 5px;
		">
										<i class="fa fa-plus-circle" aria-hidden="true"></i>
										  </button>
										<button class="btn btn-danger remove" type="button" onclick="remove_row(this)" style="
			padding: 0px 5px 2px 5px;
		"><i class="fa fa-trash" aria-hidden="true"></i></button>
										</td>
	</tr>
		</tbody>
	</table>
			</div>
	@endcomponent

	@component('components.widget', ['class' => 'box-solid', 'title' => __('Details')])
	<div class="row">
		<div class="col-md-12">
			<div id="enter_ingredients_table" class="text-center">
				{{-- <i>@lang('manufacturing::lang.add_ingredients_tooltip')</i> --}}
			</div>
		</div>
	</div>
	<br>
	<div class="row">
		@if(request()->session()->get('business.enable_lot_number') == 1)
			<div class="col-sm-3">
				<div class="form-group">
					{!! Form::label('lot_number', __('lang_v1.lot_number').':') !!}
					{!! Form::text('lot_number', null, ['class' => 'form-control']); !!}
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
						{!! Form::text('exp_date', null, ['class' => 'form-control', 'readonly']); !!}
					</div>
				</div>
			</div>
		@endif
		<div class="col-md-3">
			<div class="form-group">
				{!! Form::label('mfg_wasted_units', __('manufacturing::lang.waste_units').':') !!} @show_tooltip(__('manufacturing::lang.wastage_tooltip'))
				<div class="input-group">
					{!! Form::text('mfg_wasted_units', 0, ['class' => 'form-control input_number']); !!}
					<span class="input-group-addon" id="wasted_units_text"></span>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				{!! Form::label('production_cost', __('manufacturing::lang.production_cost').':') !!} @show_tooltip(__('manufacturing::lang.production_cost_tooltip'))
				<div class="input_inline">
					{!! Form::text('production_cost[]', 0, ['class' => 'form-control input_number']); !!}
					<span>
						{!! Form::select('mfg_production_cost_type',['fixed' => __('lang_v1.fixed'), 'percentage' => __('lang_v1.percentage'), 'per_unit' => __('manufacturing::lang.per_unit')], 'fixed', ['class' => 'form-control', 'id' => 'mfg_production_cost_type']); !!}	
					</span>
				</div>
				<p><strong>
				{{__('manufacturing::lang.total_production_cost')}}:
			</strong>
			<span id="total_production_cost" class="display_currency" data-currency_symbol="true">0</span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-3 col-md-offset-9">
			{!! Form::hidden('final_total', 0, ['id' => 'final_total']); !!}
			<!--<strong>-->
			<!--	{{__('manufacturing::lang.total_cost')}}:-->
			<!--</strong>-->
			<!--<span id="final_total_text" class="display_currency" data-currency_symbol="true">0</span>-->
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
				<button type="submit" class="btn-big btn-primary" id="submitForm">Save And Close</button>
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

<script>
	$(document).on('change', '.search_product', function () {
		var id = $(this).parent().parent().attr('id');
    
		var product=$(this).closest('tr').find(".search_product option:selected").text();
		var variation_id = $(this).val();
		var location_id = $("#location_id").val();

		var ind = $(".search_product").index(this);
		
		if(variation_id && location_id) {
			$.ajax({
	            url: "/manufacturing/get-recipe-details-multy?variation_id=" + variation_id + "&location_id=" + location_id,
	            dataType: 'json',
	            success: function(result) {
	                
	                
	                if($('#enter_ingredients_table #div_'+id).length == 0) {
                      	$('#enter_ingredients_table').append(`<div class="ing_div" id="div_`+id+`">`+product + result.ingredient_table + '</div>');
                      	
                      	$('#div_'+id).find('.parent_id').val(variation_id);
                      	
                    }else if($('#enter_ingredients_table #div_'+id).length == 1){
                        $('#enter_ingredients_table #div_'+id).html(product + result.ingredient_table);
                        
                        $('#div_'+id).find('.parent_id').val(variation_id);
                    }
	          
	              
					$('#enter_ingredients_table table:last').attr('id',id);
	                if (result.is_sub_unit) {
	                	$('#recipe_quantity_multi_input').removeClass('input-group');
	                	$('#recipe_quantity_multi_input').addClass('input_inline');
	                	$('#unit_html').removeClass('input-group-addon');
	                } else {
	                	$('#recipe_quantity_multi_input').addClass('input-group');
	                	$('#recipe_quantity_multi_input').removeClass('input_inline');
	                	$('#unit_html').addClass('input-group-addon');
	                }
					$(".recipe_quantity_multi").eq(ind).val(0);
	                $('#unit_html').html(result.unit_html);
	                $('#wasted_units_text').text(result.unit_name);

                    var mfg_wasted_units = __calculate_amount('percentage', $('#waste_percent').val(), result.recipe.total_quantity);
                    __write_number($('#mfg_wasted_units'), mfg_wasted_units);
                    __write_number($('#production_cost'), result.recipe.extra_cost);
                    $('#mfg_production_cost_type').val(result.recipe.production_cost_type);

	                __currency_convert_recursively($('#enter_ingredients_table'));
	                
	                
                    calculateRecipeTotal(id);
	            },
	            error: function(jqXHR, textStatus, errorThrown) {
	               // alert('Error with this product');
	               toastr.error('Ingredients Not Found Of This Product');

                },
	        });
		} else {
			$('#enter_ingredients_table').append('');
            calculateRecipeTotal(id);
		}
	});

	function recipe_qty(el)
	{
			var id = $(el).closest('.ingredients_for_unit_recipe_table').attr('id');
			// alert(id);		
			var recipe_quantity = parseFloat($(el).val());

			var ind = $(".recipe_quantity_multi").index(el);

			var mfg_wasted_units = __calculate_amount('percentage', $('#waste_percent').val(), recipe_quantity);
			__write_number($('#mfg_wasted_units'), mfg_wasted_units);

			var multiplier = 1;
			if ($('#sub_unit_id').length) {
				multiplier = parseFloat(
				$('#sub_unit_id')
					.find(':selected')
					.data('multiplier')
				);
				recipe_quantity = recipe_quantity ; 
			}
			var parent_id = $(el).parents("tr").attr("id");
			$("#"+parent_id+' tbody tr').each( function(i,e) {
				var line_unit_quantity = parseFloat($(e).find('.unit_quantity').val());
				var multi_hidden_qty = parseFloat($(e).find('.multi_hidden_qty').val());
				
				// var line_multiplier = __getUnitMultiplier($(e));
				// var line_total_quantity = (recipe_quantity * multi_hidden_qty) ;
				
				var ing_quantity = parseFloat($(this).find('.ing_quantity').val());
            	var line_total_quantity = (recipe_quantity * ing_quantity);

				__write_number($(e).find('.total_quantities'), line_total_quantity);
				$(e).find('.total_quantities').change();
			});
	}	
	$(document).on('change', '.ing_quantity',function(){
        $('.recipe_quantity_multi').trigger('change');
    })

	function add_row(el) {
		// alert(el.rowIndex);
			$('.product_form #tbody tr').each(function() {
				// alert("Sa");
				$(this).find('.search_product').select2('destroy')
			})
			
			var tr = $(".product_form #tbody tr:last").clone();
			
		
			tr.find('input').val('');
			tr.find('textarea').val('');
			// console.log(tr);
			$(".product_form #tbody tr:last").after(tr);
			$('.search_product').select2()
			update_index();
		}

		function remove_row(el) {
			var tr_length = $(".product_form #tbody tr").length;
			var tr_id	  = $(el).closest('tr').attr('id');	
			if(tr_length > 1){
				var tr = $(el).closest("tr").remove();
				$(document).find('#div_' + tr_id).remove();
				update_index();
				update_div_index();
			}else{
				alert("At least one row required");
			}		
		}
		function update_index(){

		$(".product_form tbody tr").each(function(i,e){

			$(e).removeAttr("id");
			$(e).attr("id","row_"+i);
		});

		}

		function update_div_index(){
			$("#enter_ingredients_table .ing_div").each(function(i,e){
				$(e).removeAttr("id");
				$(e).attr("id","div_row_"+i);
				$(e).find('.ingredients_for_unit_recipe_table').attr("id","row_"+i);
			});
		}
		$('form').validate();
		
$(document).ready(function () {
    $("#submitForm").on("click", function (event) {
        var products = [];
        var isValid = true;

        $(".search_product").each(function () {
            var product = $(this).val().trim();

            // Check if the product is empty
            if (product === "") {
                isValid = false;
                alert("Please fill in all products.");
                event.preventDefault(); // Prevent form submission
                return false; // exit the loop
            }

            // Check if the product already exists
            if (products.indexOf(product) !== -1) {
                isValid = false;
                alert("Duplicate products are not allowed.");
                event.preventDefault(); // Prevent form submission
                return false; // exit the loop
            }

            products.push(product);
        });

        // If all validations pass, submit the form
        if (isValid) {
            $("#production_form_new").submit();
        }
    });
});
</script>
	@include('manufacturing::production.production_script')
@endsection
