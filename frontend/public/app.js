const app = document.querySelector('#app');
const notice = document.querySelector('#notice');
const parameters = new URLSearchParams(location.search);
const initialLink = parameters.get('token') ? {
  type: location.pathname === '/auth/accept-invitation' ? 'invitation' : 'registration',
  token: parameters.get('token'), email: parameters.get('email') || '', existing: parameters.get('has_account') === '1',
} : null;
if (initialLink) history.replaceState({}, '', location.pathname);

const state = {
  token: sessionStorage.getItem('iam_demo_token'), user: null, config: {},
  email: initialLink?.email || '', emailChallenge: null, pendingGoogle: null,
  link: initialLink, sdk: null, googleChallenge: null, googleAttempt: 0, autoSwitched: false,
};
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const formValues = (form) => Object.fromEntries(new FormData(form));
const input = (name, label, value = '', type = 'text', extra = '', id = name) => `<label for="${id}">${label}</label><input id="${id}" name="${name}" type="${type}" value="${escape(value)}" ${extra}>`;
const date = (value) => value ? new Date(value).toLocaleString() : 'Not available yet';

function notify(message, error = false) {
  notice.textContent = message;
  notice.dataset.error = String(error);
  notice.hidden = !message;
}

function clearSession() {
  sessionStorage.removeItem('iam_demo_token');
  state.token = null;
  state.user = null;
  state.autoSwitched = false;
}

