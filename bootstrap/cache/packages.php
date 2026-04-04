<?php return array (
  'aero/cms' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Cms\\CmsServiceProvider',
    ),
    'aliases' => 
    array (
      'AeroCms' => 'Aero\\Cms\\Facades\\Cms',
    ),
  ),
  'aero/compliance' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Compliance\\AeroComplianceServiceProvider',
    ),
  ),
  'aero/core' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Core\\AeroCoreServiceProvider',
    ),
    'aliases' => 
    array (
      'AeroCore' => 'Aero\\Core\\Facades\\AeroCore',
    ),
  ),
  'aero/hrm' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\HRM\\AeroHrmServiceProvider',
    ),
  ),
  'aero/hrmac' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\HRMAC\\HRMACServiceProvider',
    ),
    'aliases' => 
    array (
      'HRMAC' => 'Aero\\HRMAC\\Facades\\HRMAC',
    ),
  ),
  'aero/platform' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Platform\\AeroPlatformServiceProvider',
    ),
    'aliases' => 
    array (
      'AeroPlatform' => 'Aero\\Platform\\Facades\\Platform',
    ),
  ),
  'aero/project' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Project\\AeroProjectServiceProvider',
    ),
  ),
  'aero/quality' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Quality\\Providers\\QualityModuleProvider',
    ),
  ),
  'aero/rfi' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Rfi\\AeroRfiServiceProvider',
    ),
  ),
  'aero/ui' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\UI\\AeroUIServiceProvider',
    ),
  ),
  'barryvdh/laravel-dompdf' => 
  array (
    'aliases' => 
    array (
      'PDF' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
      'Pdf' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
    ),
    'providers' => 
    array (
      0 => 'Barryvdh\\DomPDF\\ServiceProvider',
    ),
  ),
  'inertiajs/inertia-laravel' => 
  array (
    'providers' => 
    array (
      0 => 'Inertia\\ServiceProvider',
    ),
  ),
  'jenssegers/agent' => 
  array (
    'aliases' => 
    array (
      'Agent' => 'Jenssegers\\Agent\\Facades\\Agent',
    ),
    'providers' => 
    array (
      0 => 'Jenssegers\\Agent\\AgentServiceProvider',
    ),
  ),
  'laravel/boost' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Boost\\BoostServiceProvider',
    ),
  ),
  'laravel/cashier' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Cashier\\CashierServiceProvider',
    ),
  ),
  'laravel/fortify' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Fortify\\FortifyServiceProvider',
    ),
  ),
  'laravel/mcp' => 
  array (
    'aliases' => 
    array (
      'Mcp' => 'Laravel\\Mcp\\Server\\Facades\\Mcp',
    ),
    'providers' => 
    array (
      0 => 'Laravel\\Mcp\\Server\\McpServiceProvider',
    ),
  ),
  'laravel/pail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Pail\\PailServiceProvider',
    ),
  ),
  'laravel/roster' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Roster\\RosterServiceProvider',
    ),
  ),
  'laravel/sail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sail\\SailServiceProvider',
    ),
  ),
  'laravel/sanctum' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'maatwebsite/excel' => 
  array (
    'aliases' => 
    array (
      'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
    ),
    'providers' => 
    array (
      0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/collision' => 
  array (
    'providers' => 
    array (
      0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'spatie/laravel-activitylog' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Activitylog\\ActivitylogServiceProvider',
    ),
  ),
  'spatie/laravel-medialibrary' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider',
    ),
  ),
  'spatie/laravel-permission' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Permission\\PermissionServiceProvider',
    ),
  ),
  'stancl/tenancy' => 
  array (
    'aliases' => 
    array (
      'Tenancy' => 'Stancl\\Tenancy\\Facades\\Tenancy',
      'GlobalCache' => 'Stancl\\Tenancy\\Facades\\GlobalCache',
    ),
    'providers' => 
    array (
      0 => 'Stancl\\Tenancy\\TenancyServiceProvider',
    ),
  ),
  'tightenco/ziggy' => 
  array (
    'providers' => 
    array (
      0 => 'Tighten\\Ziggy\\ZiggyServiceProvider',
    ),
  ),
);