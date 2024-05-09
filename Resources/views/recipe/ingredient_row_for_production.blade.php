
<tr>

	<td>
		<input type="hidden" name="ingredients[{{$ingredient['id']}}][id]" class="ing_id" value="{{$ingredient['id']}}">
	<select class="form-control select2 product_ing" name="ingredients[{{$ingredient['id']}}][variation_id]" disabled>
			@foreach ($product as $p)
	        @if($ingredient['variation']['id']==$p->id)
			<option value="{{$ingredient['variation']['id']}}" selected>{{$ingredient['variation']['full_name']}}</option>
			@else
			<option value="{{$ingredient['variation']['id']}}">{{$ingredient['variation']['full_name']}}</option>
			@endif
			@endforeach
		
		
			
		</select>

		<input type="hidden" class="ingredient_price" value="{{$ingredient['dpp_inc_tax']}}">
		<input type="hidden" name="ingredients[{{$ingredient['id']}}][variation_id]"  class="ingredient_id" value="{{$ingredient['variation_id']}}">
		<input type="hidden" class="unit_quantity" value="{{$ingredient['unit_quantity']}}">
		<input type="hidden" class="parent_id" name="ingredients[{{$ingredient['id']}}][parent_id]" value="{{ $var_id ?? '' }}">
	</td>
	<td>
		@php
			$variation = $ingredient['variation'];
			$multiplier = $ingredient['multiplier'];
			$allow_decimal = $ingredient['allow_decimal'];
			$qty_available = 0;
			if($ingredient['enable_stock'] == 1) {
				$max_qty_rule = !empty($variation->variation_location_details[0]->qty_available) ? $variation->variation_location_details[0]->qty_available : 0;
				$qty_available = $max_qty_rule;
				$max_qty_rule = $max_qty_rule / $multiplier;
				$max_qty_msg = __('validation.custom-messages.quantity_not_available', ['qty'=> number_format($max_qty_rule, 2), 'unit' => $ingredient['unit']  ]);
			}
			
		@endphp
		<div class="@if(!empty($ingredient['sub_units'])) input_inline @else input-group @endif">
		    
		    <input type="hidden" value="{{number_format($ingredient['quantity'],3)}}" class="hidden_qnty"/>
			<input 
			type="hidden" 
			data-min="1" 
			class="form-control input-sm input_number mousetrap total_quantities" 
			value="{{number_format($ingredient['quantity'],3)}}" 
			{{-- name="ingredients[{{$ingredient['id']}}][quantity]"  --}}
			data-allow-overselling="@if(empty($pos_settings['allow_overselling'])){{'false'}}@else{{'true'}}@endif" />

			<input 
			type="text" 
			data-min="1" 
			class="form-control input-sm input_number mousetrap ing_quantity" 
			value="{{ (!empty($ingredient['ing_quantity']) ? number_format($ingredient['ing_quantity'],3): number_format($ingredient['quantity'],3)) }}"
			name="ingredients[{{$ingredient['id']}}][ing_quantity]"  />

			<span class="@if(empty($ingredient['sub_units'])) input-group-addon @endif line_unit_span">
			@if(empty($ingredient['sub_units'])) 
				{{$ingredient['unit']}}
			@else
				<select name="ingredients[{{$ingredient['id']}}][sub_unit_id]" class="input-sm form-control sub_unit" 
				@if(!empty($manufacturing_settings['disable_editing_ingredient_qty']))
				disabled="" 
				@endif
			>
					@foreach($ingredient['sub_units'] as $key => $value)
						<option 
							value="{{$key}}" 
							data-allow_decimal="{{$value['allow_decimal']}}"
							data-multiplier="{{$value['multiplier']}}"
							data-unit_name="{{$value['name']}}"
							@if($ingredient['sub_unit_id'] == $key) selected @endif>{{$value['name']}}</option>
					@endforeach
				</select>

				@if(!empty($manufacturing_settings['disable_editing_ingredient_qty']))
					<input type="hidden" name="ingredients[{{$ingredient['id']}}][sub_unit_id]" value="{{$ingredient['sub_unit_id']}}">
				@endif
			@endif
			</span>
		</div>
	</td>
	<td>
		<div class="input-group">
			<input type="text" name="ingredients[{{$ingredient['id']}}][mfg_waste_percent]" value="{{@format_quantity($ingredient['waste_percent'])}}" class="form-control input-sm input_number mfg_waste_percent">
			<span class="input-group-addon"><i class="fa fa-percent"></i></span>
		</div> 
	</td>
	<td>
		<span class="row_final_quantity">{{number_format($ingredient['final_quantity'],3)}}</span> <span class="row_unit_text">{{$ingredient['unit']}}</span>
		<input type="hidden" class="line_final_quantity" name="ingredients[{{$ingredient['id']}}][quantity]" value="{{@format_quantity($ingredient['final_quantity'])}}"/>
	</td>
	<td>
		<span class="row_avg_rate"></span>
	</td>
	<td>
		{{-- 
    	    <!-- this class is remove from span .ingredient_total_price-->
    		<span class="display_currency" data-currency_symbol="true">{{@num_format($ingredient['sell_price_inc_tax'])}}</span>
    	
    		<input type="hidden" class="sell_price_inc_tax" value="{{ $ingredient['sell_price_inc_tax'] }}">
    		<!--this class remove from input .total_price-->
    		<input type="hidden" class="new_total_price" value="{{ $ingredient['new_total_price'] }}"> 
		--}}
		
		
		<span class="display_currency ingredient_total_price" data-currency_symbol="true">1</span>
	
		<input type="hidden" class="sell_price_inc_tax" value="1">
		<!--this class remove from input .total_price / new_total_price-->
		<input type="hidden" class="total_price" value="1">
	</td>
</tr>