async function api(path, { method = 'GET', body, authenticated = true } = {}) {
  const response = await fetch(`/api/v1${path}`, {
    method, headers: {
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(authenticated && state.token ? { Authorization: `Bearer ${state.token}` } : {}),
    }, body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await response.text();
  let result;
  try { result = text ? JSON.parse(text) : null; } catch { throw new Error('The API returned an unexpected response.'); }
  if (!response.ok) {
    if (response.status === 401 && authenticated && state.token) {
      clearSession();
      renderLogin();
    }
    if (result?.code === 'profile_incomplete') {
      await refreshUser();
      renderProfile();
    }
    if (response.status === 429) {
      const seconds = Number(response.headers.get('retry-after'));
      throw new Error(seconds > 0 ? `Too many attempts. Try again in ${seconds} seconds.` : 'Too many attempts. Please wait a minute before trying again.');
    }
    const details = result?.errors && !result.errors.type ? Object.values(result.errors).flat().join('\n') : '';
    throw new Error(details || result?.message || `Request failed (${response.status}).`);
  }
  return result;
}

function submit(id, handler) {
  document.getElementById(id)?.addEventListener('submit', (event) => {
    event.preventDefault();
    run(() => handler(formValues(event.target), event.target), event.target);
  });
}

async function run(action, container = app) {
  const buttons = [...container.querySelectorAll('button')].filter((button) => !button.disabled);
  buttons.forEach((button) => { button.disabled = true; });
  notify('');
  try { await action(); } catch (error) { notify(error.message, true); }
  finally { buttons.forEach((button) => { button.disabled = false; }); }
}

function bindClick(id, handler) {
  document.getElementById(id)?.addEventListener('click', () => run(handler));
}

async function refreshUser() {
  state.user = (await api('/auth/me')).data;
}

async function finishAuthentication(result) {
  state.token = result.data.access_token;
  sessionStorage.setItem('iam_demo_token', state.token);
  state.user = result.data.user;
  state.emailChallenge = null;
  state.autoSwitched = false;
  await showAccount();
}

async function showAccount() {
  if (!state.user.profile_complete) {
    renderProfile();
    return;
  }
  if (state.pendingGoogle) {
    try {
      await api('/auth/google-identities', { method: 'POST', body: state.pendingGoogle });
      notify('Google is now linked to your account.');
    } catch (error) {
      notify(`${error.message} Your email session remains available. You can restart Google linking below.`, true);
    }
    state.pendingGoogle = null;
  }
  if (state.link?.type === 'invitation') {
    renderEmailLink();
    return;
  }
  if (!state.autoSwitched && !state.user.current_member_role_id && state.user.default_member_role_id) {
    state.autoSwitched = true;
    const available = state.user.member_roles.some((role) => role.id === state.user.default_member_role_id);
    if (available) {
      try {
        state.user = (await api('/auth/switch-member-role', { method: 'POST', body: { member_role_id: state.user.default_member_role_id } })).data;
      } catch (error) { notify(error.message, true); }
    }
  }
  renderDashboard();
}

function googleSection() {
  return state.config.google_client_id
    ? '<div id="google-button" aria-live="polite"><p class="hint">Loading Google sign-in…</p></div>'
    : '<p class="hint">Google sign-in is not configured on this environment.</p>';
}

function renderLogin() {
  state.googleAttempt += 1;
  app.innerHTML = `<section class="card auth"><p class="eyebrow">Your workspace starts here</p><h1>Sign in or create an account</h1>
    <p class="hint">Use your email or Google. You can explore before creating an organization.</p>
    ${state.link?.type === 'invitation' ? `<p>You have been invited as <strong>${escape(state.link.email)}</strong>.</p>` : ''}
    <form id="email-form">${input('email', 'Email address', state.email, 'email', 'required autocomplete="email"')}<button class="full">Continue with email</button></form>
    <div class="divider">or</div>${googleSection()}
    <div class="actions"><button id="registration-link" class="secondary" type="button">Create an organization by email</button></div>
    ${state.link ? '<button id="open-email-link" class="secondary full" type="button">Continue with the email link</button>' : ''}
    </section>`;
  submit('email-form', ({ email }) => requestEmailCode(email));
  prepareGoogle(false);
  bindClick('registration-link', renderRegistrationRequest);
  bindClick('open-email-link', renderEmailLink);
}

async function requestEmailCode(email) {
  const result = await api('/auth/email-challenges', { method: 'POST', authenticated: false, body: { email } });
  state.email = email;
  state.emailChallenge = result.challenge_id;
  renderOtp();
  notify(result.message);
}

function renderOtp() {
  state.googleAttempt += 1;
  app.innerHTML = `<section class="card auth"><p class="eyebrow">Check your email</p><h1>Enter your sign-in code</h1>
    <p>We sent a code to <strong>${escape(state.email)}</strong>.</p>
    ${state.pendingGoogle ? '<p class="hint">Confirm this email once to link your Google account.</p>' : ''}
    <form id="otp-form">${input('code', 'Six-digit code', '', 'text', 'class="otp" required pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code"')}<button class="full">Verify and continue</button></form>
    <div class="actions"><button id="resend-code" class="secondary">Send another code</button><button id="back-login" class="secondary">Back</button></div></section>`;
  submit('otp-form', async ({ code }) => finishAuthentication(await api('/auth/email-challenge-verifications', {
    method: 'POST', authenticated: false, body: { challenge_id: state.emailChallenge, code },
  })));
  bindClick('resend-code', () => requestEmailCode(state.email));
  bindClick('back-login', () => { state.pendingGoogle = null; state.emailChallenge = null; renderLogin(); });
}

async function googleSdk() {
  if (!state.sdk) state.sdk = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.onload = resolve;
    script.onerror = () => { state.sdk = null; reject(new Error('Google sign-in could not load. Check your connection and browser extensions.')); };
    document.head.append(script);
  });
  await state.sdk;
}

async function googleChallenge() {
  if (state.googleChallenge && Date.parse(state.googleChallenge.expires_at) > Date.now() + 1000) {
    return state.googleChallenge;
  }
  const challenge = await api('/auth/google-challenges', { method: 'POST', body: {}, authenticated: false });
  state.googleChallenge = challenge;
  return challenge;
}

