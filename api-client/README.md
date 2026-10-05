# Lahatre ERP · Bruno

Open this directory as an OpenCollection YAML collection in Bruno.

The collection contains 69 manual requests for Auth, IAM, Catalog, Master and
Organization. It has response scripts for storing IDs and tokens, but no runtime
test assertions. It does not yet cover every ERP endpoint or provide an unattended,
repeatable test suite.

## Start a local session

1. Select `example`, or copy it to a local environment.
2. Set `baseUrl` and your test email. Keep tokens and email-link secrets local.
3. Run `auth/email-challenge`, copy the OTP from Mailpit into `loginCode`, then verify it.
4. Read `auth/me`, choose an accessible `memberRoleId`, and switch the active context.
5. Run `master/unit/upsert`, then `catalog/product/create` and the related reads.

Successful create requests that need IDs ask for `response=resource`. Their scripts
store IDs only after a successful status and a valid string identifier. `roleId`
is the role definition; `memberRoleId` is its assignment to a member.

Use a new name/SKU for repeated creates or choose existing accessible IDs. Child IDs
must belong to their selected parent. Deletes, role/session revocations and invitation
acceptance change state; run them deliberately at the appropriate point in the flow.
The collection's numeric ordering is not a complete test scenario.

The [French](https://lahatre-erp-docs.vercel.app/fr/docs/api/bruno) and
[English](https://lahatre-erp-docs.vercel.app/en/docs/api/bruno) guides describe Google sign-in, organization
onboarding, variables, expected responses and current coverage limits.
Use Pest for automated application testing against a disposable database.
