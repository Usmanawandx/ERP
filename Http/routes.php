<?php

Route::group(['middleware' => ['web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'manufacturing', 'namespace' => 'Modules\Manufacturing\Http\Controllers'], function () {
    Route::get('/install', 'InstallController@index');
    Route::post('/install', 'InstallController@install');
    Route::get('/install/update', 'InstallController@update');
    Route::get('/install/uninstall', 'InstallController@uninstall');

    Route::get('/multiproduction_index', 'ProductionController@multiproduction_index');

    Route::get('/is-recipe-exist/{variation_id}', 'RecipeController@isRecipeExist');
    Route::get('/ingredient-group-form', 'RecipeController@getIngredientGroupForm');
    Route::get('/get-recipe-details', 'RecipeController@getRecipeDetails');
    Route::get('/get-recipe-details-multy', 'RecipeController@getRecipeDetailsMulty');
    Route::get('/get-ingredient-row/{variation_id}', 'RecipeController@getIngredientRow');
    Route::get('/add-ingredient', 'RecipeController@addIngredients');
    Route::get('/multi-production-create','ProductionController@multi_production_create');
    Route::post('/quantity_check','ProductionController@quantity_check');
    
    Route::resource('/recipe', 'RecipeController', ['except' => ['edit', 'update']]);
    Route::resource('/production', 'ProductionController');
    Route::resource('/settings', 'SettingsController', ['only' => ['index', 'store']]);

    Route::get('/report', 'ProductionController@getManufacturingReport');
    
    
    Route::post('/loadCsv','RecipeController@loadCsv');
    Route::get('/get_product/{cat_id}', 'RecipeController@get_product');
    
    Route::post('/add_contract','ProductionController@add_contractor');

    Route::post('/update-product-prices', 'RecipeController@updateRecipeProductPrices');
});