async function prepareGoogle(linking) {
  const container = document.getElementById('google-button');
  if (!container) return;
  const attempt = ++state.googleAttempt;
  const current = () => container.isConnected && attempt === state.googleAttempt;
  try {
    await googleSdk();
    if (!current()) return;
    const challenge = await googleChallenge();
    if (!current()) return;
    google.accounts.id.initialize({
      client_id: state.config.google_client_id, nonce: challenge.nonce, auto_select: false,
      callback: (response) => {
        if (!current()) return;
        run(async () => {
          const body = { challenge_id: challenge.challenge_id, credential: response.credential };
          if (linking) {
            await api('/auth/google-identities', { method: 'POST', body });
            state.googleChallenge = null;
            state.googleAttempt += 1;
            container.textContent = 'Google is linked to your account.';
            notify('Google is linked to your account.');
            return;
          }
          const result = await api('/auth/google-challenge-verifications', { method: 'POST', body, authenticated: false });
          state.googleChallenge = null;
          if (result.status === 'email_verification_required') {
            state.pendingGoogle = body;
            await requestEmailCode(result.email);
          } else {
            await finishAuthentication(result);
          }
        });
      },
    });
    container.replaceChildren();
    google.accounts.id.renderButton(container, { theme: 'outline', size: 'large', text: 'continue_with' });
    setTimeout(() => {
      if (current()) prepareGoogle(linking);
    }, Math.max(1000, Date.parse(challenge.expires_at) - Date.now()));
  } catch (error) {
    if (!current()) return;
    container.innerHTML = `<p class="hint">${escape(error.message)}</p><button class="secondary" type="button">Retry Google sign-in</button>`;
    container.querySelector('button').addEventListener('click', () => {
      container.innerHTML = '<p class="hint">Loading Google sign-in…</p>';
      prepareGoogle(linking);
    });
  }
}

function renderProfile() {
  state.googleAttempt += 1;
  app.innerHTML = `<section class="card auth"><p class="eyebrow">One more step</p><h1>Complete your profile</h1><p>Your account is ready. Add your first and last name to continue.</p>
    <form id="complete-profile">${input('first_name', 'First name', state.user.first_name, 'text', 'required maxlength="100" autocomplete="given-name"')}${input('last_name', 'Last name', state.user.last_name, 'text', 'required maxlength="100" autocomplete="family-name"')}<button class="full">Save and continue</button></form>
    <div class="actions"><button id="profile-sessions" class="secondary">Manage sessions</button><button id="logout" class="secondary">Sign out</button></div></section>`;
  submit('complete-profile', async (body) => {
    await api('/auth/me', { method: 'PATCH', body });
    await refreshUser();
    await showAccount();
  });
  bindClick('profile-sessions', renderSessions);
  bindClick('logout', logout);
}

function organizationFields() {
  return `${input('name', 'Organization name', '', 'text', 'required maxlength="100"')}${input('currency_code', 'Functional currency', 'XOF', 'text', 'required pattern="[A-Za-z]{3}" maxlength="3"')}${input('timezone', 'Timezone', Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Porto-Novo', 'text', 'required')}`;
}

function renderRegistrationRequest() {
  app.innerHTML = `<section class="card auth"><h1>Create an organization by email</h1><p>Receive a link to set up an organization and create an account if needed.</p>
    <form id="request-registration">${input('email', 'Email address', state.email, 'email', 'required')}<button class="full">Send setup link</button></form><div class="actions"><button id="back-login" class="secondary">Back to sign-in</button></div></section>`;
  submit('request-registration', async (body) => {
    const result = await api('/auth/organization-registration-tokens', { method: 'POST', authenticated: false, body });
    notify(result.message);
  });
  bindClick('back-login', renderLogin);
}

