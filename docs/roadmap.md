# Refactor roadmap

Goal: a framework-agnostic PHP state machine package, driven by JSON definitions of states, events,
transitions, conditions and commands, usable from Laravel, Symfony or plain PHP.

This document is the umbrella for the refactor. Each phase is implemented on its own branch,
and each branch is created from the previous one.

## Branches

```
main
└── sm-8-refactor-roadmap                 this document
    └── sm-8-phase-1-core-correctness
        └── sm-8-phase-2-framework-agnostic-core
            └── sm-8-phase-3-advanced-features
                └── sm-8-phase-4-adoption
```

| Phase | Branch | Status |
|-------|--------|--------|
| 1. Make what exists correct | `sm-8-phase-1-core-correctness` | Done |
| 2. Framework-agnostic core | `sm-8-phase-2-framework-agnostic-core` | Done |
| 3. Advanced workflow features | `sm-8-phase-3-advanced-features` | Done, except sub-processes |
| 4. Adoption | `sm-8-phase-4-adoption` | Not started |

Rules for every phase:
- Test first (red, green, refactor). `phpunit`, `phpstan` and `phpcs` must be green before a phase is done.
- Update the status table above and tick the items below in the same branch.
- Phase 2 changes the public API, so it should land before any tagged release.

## Review findings (baseline)

Baseline: 18 tests, PHPStan level 6 and phpcs clean. The problems were in behaviour and in what the package exposes.

### Correctness bugs
1. The README's if-else feature was not implemented: the first matching transition was used, and a failing condition never tried the "else" transition.
2. Validation errors were swallowed by `DefinitionReader`, so invalid definitions crashed later with undefined-index errors or a `TypeError`.
3. Schema and models disagreed: `Event` required `command`, `Transition` required `condition`, `event` was not required on transitions, and the README examples used fields the schema rejects (`to`, `command` and `manual` on transitions).
4. Schema features were parsed but not wired up: `isCurrent` was ignored, and `toJson()` output failed the schema (`timeout: null`).
5. `run()` did not check that the state or event exists, so a typo was indistinguishable from "transition not allowed".
6. Commands are attached to events, not transitions, so the same event cannot run different commands from different states.
7. Conditions and commands are instantiated with `new` and no class-exists or interface check.

### Design gaps
- Conditions and commands cannot take dependencies, so they cannot use a framework container. This is the biggest blocker for a framework-agnostic package.
- `run()` returns a string and keeps no state: no persistence contract, history, locking, `can()` or `availableEvents()`.
- No hooks: nothing fires before or after a transition.
- Loading and running are fused in `StateMachineRunner`: no registry of named machines, no file loader, no array input.
- Missing features: `onEnter` auto-triggering, timeouts, manual events listing, sub-processes.
- `Designer` is tied to `jbzoo/mermaid-php`, which is a hard requirement.

