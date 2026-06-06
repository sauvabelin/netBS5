# TODO — code review findings

Findings from the `feat/password-reset` review (2026-05-30). Grouped by severity.
Each item has file:line and a concrete fix. Items marked **[outside password-reset]**
are pre-existing or cross-cutting issues surfaced during the review.

---

## Critical

### 1. ✅ DONE — `TrackLoginTimeListener` crashes every JWT / API login (500) — **[outside password-reset]**
Fixed: added `getMainRequest()`/`hasSession()` guard + `tests/EventSubscriber/TrackLoginTimeListenerTest.php` (3 tests, green).
`netBS/core/SecureBundle/EventSubscriber/TrackLoginTimeListener.php:20`

`LoginSuccessEvent` is dispatched by `AuthenticatorManager` for **every** firewall
(`vendor/symfony/security-http/Authentication/AuthenticatorManager.php:241`), including the
two `stateless: true` firewalls in `config/packages/security.yaml` (`gettoken` json_login,
`api` jwt). On a stateless request there is no session, so the unguarded
`$this->requests->getSession()` throws `SessionNotFoundException` → a valid API/JWT login
becomes a 500. The listener is registered globally (`services.yml:49`) with no firewall scoping.

Fix — guard on session availability (symmetric with `SessionInvalidationListener:39`):

```php
public function onLoginSuccess(LoginSuccessEvent $event): void
{
    $request = $this->requests->getMainRequest();
    if ($request === null || !$request->hasSession()) {
        return; // stateless (JWT / json_login) firewall — no session to track
    }
    $request->getSession()->set(
        SessionInvalidationListener::LOGIN_TIME_KEY,
        (new \DateTimeImmutable())->getTimestamp(),
    );
}
```

Do **not** use a blind try/catch — that would also hide a genuinely-missing session on the
form-login firewall.

### 2. ✅ DONE — New password has no validation (empty / trivial password accepted) — also **[outside password-reset]**
Fixed: added one shared `NetBS\SecureBundle\Validator\Constraints\StrongPassword` compound constraint
(NotBlank + Length min 10 + PasswordStrength MEDIUM, offline) applied at **all four** entry points —
`ChangePassword::$newPassword` (reset + my-account), `AdminChangePassword::$password` (admin change),
and the `UserType` password field (user creation). Props native-typed. Tests:
`tests/Validator/StrongPasswordPolicyTest.php` (13) + full suite green (33).
`netBS/core/SecureBundle/Model/ChangePassword.php:18`

`$newPassword` has zero constraints. The `RepeatedType` in `ChangePasswordType.php:20` only
enforces that the two fields *match*, not that the value is non-empty or strong. Two blank
boxes submit as `""`, match, validate, and get hashed and stored. This affects **both** the
reset flow (`ResetPasswordController.php:109-110`) and the existing my-account change-password
flow (`UserController::accountPageAction`), and the reset flow is exactly the one with no
"old password" friction.

Fix — constrain the model property so it holds for every consumer:

```php
#[Assert\NotBlank]
#[Assert\Length(min: 8, max: 4096)]
// #[Assert\NotCompromisedPassword]  // optional; needs network, disable in tests
protected ?string $newPassword = null;
```

---

## Important

### 3. Synchronous mailer + 0–80 ms jitter → username enumeration via timing
`netBS/core/SecureBundle/Controller/ResetPasswordController.php:198`, `:214-217`;
`config/packages/messenger.yaml` (empty `routing:`)

The unknown-user path returns after only `jitter()` (0–80 ms). The valid-active-user path does
a DB delete + token generate/persist + template render + a synchronous `mailer->send()` (SMTP
round-trip, often hundreds of ms). The response-time delta far exceeds the jitter window and is
a reliable enumeration oracle, defeating the constant-response design. (The body/status are
correctly uniform — this is purely timing.)