function renderEmailLink() {
  const link = state.link;
  if (!link) { renderLogin(); return; }
  if (link.type === 'invitation' && state.token && state.user?.email !== link.email) {
    app.innerHTML = `<section class="card auth"><h1>This invitation is for another account</h1><p>Invited: <strong>${escape(link.email)}</strong>.</p><p>You are signed in as ${escape(state.user?.email)}.</p><button id="invitation-switch-account" class="full">Sign out and continue with this invitation</button></section>`;
    bindClick('invitation-switch-account', async () => { await logout(); renderEmailLink(); });
    return;
  }
  const isExisting = link.existing || state.user?.email === link.email;
  const names = link.type === 'invitation' || isExisting ? '' : `${input('first_name', 'First name', '', 'text', 'required maxlength="100"')}${input('last_name', 'Last name', '', 'text', 'required maxlength="100"')}`;
  app.innerHTML = `<section class="card wide"><p class="eyebrow">${escape(link.email)}</p><h1>${link.type === 'registration' ? 'Set up your organization' : 'Accept your invitation'}</h1>
    <p>This email link confirms access to the invited address.</p><form id="email-link-form">${names}${link.type === 'registration' ? organizationFields() : ''}<button class="full">${link.type === 'registration' ? 'Create organization' : 'Accept invitation'}</button></form>
    ${link.type === 'registration' || state.token ? `<div class="actions"><button id="link-back" class="secondary">${state.token ? 'Back to account' : 'Back to sign-in'}</button></div>` : ''}</section>`;
  submit('email-link-form', async (values) => {
    if (link.type === 'invitation') {
      const signedIn = Boolean(state.token);
      const result = await api(signedIn ? '/auth/invitations/accept' : '/iam/invitations/accept', {
        method: 'POST', authenticated: signedIn,
        body: signedIn ? { token: link.token } : { email: link.email, token: link.token },
      });
      state.link = null;
      history.replaceState({}, '', '/');
      if (signedIn) { await refreshUser(); await showAccount(); }
      else { await finishAuthentication(result); }
      notify('Invitation accepted. You are now signed in.');
      return;
    }
    const userNames = isExisting ? {} : { first_name: values.first_name, last_name: values.last_name };
    const result = await api('/auth/organization-registrations', {
      method: 'POST', authenticated: false, body: {
        email: link.email, token: link.token, ...userNames,
        organization: { name: values.name, currency_code: values.currency_code, timezone: values.timezone },
      },
    });
    state.link = null;
    state.email = link.email;
    history.replaceState({}, '', '/');
    if (state.token) { await refreshUser(); await showAccount(); } else { renderLogin(); }
    notify(result.message);
  });
  bindClick('link-back', () => state.token ? showAccount() : renderLogin());
}

function renderDashboard() {
  state.googleAttempt += 1;
  const user = state.user;
  const selected = user.member_roles.find((role) => role.id === user.current_member_role_id);
  app.innerHTML = `<section class="welcome"><div><p class="eyebrow">Your account</p><h1>Hello, ${escape(user.first_name)}</h1><p>${escape(user.email)}</p></div><div class="actions"><button id="sessions" class="secondary">Sessions</button><button id="logout" class="secondary">Sign out</button></div></section>
    ${state.link ? `<section class="card"><h2>${state.link.type === 'invitation' ? 'An invitation is waiting' : 'Organization setup link'}</h2><p>Addressed to ${escape(state.link.email)}.</p><div class="actions">${state.link.type === 'invitation' ? '<button id="accept-auth-invite">Accept with this account</button>' : ''}<button id="email-link" class="secondary">Continue with email link</button></div></section><br>` : ''}
    <div class="grid"><section class="card"><h2>Organizations & access</h2><p class="hint">${selected ? `Current: ${escape(selected.organization.name)} · ${escape(selected.role.name)}` : 'No organization selected. Choose an access below.'}</p>
    <div>${user.member_roles.length ? user.member_roles.map((role) => `<div class="row"><h3>${escape(role.organization.name)}</h3><span>${escape(role.role.name)}</span> ${role.is_default ? '<span class="badge">Default</span>' : ''} ${selected?.id === role.id ? '<span class="badge">Selected</span>' : ''}<div class="actions"><button data-switch="${escape(role.id)}" class="secondary">Switch</button><button data-default="${escape(role.id)}" class="secondary">${role.is_default ? 'Clear default' : 'Make default'}</button></div></div>`).join('') : '<p class="empty">No organization yet. Create one or accept an invitation.</p>'}</div>
    ${selected ? '<div class="actions"><button id="organization-tools">Organization settings & invitations</button></div>' : ''}</section>
    <section class="card"><h2>Create an organization</h2><form id="create-organization">${organizationFields()}<button class="full">Create organization</button></form></section>
    <section class="card"><h2>Profile</h2><form id="edit-profile">${input('first_name', 'First name', user.first_name, 'text', 'required maxlength="100"')}${input('last_name', 'Last name', user.last_name, 'text', 'required maxlength="100"')}<button class="full">Save profile</button></form></section>
    <section class="card"><h2>Link Google</h2><p class="hint">Use a recent email OTP session and the Google account with the same email. Already linked identities remain unchanged.</p>${googleSection()}<div class="actions"><button id="fresh-email" class="secondary">Confirm email again</button></div></section></div>`;
  bindClick('sessions', renderSessions);
  bindClick('logout', logout);
  prepareGoogle(true);
  bindClick('fresh-email', () => requestEmailCode(user.email));
  bindClick('organization-tools', renderOrganizationTools);
  bindClick('email-link', renderEmailLink);
  bindClick('accept-auth-invite', async () => {
    const result = await api('/auth/invitations/accept', { method: 'POST', body: { token: state.link.token } });
    state.link = null;
    history.replaceState({}, '', '/');
    await refreshUser(); renderDashboard(); notify(result.message);
  });
  app.querySelectorAll('[data-switch]').forEach((button) => button.addEventListener('click', () => run(async () => {
    state.user = (await api('/auth/switch-member-role', { method: 'POST', body: { member_role_id: button.dataset.switch } })).data;
    renderDashboard(); notify('Organization access selected.');
  })));
  app.querySelectorAll('[data-default]').forEach((button) => button.addEventListener('click', () => run(async () => {
    const id = button.dataset.default;
    await api('/auth/me', { method: 'PATCH', body: { default_member_role_id: user.default_member_role_id === id ? null : id } });
    await refreshUser(); renderDashboard(); notify('Default access updated.');
  })));
  submit('create-organization', async (body) => {
    const result = await api('/auth/organizations', { method: 'POST', body });
    await refreshUser(); renderDashboard(); notify(result.message);
  });
  submit('edit-profile', async (body) => {
    await api('/auth/me', { method: 'PATCH', body });
    await refreshUser(); renderDashboard(); notify('Profile saved.');
  });
}

