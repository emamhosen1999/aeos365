$c = app(\Aero\Notifications\Http\Controllers\NotificationCenterController::class);
$ref = new ReflectionClass($c);
$tp = $ref->getMethod('tabPayload'); $tp->setAccessible(true);
$st = $ref->getMethod('stats'); $st->setAccessible(true);

// TENANT context
tenancy()->initialize(\Aero\Platform\Models\Tenant::find('72c6805f-a788-426d-92ad-7b3bc72b2f00'));
$user = \Aero\Core\Models\User::where('email','admin@democorp.com')->first(); auth()->login($user);
$troute = app('router')->getRoutes()->getByName('notifications.index');
$req = \Illuminate\Http\Request::create('/notifications','GET'); $req->setRouteResolver(fn()=>$troute); $req->setUserResolver(fn()=>$user);
app()->instance('request',$req);
$tpl = $tp->invoke($c,'templates',$req);
$ch  = $tp->invoke($c,'channels',$req);
$stats = $st->invoke($c);
echo "TENANT templates=".count($tpl['templates'])." | channels.inheritance.mail.source=".$ch['inheritance']['mail']['source']." | stats.emailQuota.used=".$stats['emailQuota']['used']." unlimited=".var_export($stats['emailQuota']['unlimited'],true).PHP_EOL;
tenancy()->end();

// PLATFORM context
$proute = app('router')->getRoutes()->getByName('admin.notifications.index');
$preq = \Illuminate\Http\Request::create('http://admin.aeos365.test/notifications','GET'); $preq->setRouteResolver(fn()=>$proute);
app()->instance('request',$preq);
$fleet = $tp->invoke($c,'fleet',$preq);
$bc = $tp->invoke($c,'broadcasts',$preq);
echo "PLATFORM fleet.summary.sent=".($fleet['fleet']['summary']['sent'] + $fleet['fleet']['summary']['delivered'] ?? 'n/a')." worst=".count($fleet['fleet']['worstOffenders'])." | broadcasts.tenants=".count($bc['broadcast']['tenants']).PHP_EOL;
