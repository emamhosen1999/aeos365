import { useEffect } from 'react';
import { useForm, Link } from '@inertiajs/react';
import AuthLayout from './AuthLayout.jsx';
import { Field, Input, Toggle, Button, Alert, Text, HStack, VStack, Mono } from '@aero/ui';

function uuidv4() {
  // crypto.randomUUID() only exists in secure contexts (HTTPS or localhost).
  // Over plain http://*.test it is undefined and throws, crashing the login
  // page before the form mounts. Fall back to a getRandomValues-based UUID v4,
  // then to Math.random as a last resort.
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map(x => x.toString(16).padStart(2, '0'));
    return `${h[0]}${h[1]}${h[2]}${h[3]}-${h[4]}${h[5]}-${h[6]}${h[7]}-${h[8]}${h[9]}-${h[10]}${h[11]}${h[12]}${h[13]}${h[14]}${h[15]}`;
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });
}

function getOrCreateDeviceId() {
  let id = localStorage.getItem('aeos_device_id');
  if (!id) {
    id = uuidv4();
    localStorage.setItem('aeos_device_id', id);
  }
  return id;
}

export default function Login({
  canResetPassword,
  status,
  canRegister,
  oauthProviders = [],
  deviceBlocked,
  deviceMessage,
  blockedDeviceInfo,
  demo = null,
}) {
  // Demo tenants ship persona chips (admin first). Non-demo tenants get `null`
  // from the controller, so nothing below ever renders outside the demo.
  const personas = Array.isArray(demo?.personas) ? demo.personas : [];

  const { data, setData, post, processing, errors, reset } = useForm({
    email:     personas[0]?.email ?? '',
    password:  personas[0]?.password ?? '',
    remember:  false,
    device_id: '',
  });

  const activePersona = personas.find(p => p.email === data.email) ?? null;

  useEffect(() => {
    setData('device_id', getOrCreateDeviceId());
  }, []);

  function pickPersona(persona) {
    setData(d => ({ ...d, email: persona.email, password: persona.password }));
  }

  function submit(e) {
    e?.preventDefault?.();
    // On the demo the password is intentionally visible and pre-filled; clearing
    // it on a failed attempt would silently restore the *first* persona's
    // password instead of the selected one.
    post(route('login'), { onFinish: () => { if (personas.length === 0) reset('password'); } });
  }

  return (
    <AuthLayout title="Sign in to your account">
      <form className="al-form" onSubmit={submit} noValidate>
        {personas.length > 0 && (
          <Alert intent="info" title="Live demo — no sign-up needed">
            <VStack gap={2}>
              <Text size="sm">Pick a persona — credentials are pre-filled. Nothing you do here is permanent.</Text>

              {personas.length > 1 && (
                <HStack gap={2} wrap="wrap">
                  {personas.map(p => (
                    <Button
                      key={p.email}
                      type="button"
                      size="sm"
                      intent={data.email === p.email ? 'primary' : 'soft'}
                      aria-pressed={data.email === p.email}
                      onClick={() => pickPersona(p)}
                    >
                      {p.label}
                    </Button>
                  ))}
                </HStack>
              )}

              {activePersona?.name && (
                <Text size="sm" tone="secondary">
                  Signs in as {activePersona.name}
                  {activePersona.role ? ` · ${activePersona.role}` : ''}
                </Text>
              )}

              <HStack gap={4} wrap="wrap">
                <Text size="sm" tone="secondary">Email&nbsp;<Mono>{data.email}</Mono></Text>
                <Text size="sm" tone="secondary">Password&nbsp;<Mono>{data.password}</Mono></Text>
              </HStack>

              <Button type="button" intent="primary" onClick={submit} loading={processing}>
                Enter the demo
              </Button>
            </VStack>
          </Alert>
        )}

        {status && <Alert intent="info">{status}</Alert>}

        {deviceBlocked && (
          <Alert intent="danger" title="Device blocked">
            <Text>{deviceMessage ?? 'This device has been blocked. Contact your administrator.'}</Text>
            {blockedDeviceInfo && (
              <Text size="sm" tone="secondary" style={{ marginTop: 6 }}>
                Last seen: {blockedDeviceInfo.device_name} · {blockedDeviceInfo.last_used_at}
              </Text>
            )}
          </Alert>
        )}

        <Field label="Email address" htmlFor="email" error={errors.email} required>
          <Input
            id="email"
            type="email"
            value={data.email}
            onChange={e => setData('email', e.target.value)}
            placeholder="you@company.com"
            autoComplete="email"
            autoFocus
            error={!!errors.email}
          />
        </Field>

        <Field label="Password" htmlFor="password" error={errors.password} required>
          <Input
            id="password"
            type="password"
            value={data.password}
            onChange={e => setData('password', e.target.value)}
            placeholder="••••••••"
            autoComplete="current-password"
            error={!!errors.password}
          />
        </Field>

        <HStack justify="between" align="center">
          <Toggle
            label="Remember me"
            checked={data.remember}
            onChange={e => setData('remember', e.target.checked)}
          />
          {canResetPassword && (
            <Link href={route('password.request')} className="al-link">
              Forgot password?
            </Link>
          )}
        </HStack>

        <input type="hidden" name="device_id" value={data.device_id} />

        {errors.device_id && (
          <Alert intent="danger">{errors.device_id}</Alert>
        )}

        <Button intent="primary" fullWidth loading={processing} type="submit" size="lg">
          Sign in
        </Button>

        {oauthProviders.length > 0 && (
          <>
            <div className="al-sep">
              <span className="al-sep-line" />
              <span className="al-sep-text">or continue with</span>
              <span className="al-sep-line" />
            </div>
            <div className="al-oauth-grid">
              {oauthProviders.map(p => (
                <a
                  key={p.name}
                  href={p.url}
                  className="aeos-btn aeos-btn-ghost"
                  style={{ flex: 1, justifyContent: 'center' }}
                >
                  {p.label}
                </a>
              ))}
            </div>
          </>
        )}

        {canRegister && (
          <Text tone="secondary" size="sm" style={{ textAlign: 'center', marginTop: 8 }}>
            Don't have an account?{' '}
            <Link href={route('platform.register.index')} className="al-link">
              Sign up
            </Link>
          </Text>
        )}
      </form>
    </AuthLayout>
  );
}