async function renderSessions(cursor = null, append = false) {
  const result = await api(`/auth/sessions${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''}`);
  const rows = result.data.map((session) => {
    const device = session.last_request?.device;
    const location = session.last_request?.location;
    const label = device?.label || device?.name || 'Device';
    return `<div class="row"><h3>${escape(label)} ${session.is_current ? '<span class="badge">Current session</span>' : ''}</h3><p class="hint">${escape([device?.os, location?.city, location?.country].filter(Boolean).join(' · '))} · ${escape(date(session.last_used_at || session.created_at))}</p><p class="hint">${escape(session.authentication_method)} · Expires ${escape(date(session.expires_at))}</p><button class="danger" data-revoke="${escape(session.id)}" data-current="${session.is_current}">Sign out this session</button></div>`;
  }).join('');
  if (!append) {
    app.innerHTML = '<section class="card wide"><h1>Your sessions</h1><div id="session-list"></div><div class="actions"><button id="more-sessions" class="secondary" hidden>Load more</button><button id="sessions-back" class="secondary">Back to account</button><button id="revoke-all" class="danger">Sign out all sessions</button></div></section>';
    bindClick('sessions-back', showAccount);
    bindClick('revoke-all', async () => {
      if (!confirm('Sign out every session, including this one?')) return;
      await api('/auth/sessions', { method: 'DELETE' }); clearSession(); renderLogin(); notify('All sessions revoked.');
    });
  }
  document.getElementById('session-list').insertAdjacentHTML('beforeend', rows);
  const more = document.getElementById('more-sessions');
  more.hidden = !result.meta.next_cursor;
  more.onclick = () => run(() => renderSessions(result.meta.next_cursor, true));
  app.querySelectorAll('[data-revoke]').forEach((button) => { button.onclick = () => run(async () => {
    await api(`/auth/sessions/${encodeURIComponent(button.dataset.revoke)}`, { method: 'DELETE' });
    if (button.dataset.current === 'true') { clearSession(); renderLogin(); } else { await renderSessions(); }
    notify('Session revoked.');
  }); });
}