### Package hygiene
- Sample machines and classes ship inside `src/`, and `public/index.php` is a dev demo.
- Composer name (`tuxonice/`) and namespace (`Tlab\`) differ.
- CI tests PHP 8.2 only, with no matrix, coverage, `composer validate` or `--prefer-lowest` run. PHPStan is pinned to `^1.12`.
- The README installation section is a `TODO`.
- `ValidationException` has no common base, so callers cannot catch package errors in one place.

## Phase 1: Make what exists correct

Branch: `sm-8-phase-1-core-correctness`

- [x] Reader throws `ValidationException` (with `getErrors()`) for schema errors and malformed JSON.
- [x] Schema, models and README agree. `event` is required on transitions, `command` and `condition` default to `null`, `timeout` accepts `null`, and README examples validate.
- [x] If-else branching: transitions with the same source and event are tried in order, and the first passing condition wins.
- [x] `apply()` returns a `TransitionResult` (`Moved`, `Blocked`, `NoTransition`). Unknown states and events throw. `run()` stays as a string-returning wrapper.
- [x] `isCurrent` is read, `toJson()` round-trips, and `DefinitionReader` no longer accumulates state across reads.
- [x] Tests for each of the above.

Not done here: the class-exists and interface check for conditions and commands. It moves to Phase 2 with the resolvers.

## Phase 2: Framework-agnostic core

Branch: `sm-8-phase-2-framework-agnostic-core`

- [x] `ConditionResolver` and `CommandResolver` built on PSR-11 `ContainerInterface`, defaulting to `new`. The class must exist and implement the interface, otherwise `ResolutionException` is thrown (finding 7).
- [x] PSR-14 event dispatcher with `BeforeTransition`, `AfterTransition` and `TransitionBlocked` events, and a `NullEventDispatcher` by default.
- [x] Loading split from running. `DefinitionReader` reads JSON, arrays and files (`read()`, `readArray()`, `readFile()`), the `StateMachine` definition is immutable, and `StateMachineRunner` takes a definition (`fromJson()` is the shortcut).
- [x] `StatefulInterface` and `StateStorageInterface` (`StatefulStateStorage` by default, `InMemoryStateStorage`), plus `can()`, `apply()`, `applyTo()`, `canApplyTo()`, `availableEvents()` and `availableEventsFor()`.
- [x] `StateMachineException` as the base of all package exceptions.

Decisions and differences from the plan:
- There is no separate `DefinitionLoader` class: adding `readArray()` and `readFile()` to `DefinitionReader` avoided two classes doing the same job.
- `StateStorageInterface` reads and writes the state of a *subject object* (`read(object)`, `write(object, string)`), so it can back any object. `StatefulInterface` is the zero-config path, where the subject keeps its own state.
- `availableEvents()` ignores conditions, because they need data. Use `can()` to evaluate them.
- Breaking changes, acceptable before the first release: `new StateMachineRunner($json)` is now `StateMachineRunner::fromJson($json)`, `StateMachine` and `State` have no setters, `Transition::checkCondition()` is gone (the runner evaluates conditions through the resolver), and `GraphRenderException` no longer extends `RuntimeException`.
- `psr/container` and `psr/event-dispatcher` are new requirements.

## Phase 3: Advanced workflow features

Branch: `sm-8-phase-3-advanced-features`

- [x] `onEnter` auto-triggering.
- [x] `manual` flag exposed through `availableManualEvents()`.
- [x] `timeout` through a `TimeoutScheduler` interface and a `processTimeouts()` entry point that cron or a queue worker can call.
- [x] Registry for multiple named machines.
- [x] Commands attachable to the transition as well as to the event (finding 6).
- [ ] Optional sub-process support. Deferred: it needs a design for how a sub machine reports completion, and nothing else depends on it.

Decisions:
- onEnter events are applied after the triggering transition, with the same data. They are tried in event definition order and the first whose condition passes wins. A failing condition is not an error, the subject stays in the state. A chain is capped at 50 steps, then `OnEnterLoopException` is thrown.
- A transition's command runs after the event's command. Both run only when the transition is taken.
- Timeouts are a number plus a unit (`30 minutes`), enforced by the schema. They are scheduled for subjects only (`applyTo()`), and only for the state a chain ends in. A timeout whose conditions fail is dropped, not retried.
- `TimeoutScheduler` has three methods (`schedule()`, `cancel()`, `pullDue()`). Persistent implementations are left to the application, `InMemoryTimeoutScheduler` ships for tests.
- `TransitionResult::getEvents()` lists the requested event followed by the onEnter events it triggered.
- `StateMachineRunner` takes `scheduler` as a new last optional argument, so nothing breaks.

## Phase 4: Adoption

Branch: `sm-8-phase-4-adoption`

- [ ] Make diagrams optional: move `jbzoo/mermaid-php` to `suggest`, and have `Designer` implement a `DiagramRenderer` interface.
- [ ] Laravel bridge (service provider, container resolver) and Symfony bridge (bundle or autowired resolvers), as thin packages or documented recipes.
- [ ] Real installation and usage README. Move sample machines to `examples/`, remove `public/`, add `.gitattributes`, and align the composer name with the namespace.
- [ ] CI: PHP 8.2 to 8.5 matrix, coverage, PHPStan 2 at level 8 or higher, `composer validate`, `--prefer-lowest`.
- [ ] Tag `0.1.0`.
