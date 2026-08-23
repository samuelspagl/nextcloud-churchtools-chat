# Nextcloud App Store release runbook

This runbook starts after the certificate request pull request for
`churchtools_chat` has been submitted. It covers the one-time App Store setup
and every subsequent release.

## Keep these files safe

The local certificate directory is:

```text
~/.nextcloud/certificates/
```

It contains the following files:

| File | Purpose | May be shared? |
| --- | --- | --- |
| `churchtools_chat.key` | Private RSA signing key | No — store only in the protected GitHub environment secret. |
| `churchtools_chat.csr` | Certificate signing request | Yes — it is the public request submitted to Nextcloud. |
| `churchtools_chat.crt` | Signed public certificate | Yes — store it in a protected GitHub environment secret. |

Never commit, paste into an issue, or attach the `.key` file to a pull request.
The repository ignores `.key`, `.csr`, and `.crt` files as an additional guard.

## 1. Wait for the certificate request

1. Monitor the pull request in
   [`nextcloud/app-certificate-requests`](https://github.com/nextcloud/app-certificate-requests).
2. Respond to maintainer questions if any arise.
3. When Nextcloud approves the request, obtain the signed public certificate
   for `churchtools_chat`.
4. Save it as:

   ```text
   ~/.nextcloud/certificates/churchtools_chat.crt
   ```

5. Confirm that it matches the intended app ID:

   ```bash
   openssl x509 -in ~/.nextcloud/certificates/churchtools_chat.crt -noout -subject
   ```

   The output must contain `CN = churchtools_chat`.

## 2. Register the app in the App Store

1. Sign in to [Nextcloud App Store](https://apps.nextcloud.com/).
2. Register the app ID exactly as `churchtools_chat`.
3. When the App Store requests proof of ownership, generate it locally:

   ```bash
   printf %s churchtools_chat \
     | openssl dgst -sha512 -sign ~/.nextcloud/certificates/churchtools_chat.key \
     | openssl base64 -A
   ```

4. Paste the resulting signature into the App Store form. Do not paste the
   private key itself.
5. Create an App Store upload token in the developer area and save it in a
   password manager. It is only needed once for GitHub configuration below.

## 3. Configure the protected GitHub environment

In [`samuelspagl/nextcloud-churchtools-chat`](https://github.com/samuelspagl/nextcloud-churchtools-chat), open **Settings → Environments → New environment**.

1. Create an environment named `release`.
2. Enable **Required reviewers** and add the release owner. This makes every
   Store upload a manual approval step.
3. Under **Environment secrets**, add exactly these secrets:

   | Secret | Value |
   | --- | --- |
   | `APP_PRIVATE_KEY` | Complete PEM contents of `churchtools_chat.key` |
   | `APP_PUBLIC_CRT` | Complete PEM contents of `churchtools_chat.crt` |
   | `APPSTORE_TOKEN` | App Store upload token |

4. Under **Settings → Secrets and variables → Actions → Variables**, add the
   repository variable `APPSTORE_ENABLED` with the value `true`.

Do not create these values as repository-wide secrets: they belong only to the
review-protected `release` environment.

## 4. Publish the first Store release

The workflow is **Publish to Nextcloud App Store**. It creates a separate
`churchtools_chat-<tag>.tar.gz` archive; the existing GitHub ZIP release stays
unchanged.

For the first Store upload:

1. Open **Actions → Publish to Nextcloud App Store → Run workflow**.
2. Enter the Git tag to publish, for example `v0.3.19`.
3. Leave **nightly** disabled for a stable release.
4. Start the workflow.
5. Open the pending `release` environment approval and approve it only after
   confirming the tag and changelog.
6. Wait for the workflow to complete. It will build production assets, package
   the app with `churchtools_chat/` as the archive root, attach the tarball to
   the GitHub Release, sign the archive, and submit it to the App Store.

The workflow validates the private key and public certificate before upload,
and checks the packaged `appinfo/info.xml` version before the archive is used.

## 5. Verify the published app

After the workflow succeeds:

1. Confirm the GitHub Release has a `churchtools_chat-<tag>.tar.gz` asset.
2. Confirm the new version appears on the App Store listing and is marked as a
   stable release (or nightly when selected).
3. Download the tarball and check that its only top-level directory is
   `churchtools_chat/`:

   ```bash
   tar -tzf churchtools_chat-<tag>.tar.gz | head
   ```

4. Test-install it into a disposable Nextcloud 34 instance. After extracting
   it into `custom_apps/`, run the following commands with the Nextcloud web
   user (typically `www-data`):

   ```bash
   sudo -u <web-user> php occ app:enable churchtools_chat
   sudo -u <web-user> php occ integrity:check-app churchtools_chat
   ```

   For this repository's Docker stack, check out the release tag and use:

   ```bash
   docker compose up -d
   docker compose exec -u www-data app php occ integrity:check-app churchtools_chat
   ```

5. Open the app in Nextcloud and run a short smoke test: configure a test
   ChurchTools account, load rooms, open a conversation, and send a test
   message.

If the upload fails, do not overwrite a published version. Fix the cause, bump
the semantic version in both `appinfo/info.xml` and `package.json`, and release
the new version.

## Subsequent releases

1. Bump the version in `appinfo/info.xml` and `package.json` to the same,
   strictly higher `major.minor.patch` version.
2. Update `CHANGELOG.md`.
3. Open and merge the pull request after all required checks pass.
4. The main verification workflow creates the GitHub tag and release.
5. The App Store workflow follows the successful release workflow and waits at
   the protected `release` environment for manual approval.
6. Approve only the expected version, then carry out the verification steps
   above.

For a pre-release, publish a GitHub pre-release or run the workflow manually
with **nightly** enabled. This sends the release to the App Store as a nightly
instead of a stable version.

## Recovery and access hygiene

- If the private key is lost, request a new certificate; existing releases can
  no longer be signed with that key.
- If the App Store token leaks, revoke it in the App Store and replace only
  `APPSTORE_TOKEN` in the `release` environment.
- If the private key leaks, revoke/replace the certificate through Nextcloud,
  generate a new key pair, and replace both certificate secrets.
- After manual GitHub setup, sign out from GitHub in the browser if the session
  was temporary.
