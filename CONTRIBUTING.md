# Contributing to Universal Commerce

Thanks for improving the project. Start with [ROADMAP.md](ROADMAP.md) for priorities, missing verification and the definition of a stable release. Report bugs with a reproducible minimal case, PHP/MySQL versions, relevant migration level and browser environment. **Never post credentials, access tokens, real customer data, private logs or database dumps in issues.**

For pull requests:

1. Open an issue for major breaking work; describe the goal and compatibility impact.
2. Keep database migrations **append-only**. Deployed migration checksums are validated; modifying old SQL migrations breaks upgrades.
3. Include tests or an explicit manual verification plan for changed business logic and UI.
4. Preserve base-path-aware routing, Apache protections, CSRF handling, admin authorization and English/Arabic rendering.
5. Do not add unlicensed imagery, commercial fonts, scraped catalogs or merchant/customer data.
6. Run PHP lint and any relevant database/smoke tests before submitting.

By contributing, you confirm you have the right to license your contribution under the project MIT License.

## Help for new contributors

Start with [the code architecture](docs/ARCHITECTURE.md), set up a [local test store](docs/GETTING-STARTED.md), and use [troubleshooting](docs/TROUBLESHOOTING.md). Our [code of conduct](CODE_OF_CONDUCT.md) applies to public discussions and pull requests.
