<?php return array (
  'aero/auth' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Auth\\AeroAuthServiceProvider',
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
  'aero/i18n' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\I18n\\AeroI18nServiceProvider',
    ),
  ),
  'aero/infrastructure' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Infrastructure\\Providers\\InfrastructureServiceProvider',
    ),
  ),
  'aero/installation' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Installation\\Providers\\AeroInstallationServiceProvider',
    ),
  ),
  'aero/kernel' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Kernel\\AeroKernelServiceProvider',
    ),
  ),
  'aero/notifications' => 
  array (
    'providers' => 
    array (
      0 => 'Aero\\Notifications\\AeroNotificationsServiceProvider',
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
  'laravel/pail' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Pail\\PailServiceProvider',
    ),
  ),
  'laravel/passkeys' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Passkeys\\PasskeysServiceProvider',
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
  'orchestra/canvas' => 
  array (
    'providers' => 
    array (
      0 => 'Orchestra\\Canvas\\LaravelServiceProvider',
    ),
  ),
  'orchestra/canvas-core' => 
  array (
    'providers' => 
    array (
      0 => 'Orchestra\\Canvas\\Core\\LaravelServiceProvider',
    ),
  ),
  'spatie/laravel-activitylog' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Activitylog\\ActivitylogServiceProvider',
    ),
  ),
  'spatie/laravel-backup' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Backup\\BackupServiceProvider',
    ),
  ),
  'spatie/laravel-medialibrary' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider',
    ),
  ),
  'spatie/laravel-signal-aware-command' => 
  array (
    'aliases' => 
    array (
      'Signal' => 'Spatie\\SignalAwareCommand\\Facades\\Signal',
    ),
    'providers' => 
    array (
      0 => 'Spatie\\SignalAwareCommand\\SignalAwareCommandServiceProvider',
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