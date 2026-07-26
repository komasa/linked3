# Full v29.2.0 Tree Push Instructions

The sandbox cannot push 839 files in one API call and has no outbound git network.

## Local command (run on your machine)

```bash
unzip linked3-v29.2.0-install.zip
cd linked3
git init
git branch -m main
git add -A
git commit -m "v29.2.0 full source baseline"
git remote add origin https://<YOUR_TOKEN>@github.com/komasa/linked3.git
git push -u origin main --force
```

After that, the v30 MVP files already on main will need to be re-applied or merged:
- src/Classes/Dashboard/DashboardPresenter.php
- src/Classes/Core/EventBus.php
- src/Classes/Core/ProviderRegistry.php
- src/Classes/Genesis/GenesisPromptUseCase.php
- migration_shim.php

## Status
- A (full baseline): blocked on network/size → use local git
- B (v30 landing): ready once full tree is on main