Fix — route mail off the request thread:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: async
```

…and ensure a worker runs. With async delivery the 0–80 ms jitter is plausible cover.

### 4. `reset()` consumes the token before the password is persisted
`netBS/core/SecureBundle/Controller/ResetPasswordController.php:108-112`

`removeResetRequest($token)` runs an **immediate** DQL `DELETE`
(`vendor/symfonycasts/reset-password-bundle/.../ResetPasswordRequestRepositoryTrait`), *before*
`$this->em->flush()`. If the flush throws (deadlock, connection drop, constraint), the password
is unchanged but the token is already gone → the user's reset link is dead and they must restart
the whole flow. The failure is also unlogged (bare 500).

Fix — persist first, consume the token only after a successful flush, and log a flush failure:

```php
if ($form->isSubmitted() && $form->isValid()) {
    $plain = $form->getData()->getNewPassword();
    $user->setPassword($hasher->hashPassword($user, $plain));
    $user->setPasswordChangedAt(new \DateTimeImmutable());
    try {
        $this->em->flush();
    } catch (\Throwable $e) {
        $this->logger->error('password_reset.persist_failed', [
            'user_id' => $user->getId(),
            'exception' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'Une erreur est survenue. Réessayez ou redemandez un lien.');
        return $this->redirectToRoute('netbs.secure.password_reset.request');
    }
    $this->resetPasswordHelper->removeResetRequest($token);
    $this->cleanSessionAfterReset();
    $this->dispatcher->dispatch(new PasswordResetCompletedEvent($user, $request->getClientIp()));
    // ...
}
```

### 5. Test coverage: the entire completion half of the feature is untested
The request/email-issuance path is well covered; the high-consequence completion path is not.
Highest-risk gaps:

- **Full reset completion, end-to-end** — `GET /reset-password/{token}` → submit new password →
  assert the stored hash verifies the new password, `passwordChangedAt` is set, the
  `ResetPasswordRequest` row is gone, `PasswordResetCompletedEvent` fired, redirect to login.
  (`ResetPasswordController.php:73-122`)
- **Token replay** — re-driving the same token after a successful reset must be rejected
  (guards against leaked-link replay). (`:108`)
- **Invalid / expired / no-session-token** on the reset form must give the generic redirect,
  never a 500 and never a reason that distinguishes invalid vs expired. (`:83-100`)
- **`TrackLoginTimeListener` has no test at all** — it is the *producer* of `LOGIN_TIME_KEY`;
  if it regresses, every `SessionInvalidationListener` test still passes while the real feature
  silently stops working. Add a test asserting it sets the key on `LoginSuccessEvent` **and**
  no-ops on a sessionless (stateless) request (see Critical #1).
- **Rate-limiter branches are structurally unreachable in tests**: `framework.yaml:30-38` sets
  both limiters to `no_limit` under `when@test`, so the `ip_rate_limited` / `user_rate_limited`
  paths (`:128-135`, `:154-161`) — the headline abuse controls — can't be covered functionally.
  Cover them with a unit test of `dispatchResetEmail` using a stubbed `RateLimiterFactory` whose
  `consume()` returns a non-accepted limit; assert no email, no event, and the rate-limit log line.
- **Inactive-user request path** (`:147-151`) — a deactivated account must get no email; only the
  no-email case is currently tested.
- **Mailer transport failure** (`:197-209`) — assert a known user still sees the generic
  banner/redirect (no 500) when the transport throws; this is the documented anti-enumeration
  property, asserted nowhere.

---

## Suggestions / minor

### 6. Dead `TooManyPasswordRequestsException` catch
`ResetPasswordController.php:171`

`removeRequests($user)` (`:167`) deletes all of the user's pending request rows immediately
before `generateResetToken()`, so the bundle's internal throttle
(`getMostRecentNonExpiredRequestDate`) never trips and the catch is unreachable. This is
intentional (anti-abuse delegated to the IP/user limiters) but the catch is dead code — drop it,
or keep the bundle throttle as defence-in-depth by reconsidering the unconditional `removeRequests`.

### 7. `reset()` does not re-check `getIsActive()` on completion
`ResetPasswordController.php:107-117`

`getIsActive()` is only checked at request time (`:147`). A user deactivated between request and
completion (within the token lifetime) can still set a new password. Low impact (short-lived,
emailed token) but a cheap guard: re-check after `validateTokenAndFetchUser` and redirect if inactive.

### 8. `passwordChangedAt` not set on normal password changes — **[outside password-reset]**
`UserController::accountPageAction` (my-account self-service) and the admin change-password flow
do not call `setPasswordChangedAt()`. So `SessionInvalidationListener` (which bails on a null
`passwordChangedAt`) never invalidates other sessions for an ordinary password change — only for
resets. If the listener is meant to be the general "log out other sessions on password change"
mechanism, set `passwordChangedAt` in those flows too; otherwise document that it's reset-scoped.

### 9. Reset events: dead constants + copy-paste
`netBS/core/SecureBundle/Event/PasswordResetRequestedEvent.php`,
`PasswordResetCompletedEvent.php`

Two `public const NAME` are never referenced (dispatch uses `::class`) — drop them. The two
classes are otherwise identical; extract an `abstract class PasswordResetEvent` holding
`user`/`ip` while keeping the two concrete leaf types (the distinct types are justified — they
give type-based subscriber dispatch).

### 10. `ChangePassword` model: untyped properties + unconditional `UserPassword` constraint — partially done
`netBS/core/SecureBundle/Model/ChangePassword.php`

DONE: properties native-typed (`?string`) as part of #2. STILL OPEN: the `#[Assert\UserPassword]`
validation-groups refinement below.

Native-type the properties (`?string`) to match the rest of the new code instead of `@var string`.
The `#[Assert\UserPassword]` on `oldPassword` is unconditional even though `require_current` is a
per-form option; it only happens not to fire in the reset flow because the field isn't added to
the form. Make the dual regime explicit with form `validation_groups` rather than relying on that
coupling.

### 11. Test comments mischaracterize the email-count mechanism
`tests/Controller/ResetPasswordControllerTest.php:48-49`, `:92-96`

`assertEmailCount()` reads the `mailer.message_logger_listener` service, not the profiler. The
reason the assertion must precede `followRedirect()` is that `KernelBrowser` reboots the kernel
between requests, which *resets* that listener — not "profile swapping". Reword, and note that
`enableProfiler()` (`:40`, `:79`) is not required for these assertions.

### 12. `test_resubmit...` doesn't assert what its name claims
`tests/Controller/ResetPasswordControllerTest.php:76-97`

The name says it "invalidates the old token", but the test only asserts `assertEmailCount(1)`
twice. To actually cover GitHub-style re-issue, extract the first email's token and assert it no
longer validates after the second request issues a fresh one.

### 13. `SessionInvalidationListener` null-`LOGIN_TIME_KEY` gap is undocumented
`netBS/core/SecureBundle/EventSubscriber/SessionInvalidationListener.php:44-47`

Sessions with no `LOGIN_TIME_KEY` (pre-feature logins, or — per Critical #1 — any stateless path)
are never invalidated by a password change. The window is bounded by the 7-day remember-me
lifetime, so this is acceptable, but add a comment so the early return isn't mistaken for
"invalidation ran".

---

## Verified OK (no action — recorded so they aren't re-flagged)

- **`from` address fallback works.** `%env(default::MAILER_FROM)%` resolves to `null` when the
  var is unset or empty (`EnvVarProcessor` returns null for an empty `default:`), so the
  `?? 'no-reply@netbs.localhost'` fallback (`:189`) fires correctly.
- **remember-me cookie is invalidated by the password change.** Symfony's default
  `signature_properties` is `['password']`, so the HMAC over the (now-changed) password hash no
  longer validates and the old 7-day cookie is rejected — no silent re-login after a reset.
- **`<=` same-second comparison** (`SessionInvalidationListener:51`) is correct: both timestamps
  are second-resolution, so `<=` is needed to catch a login in the same second as the change.
- **`ResetPasswordRequest` entity** is exemplary — non-null user + `ON DELETE CASCADE` +
  constructor `initialize()` make illegal/orphaned states unrepresentable.
- **`passwordChangedAt` nullable is the right design** — do not backfill; backfilling to deploy
  time would force-log-out the entire userbase on release for no security reason.