async function renderOrganizationTools() {
  const settings = (await api('/organization/settings')).data;
  const roles = await allPages('/iam/roles');
  const permissions = await allPages('/iam/permissions');
  const invitations = await allPages('/iam/invitations?include=roles');
  const customRoles = roles.filter((role) => !role.is_builtin);
  app.innerHTML = `<section class="welcome"><div><p class="eyebrow">Current organization</p><h1>${escape(settings.name)}</h1></div><button id="tools-back" class="secondary">Back to account</button></section><div class="grid">
    <section class="card"><h2>Organization settings</h2><p class="hint">Functional currency: ${escape(settings.functional_currency_code)}</p><form id="settings-form">${input('name', 'Name', settings.name, 'text', 'required maxlength="100"')}${input('timezone', 'Timezone', settings.timezone, 'text', 'required')}<button class="full">Save settings</button></form></section>
    <section class="card"><h2>Create an organization role</h2><form id="role-form">${input('name', 'Role name', '', 'text', 'required maxlength="100"', 'role-name')}<label>Permissions</label><div class="checkboxes">${permissions.map((permission) => `<label><input type="checkbox" name="permission_ids" value="${escape(permission.id)}">${escape(permission.name)}</label>`).join('')}</div><button class="full">Create role</button></form></section>
    <section class="card"><h2>Invite a member</h2><p class="hint">Choose at least one organization role. System roles cannot be offered.</p><form id="invite-form">${input('email', 'Email address', '', 'email', 'required')}<label>Roles</label><div class="checkboxes">${customRoles.length ? customRoles.map((role) => `<label><input type="checkbox" name="role_ids" value="${escape(role.id)}">${escape(role.name)}</label>`).join('') : '<p class="hint">Create a role first.</p>'}</div><button class="full" ${customRoles.length ? '' : 'disabled'}>Send invitation</button></form></section>
    <section class="card"><h2>Pending & expired invitations</h2>${invitations.length ? invitations.map((invitation) => `<div class="row"><h3>${escape(invitation.email)}</h3><p class="hint">${escape((invitation.roles || []).map((role) => role.name).join(', '))} · Expires ${escape(date(invitation.expires_at))}</p><div class="actions"><button class="secondary" data-resend="${escape(invitation.id)}">Resend email</button><button class="danger" data-delete-invite="${escape(invitation.id)}">Cancel</button></div></div>`).join('') : '<p class="empty">No pending invitations.</p>'}</section></div>`;
  bindClick('tools-back', async () => { await refreshUser(); renderDashboard(); });
  submit('settings-form', async (body) => { await api('/organization/settings', { method: 'PATCH', body }); await renderOrganizationTools(); notify('Organization settings saved.'); });
  submit('role-form', async (values, form) => {
    await api('/iam/roles', { method: 'POST', body: { name: values.name, permission_ids: new FormData(form).getAll('permission_ids') } });
    await renderOrganizationTools(); notify('Role created.');
  });
  submit('invite-form', async (values, form) => {
    await api('/iam/invitations', { method: 'POST', body: { email: values.email, role_ids: new FormData(form).getAll('role_ids') } });
    await renderOrganizationTools(); notify('Invitation sent. Open the recipient’s email to join.');
  });
  app.querySelectorAll('[data-resend]').forEach((button) => { button.onclick = () => run(async () => {
    await api(`/iam/invitations/${button.dataset.resend}/resend`, { method: 'POST', body: {} }); await renderOrganizationTools(); notify('A new invitation email was sent.');
  }); });
  app.querySelectorAll('[data-delete-invite]').forEach((button) => { button.onclick = () => run(async () => {
    await api(`/iam/invitations/${button.dataset.deleteInvite}`, { method: 'DELETE' }); await renderOrganizationTools(); notify('Invitation cancelled.');
  }); });
}

async function allPages(path) {
  const items = [];
  let cursor = null;
  do {
    const separator = path.includes('?') ? '&' : '?';
    const result = await api(`${path}${separator}per_page=100${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`);
    items.push(...result.data);
    cursor = result.meta?.next_cursor;
  } while (cursor);
  return items;
}

async function logout() {
  await api('/auth/logout', { method: 'POST', body: {} });
  clearSession(); state.pendingGoogle = null; renderLogin(); notify('Signed out.');
}

await run(async () => {
  state.config = await (await fetch('/config.json')).json();
  if (state.token) {
    await refreshUser(); await showAccount();
  } else if (state.link) {
    renderEmailLink();
  } else {
    renderLogin();
  }
});
