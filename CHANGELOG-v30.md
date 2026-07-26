# Changelog — v30.0.0 MVP

## [30.0.0] - 2026-07-26

### Added
- DashboardPresenter (single source for tab rendering)
- EventBus (beforeFetch / afterFetch hooks)
- ProviderRegistry + KeyRotationService (delegates to existing Factory)
- GenesisPromptUseCase (business logic extraction)
- migration_shim.php + feature flag `linked3_v30_mvp`

### Architecture
- Axiom α Information Entropy Reduction: repeated logic converged
- Axiom β System Dimensionality Reduction: Presenter + UseCase + Registry layers
- 100% backward compatible (old shortcodes, AJAX, partials unchanged)
- Feature flag controlled rollout / instant rollback

### Security / Quality
- No new self:: cross-class references
- CI ready (self-ref checker + PHPStan Level 5)
