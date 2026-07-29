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
    @php
        $stylesheetPath = public_path($stylesheet);
        $stylesheetVersion = file_exists($stylesheetPath) ? (string) filemtime($stylesheetPath) : $version;
    @endphp
    <link rel="stylesheet" href="{{ asset($stylesheet) }}?v={{ $stylesheetVersion }}">
@endforeach
@include('filament.components.erp.form-scripts-styles')
