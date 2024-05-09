@extends('layouts.app')
<style>
.btn-edt {
    font-size: 14px !important;
    padding: 7px 8px 9px !important;
    border-radius: 50px !important;
}

.btn-vew {
    font-size: 14px !important;
    padding: 9px 8px 9px !important;
    border-radius: 50px !important;
}

.btn-dlt {
    font-size: 14px !important;
    padding: 7px 8px 9px !important;
    border-radius: 50px !important;
}
    
</style>
@section('title', __('manufacturing::lang.production'))

@section('content')
@include('manufacturing::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('manufacturing::lang.production')</h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        @slot('tool')
            <div class="box-tools">
                <a class="btn btn-block btn-primary" href="{{action('\Modules\Manufacturing\Http\Controllers\ProductionController@create')}}">
                    <i class="fa fa-plus"></i> @lang( 'messages.add' )</a>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped hide-footer dataTable table-styling table-hover table-primary" id="productions_table">
                 <thead>
                    <tr>
                        <th class="main-colum">@lang('messages.action')</th>
                        <th>@lang('messages.date')</th>
                        <th>@lang('purchase.ref_no')</th>
                        <th>@lang('purchase.location')</th>
                        <th>@lang('sale.product')</th>
                        <th>@lang('lang_v1.quantity')</th>
                        <th>@lang('manufacturing::lang.total_cost')</th>
                        
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade" id="recipe_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
@stop
@section('javascript')
    @include('manufacturing::layouts.partials.common_script')
@endsection
