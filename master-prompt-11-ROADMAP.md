# Part 11 roadmap — blueprint phases mapped to prompts

**Status:** 11A, 11B, 11C are written, tested and delivered. 11D–11I are an **outline only**: no prompt exists yet, nothing in 11A–11C depends on their details. Each will be written (code + tests + embedded files + manifest) as its own master prompt after the previous one is applied and verified.

| Prompt | Blueprint phase / section | Content | Depends on |
|---|---|---|---|
| **11A** (done) | Phase 0–1, §17 P0 | POST+CSRF logout, API method guards, media GET/POST split, PHP-8.0 gate, manifest verifier, Arabic-letters test, `docs/ai/` hand-off set | — |
| **11B** (done) | Phase 2, §5, §7, §11 | HTTP kernel: front controller, router, request/response, middleware, container, validator, exception handler, health route, API envelope | 11A |
| **11C** (done) | Phase 2, §14 | Module system: manifest, registry, dependency/core checks, console, sample module, menu bridge | 11B |
| 11D | Phase 4, Phase 3, §8, §10 | Database core v2 (namespaced migrations, checksums, `--status`/`--dry-run`, UTC), no default credentials + setup wizard, module permissions stored in `permissions` (design of PK/FK with reasons), `unique`/`exists` validator rules. May be split in two prompts | 11C |
| 11E | Phase 5, §12, §17 | UI foundation v2: view renderer so modules can serve HTML pages, CSP + removal of inline JS, shell extension points | 11D |
| 11F | Phase 6, §14, §15 | Service modules (Users, Roles, Notifications, Media, Dashboard) as modules; dashboard widget registry; file-reference contracts | 11E |
| 11G | Phase 7 | CMS (pages builder) moved into `modules/cms` | 11F |
| 11H | Phase 8 | HRM pilot module (proves the framework with a non-CMS product) | 11G |
| 11I | Phase 9, §27 | Release, CI, docs, Definition of Done checks | 11H |

Rules that stay the same for every prompt: operator-not-engineer, embedded verbatim files with sha256 manifest, prerequisite check with STOP, backup, "Must NOT do" table, library decision, PK/FK design (or explicit "no schema change"), acceptance checklist, mandatory changelog + `docs/ai/` updates, Persian summary to the owner.
