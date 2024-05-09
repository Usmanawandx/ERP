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
@section('title', __('manufacturing::lang.recipe'))

@section('content')
@include('manufacturing::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('manufacturing::lang.recipe')</h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        @can("manufacturing.add_recipe")
        @slot('tool')
            <div class="box-tools">
                <button class="btn btn-block btn-primary btn-modal" data-container="#recipe_modal" data-href="{{action('\Modules\Manufacturing\Http\Controllers\RecipeController@create')}}">
                    <i class="fa fa-plus"></i> @lang( 'messages.add' )</button>
            </div>
            
            <div class="box-tools m-5">
                 <button type="button" class="btn btn-primary btn-uploadCSV-recipe pull-right">
                    <i class="fa fa-plus"></i> Upload CSV
                </button>
            </div>
           
           
        @if (session('notification') || !empty($notification))
            <br>
            <div class="row">
                <div class="col-sm-12">
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        @if(!empty($notification['msg']))
                            {{$notification['msg']}}
                        @elseif(session('notification.msg'))
                            {{ session('notification.msg') }}
                        @endif
                    </div>
                </div>  
            </div>     
        @endif
           
           
        @endslot
        @endcan
        <div class="table-responsive">
            <table class="table table-bordered table-striped hide-footer dataTable table-styling table-hover table-primary no-footer" id="recipe_table">
                <thead>
                    <tr>
                        <th class="main-colum">@lang( 'messages.action' )</th>
                        <th><input type="checkbox" id="select-all-row"></th>
                        <th>@lang( 'manufacturing::lang.recipe' )</th>
                        <th>@lang( 'product.category' )</th>
                        <th>@lang( 'product.sub_category' )</th>
                        <th>@lang( 'lang_v1.quantity' )</th>
                        <th>@lang( 'lang_v1.price' ) @show_tooltip(__('manufacturing::lang.price_updated_live'))</th>
                        <th>@lang( 'sale.unit_price' )</th>
                        
                        
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <td colspan="8">
                            <button type="button" class="btn btn-xs btn-danger" id="mass_update_product_price" >@lang('manufacturing::lang.update_product_price')</button> @show_tooltip(__('manufacturing::lang.update_product_price_help'))
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade" id="recipe_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
<!-- CSV  -->
    <div id="uploadCsvModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-sm">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Upload Recipe CSV </h4>
                </div>
                <form action="{{action('\Modules\Manufacturing\Http\Controllers\RecipeController@loadCsv')}}" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    @csrf
                    <label for="csv">Import CSV</label>
                    <input type="file" name="csv" class="form-control" id="csv" required>
                    <br>
                    <a href="{{ asset('files/import_recipe.xlsx') }}" class="btn btn-success btn-sm" download><i class="fa fa-download"></i> @lang('lang_v1.download_template_file')</a>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <input type="submit" class="btn btn-primary" value="Upload" name="submitBtn">
                </div>
                </form>
            </div>
        </div>
    </div>
@stop
@section('javascript')

    @include('manufacturing::layouts.partials.common_script')

@endsection