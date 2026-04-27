# Releasing tallcms/filament-registration

Public Composer package distributed via Packagist. Releases happen from this source repo; there's no separate runtime copy to keep in sync (unlike the multisite plugin).

## Release checklist

1. **Make the change** on a feature branch and run the test suite + pint:
   ```bash
   composer test
   composer pint
   ```

2. **Bump the version** in two places — they must match the tag exactly or Packagist will reject the release:
   - `composer.json` → `"version": "X.Y.Z"`
   - `plugin.json` → `"version": "X.Y.Z"`

3. **Update `CHANGELOG.md`** under `## [X.Y.Z] - YYYY-MM-DD`. Move items from `[Unreleased]` if there were any. Lead with the user-visible change, not the internal mechanics.

4. **Commit, push, open + merge a PR.** Direct push to `main` is fine for trivial fixes; non-trivial changes should go through review.

5. **Create the GitHub Release** (this also creates the tag — don't `git tag` separately):
   ```bash
   gh release create vX.Y.Z \
     --repo tallcms/filament-registration \
     --title "vX.Y.Z — <short title>" \
     --notes-file <(cat <<'EOF'
   <release notes — what changed, why, compatibility, migration steps>
   EOF
   )
   ```

6. **Verify the GitHub Release exists** (the most-skipped step):
   ```bash
   gh release view vX.Y.Z --repo tallcms/filament-registration
   ```

7. **Verify Packagist** picked up the new version (~1 minute after the release):
   ```bash
   curl -s https://repo.packagist.org/p2/tallcms/filament-registration.json | jq -r '.packages."tallcms/filament-registration" | .[].version' | head -3
   ```
   The new version should be on top.

## Versioning

Semver:
- **Patch** (X.Y.Z → X.Y.Z+1): bug fixes, doc changes, internal refactors that don't affect the public API.
- **Minor** (X.Y.Z → X.(Y+1).0): new captcha providers, new plugin config methods, new optional features.
- **Major** (X.Y.Z → (X+1).0.0): breaking changes to the public API (e.g. removing a `defaultRole()` method, renaming the `RegistrationResponse` contract usage, dropping a Filament version).

Bump `compatibility.filament` in `plugin.json` if a release requires a newer Filament version.

## Breaking-change discipline

- Document every breaking change in CHANGELOG with a "Migration" subsection.
- Keep deprecated methods as no-op shims for one minor version before removing.
- Update README to remove deprecated usage from examples on the same release.
