@php



    use App\Support\Erp\ErpAssetVersion;



    use App\Support\Erp\ErpPageAssets;



    $version = ErpAssetVersion::bundle();



    $stylesheets = [

        'css/erp-shell.css',

        ...ErpPageAssets::coreStylesheets(),

    ];



@endphp



@foreach ($stylesheets as $stylesheet)



    <link rel="stylesheet" href="{{ asset($stylesheet) }}?v={{ $version }}">



@endforeach

@include('filament.components.erp.form-scripts-styles')

